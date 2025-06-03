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
            // Пытаемся создать директорию, если ее нет
            // @ подавляет ошибку, если mkdir не удается (например, из-за прав)
            if (!@mkdir(self::$logDir, 0775, true) && !is_dir(self::$logDir)) {
                error_log("Logger Error: Failed to create log directory: " . self::$logDir . ". Falling back to system error_log.");
                self::$fallbackToSystemLog = true;
                self::$initialized = true;
                return;
            }
        }

        if (!is_writable(self::$logDir)) {
            error_log("Logger Error: Log directory " . self::$logDir . " is not writable. Falling back to system error_log.");
            self::$fallbackToSystemLog = true;
        } else {
            self::$logFile = self::$logDir . '/application_' . date('Y-m-d') . '.log';
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

        $formattedMessage = "[" . date('Y-m-d H:i:s') . "] [" . $level . "] " . trim((string)$message) . PHP_EOL . PHP_EOL; // Добавим пустую строку для читаемости

        if (self::$fallbackToSystemLog || self::$logFile === null) {
            error_log(trim($formattedMessage)); // Используем системный логгер
            return;
        }

        // Пытаемся записать в наш файл
        if (@file_put_contents(self::$logFile, $formattedMessage, FILE_APPEND | LOCK_EX) === false) {
            // Если не удалось записать в наш файл, пишем в системный лог
            error_log("Logger Fallback: Failed to write to custom log file " . self::$logFile . ". Message: " . trim($formattedMessage));
        }
    }
}
?>
