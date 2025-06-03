<?php
namespace ResearcherAI;

class Logger {
    private static $logFile;
    private static $logDir;
    private static $initialized = false;
    private static $fallbackToSystemLog = false;

    private static function initialize() {
        if (self::$initialized) {
            return;
        }

        self::$logDir = __DIR__ . '/../logs';

        if (!is_dir(self::$logDir)) {
            if (!@mkdir(self::$logDir, 0775, true) && !is_dir(self::$logDir)) {
                // Используем error_log(), который теперь должен писать в logs/php_errors.log из-за настроек в process_query.php
                error_log("Logger Setup Error: Failed to create log directory: " . self::$logDir . ". Falling back to system error_log mechanism.");
                self::$fallbackToSystemLog = true;
                self::$initialized = true;
                return;
            }
        }

        if (!is_writable(self::$logDir)) {
            error_log("Logger Setup Error: Log directory " . self::$logDir . " is not writable. Falling back to system error_log mechanism.");
            self::$fallbackToSystemLog = true;
        } else {
            self::$logFile = self::$logDir . '/application_' . date('Y-m-d') . '.log';
            // Попытка тестовой записи при инициализации
            $initMessage = "[" . date('Y-m-d H:i:s') . "] [DEBUG] Logger initialized. Custom log file target: " . self::$logFile . PHP_EOL;
            if (file_put_contents(self::$logFile, $initMessage, FILE_APPEND | LOCK_EX) === false) {
                $errorDetails = error_get_last();
                $fallbackMessage = "Logger Setup Error: Failed initial write to custom log file " . self::$logFile;
                if ($errorDetails) {
                    $fallbackMessage .= " | PHP Error: " . $errorDetails['message'];
                }
                error_log($fallbackMessage . " Switching to fallback.");
                self::$fallbackToSystemLog = true; // Переключаемся на fallback, если даже инициализационная запись не удалась
            }
        }
        self::$initialized = true;
    }

    public static function info($message) {
        self::write('INFO', $message);
    }

    public static function error($message, \Throwable $exception = null) {
        $fullMessage = $message;
        if ($exception !== null) {
            $fullMessage .= "\nException Type: " . get_class($exception);
            $fullMessage .= "\nMessage: " . $exception->getMessage();
            $fullMessage .= "\nFile: " . $exception->getFile() . ":" . $exception->getLine();
            $fullMessage .= "\nTrace: " . $exception->getTraceAsString();
        }
        self::write('ERROR', $fullMessage);
    }

    public static function warning($message) {
        self::write('WARNING', $message);
    }

    private static function write($level, $message) {
        self::initialize();

        $formattedMessage = "[" . date('Y-m-d H:i:s') . "] [" . $level . "] " . trim((string)$message) . PHP_EOL . PHP_EOL;

        if (self::$fallbackToSystemLog || self::$logFile === null) {
            error_log(trim($formattedMessage)); 
            return;
        }

        if (file_put_contents(self::$logFile, $formattedMessage, FILE_APPEND | LOCK_EX) === false) {
            $errorDetails = error_get_last();
            $fallbackMessage = "Logger Fallback: Failed to write to custom log file " . self::$logFile . ". Original Message: [" . $level . "] " . trim((string)$message);
            if ($errorDetails) {
                $fallbackMessage .= " | PHP Error: " . $errorDetails['message'] . " in " . $errorDetails['file'] . " on line " . $errorDetails['line'];
            }
            error_log($fallbackMessage);
        }
    }
}
?>
