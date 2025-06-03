<?php

namespace ResearcherAI;

require_once __DIR__ . '/../vendor/autoload.php'; // For PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class FileParser {
    private $maxRows = 1000;
    private $maxCols = 50;
    
    public function parse($content, $filename, $mimeType = null) {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        try {
            switch ($extension) {
                case 'xlsx':
                case 'xls':
                    return $this->parseExcel($content);
                    
                case 'csv':
                    return $this->parseCsv($content);
                    
                case 'txt':
                    return $this->parseText($content);
                    
                case 'pdf':
                    return $this->parsePdf($content);
                    
                case 'docx':
                case 'doc':
                    return $this->parseWord($content);
                    
                default:
                    return $this->parseText($content); // Fallback to text parsing
            }
        } catch (Exception $e) {
            error_log("Error parsing file $filename: " . $e->getMessage());
            return [];
        }
    }
    
    private function parseExcel($content) {
        try {
            // Save content to temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
            file_put_contents($tempFile, $content);
            
            $reader = IOFactory::createReaderForFile($tempFile);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($tempFile);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $data = [];
            $rowCount = 0;
            
            foreach ($worksheet->getRowIterator() as $row) {
                if ($rowCount >= $this->maxRows) {
                    break;
                }
                
                $rowData = [];
                $cellCount = 0;
                
                foreach ($row->getCellIterator() as $cell) {
                    if ($cellCount >= $this->maxCols) {
                        break;
                    }
                    
                    $value = $cell->getCalculatedValue();
                    $rowData[] = $this->cleanCellValue($value);
                    $cellCount++;
                }
                
                // Skip empty rows
                if (!empty(array_filter($rowData))) {
                    $data[] = $rowData;
                    $rowCount++;
                }
            }
            
            unlink($tempFile);
            return $data;
            
        } catch (Exception $e) {
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw $e;
        }
    }
    
    private function parseCsv($content) {
        $data = [];
        $lines = explode("\n", $content);
        $rowCount = 0;
        
        // Try to detect delimiter
        $delimiters = [';', ',', '\t', '|'];
        $delimiter = $this->detectCsvDelimiter($content, $delimiters);
        
        foreach ($lines as $line) {
            if ($rowCount >= $this->maxRows) {
                break;
            }
            
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            $rowData = str_getcsv($line, $delimiter);
            
            // Limit columns
            if (count($rowData) > $this->maxCols) {
                $rowData = array_slice($rowData, 0, $this->maxCols);
            }
            
            // Clean and convert encoding
            $rowData = array_map(function($value) {
                return $this->cleanCellValue($value);
            }, $rowData);
            
            if (!empty(array_filter($rowData))) {
                $data[] = $rowData;
                $rowCount++;
            }
        }
        
        return $data;
    }
    
    private function parseText($content) {
        $data = [];
        $lines = explode("\n", $content);
        $rowCount = 0;
        
        foreach ($lines as $line) {
            if ($rowCount >= $this->maxRows) {
                break;
            }
            
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Try to split by common separators
            $separators = ['\t', ';', '|', '  ']; // Tab, semicolon, pipe, double space
            $bestSplit = [$line]; // Default: whole line
            
            foreach ($separators as $sep) {
                $split = preg_split('/' . preg_quote($sep, '/') . '+/', $line);
                if (count($split) > count($bestSplit)) {
                    $bestSplit = $split;
                }
            }
            
            // Clean data
            $bestSplit = array_map(function($value) {
                return $this->cleanCellValue($value);
            }, $bestSplit);
            
            if (!empty(array_filter($bestSplit))) {
                $data[] = $bestSplit;
                $rowCount++;
            }
        }
        
        return $data;
    }
    
    private function parsePdf($content) {
        $tempFile = null;
        try {
            // Create temporary file for PDF content
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
            if ($tempFile === false) {
                throw new Exception('Failed to create temporary file for PDF.');
            }
            // It's important to add the .pdf extension, as some versions of pdftotext require it
            $tempPdfFile = $tempFile . '.pdf';
            rename($tempFile, $tempPdfFile); // Rename to have the correct extension
            $tempFile = $tempPdfFile; // Update the temporary file name

            if (file_put_contents($tempFile, $content) === false) {
                throw new Exception('Failed to write PDF content to temporary file.');
            }

            // Form the command for pdftotext
            // -enc UTF-8: for UTF-8 output
            // -layout: tries to preserve the original text layout, which can be useful
            // - (dash): output to stdout
            // escapeshellarg: for safe passing of the file name
            $command = 'pdftotext -enc UTF-8 -layout ' . escapeshellarg($tempFile) . ' -';
            
            // Execute the command
            // Suppress possible errors from shell_exec if pdftotext outputs something to stderr
            $extractedText = @shell_exec($command);

            if ($extractedText === null) {
                // shell_exec might be disabled or an error occurred that it couldn't handle
                // Try without -layout if the previous command failed
                $command_simple = 'pdftotext -enc UTF-8 ' . escapeshellarg($tempFile) . ' -';
                $extractedText = @shell_exec($command_simple);
                if ($extractedText === null) {
                    throw new Exception('Failed to execute pdftotext. It might not be installed, not in PATH, or shell_exec is disabled.');
                }
            }

            if (empty(trim($extractedText))) {
                // pdftotext might not have extracted anything (e.g., PDF with only images)
                // or an error occurred but shell_exec didn't return null
                // Try the old method as a fallback
                return $this->parsePdfFallback($content);
            }
            
            return $this->parseText($extractedText);

        } catch (Exception $e) {
            error_log("PDF parsing error (pdftotext): " . $e->getMessage());
            // Try to use the old method in case of any error with pdftotext
            return $this->parsePdfFallback($content);
        } finally {
            // Delete the temporary file if it was created
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
            // If the original tempnam file without .pdf remains (in case of rename error)
            $originalTempFile = str_replace('.pdf', '', $tempFile ?? '');
            if ($originalTempFile && file_exists($originalTempFile)) {
                 unlink($originalTempFile);
            }
        }
    }

    // Old PDF parsing method as a fallback
    private function parsePdfFallback($content) {
        try {
            $text = '';
            if (preg_match_all('/\((.*?)\)/', $content, $matches)) {
                $text = implode(' ', $matches[1]);
            }
            if (empty($text)) {
                if (preg_match_all('/[A-Za-zА-Яа-я0-9\s\.,;:!?\-]+/', $content, $matches)) {
                    $text = implode(' ', $matches[0]);
                }
            }
            return $this->parseText($text);
        } catch (Exception $e) {
            error_log("PDF parsing error (fallback): " . $e->getMessage());
            return [];
        }
    }
    
    private function parseWord($content) {
        // Basic Word document parsing (simplified version)
        // For production, use libraries like PhpWord
        
        try {
            // Extract text from Word document
            $text = '';
            
            // For .docx files (ZIP-based)
            if (strpos($content, 'PK') === 0) {
                // This is a ZIP file (DOCX)
                $tempFile = tempnam(sys_get_temp_dir(), 'docx_');
                file_put_contents($tempFile, $content);
                
                $zip = new ZipArchive();
                if ($zip->open($tempFile) === TRUE) {
                    $xmlContent = $zip->getFromName('word/document.xml');
                    if ($xmlContent) {
                        // Extract text from XML
                        $text = strip_tags($xmlContent);
                        $text = html_entity_decode($text);
                    }
                    $zip->close();
                }
                unlink($tempFile);
            } else {
                // For .doc files, try to extract readable text
                $text = $this->extractTextFromBinary($content);
            }
            
            return $this->parseText($text);
            
        } catch (Exception $e) {
            error_log("Word parsing error: " . $e->getMessage());
            return [];
        }
    }
    
    private function detectCsvDelimiter($content, $delimiters) {
        $delimiterCounts = [];
        
        foreach ($delimiters as $delimiter) {
            $delimiter = $delimiter === '\t' ? "\t" : $delimiter;
            $lines = array_slice(explode("\n", $content), 0, 5); // Check first 5 lines
            $count = 0;
            
            foreach ($lines as $line) {
                $count += substr_count($line, $delimiter);
            }
            
            $delimiterCounts[$delimiter] = $count;
        }
        
        // Return delimiter with highest count
        return array_search(max($delimiterCounts), $delimiterCounts) ?: ';';
    }
    
    private function cleanCellValue($value) {
        if ($value === null || $value === '') {
            return '';
        }
        
        // Convert to string
        $value = (string) $value;
        
        // Handle encoding
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
        
        // Remove excessive whitespace
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        
        // Remove control characters except newlines
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
        
        return $value;
    }
    
    private function extractTextFromBinary($content) {
        // Extract readable text from binary content
        $text = '';
        
        // Find sequences of printable characters
        if (preg_match_all('/[\x20-\x7E\x80-\xFF]{4,}/', $content, $matches)) {
            $text = implode(' ', $matches[0]);
        }
        
        // Clean up
        $text = preg_replace('/[^\w\s\.,;:!?\-А-Яа-я]/u', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    public function validateFile($content, $filename) {
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (strlen($content) > $maxSize) {
            throw new Exception("Файл $filename слишком большой");
        }
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = ['xlsx', 'xls', 'csv', 'txt', 'pdf', 'docx', 'doc'];
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception("Неподдерживаемый формат файла: $extension");
        }
        
        return true;
    }
    
    public function getFileInfo($content, $filename) {
        return [
            'filename' => $filename,
            'size' => strlen($content),
            'extension' => pathinfo($filename, PATHINFO_EXTENSION),
            'encoding' => mb_detect_encoding($content),
            'is_valid' => $this->validateFile($content, $filename)
        ];
    }
}
?>