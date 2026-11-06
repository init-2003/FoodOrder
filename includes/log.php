<?php
declare(strict_types=1);

function appLog(string $level, string $message, array $context = []): void {
    static $logger = null;
    if ($logger === null) {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) require_once $autoload;
        if (class_exists(\Monolog\Logger::class)) {
            $logger = new \Monolog\Logger('ordering');
            $logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());
        } else {
            $logger = false;
        }
    }
    if ($logger !== false) {
        $logger->log($level, $message, $context);
    } else {
        error_log($message . ($context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : ''));
    }
}
