<?php

namespace MoBo;

class Logger
{
    private string $logPath;
    private string $level;
    private array $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3, 'critical' => 4];

    public function __construct(string $logPath, string $level = 'info')
    {
        $this->logPath = $logPath;
        $this->level = $level;
        
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
    }

    public function debug(string $message, string $context = 'SYSTEM', array $data = []): void
    {
        $this->log('debug', $message, $context, $data);
    }

    public function info(string $message, string $context = 'SYSTEM', array $data = []): void
    {
        $this->log('info', $message, $context, $data);
    }

    public function warning(string $message, string $context = 'SYSTEM', array $data = []): void
    {
        $this->log('warning', $message, $context, $data);
    }

    public function error(string $message, string $context = 'SYSTEM', array $data = []): void
    {
        $this->log('error', $message, $context, $data);
    }

    public function critical(string $message, string $context = 'SYSTEM', array $data = []): void
    {
        $this->log('critical', $message, $context, $data);
    }

    private function log(string $level, string $message, string $context, array $data): void
    {
        if ($this->levels[$level] < $this->levels[$this->level]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);
        $contextUpper = strtoupper($context);
        
        $logMessage = "[{$timestamp}] [{$levelUpper}] [{$contextUpper}] {$message}";
        
        if (!empty($data)) {
            $logMessage .= ' ' . json_encode($data);
        }
        
        $logMessage .= PHP_EOL;
        
        file_put_contents($this->logPath, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console in debug mode
        if ($this->level === 'debug') {
            echo $logMessage;
        }
    }

    public function setLevel(string $level): void
    {
        if (isset($this->levels[$level])) {
            $this->level = $level;
        }
    }
}