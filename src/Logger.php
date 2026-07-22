<?php

declare(strict_types=1);

namespace Wursta\Sentinel;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

final class Logger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    /** @var resource|null */
    private $fileHandle;

    public function __construct(?string $logFile = null)
    {
        if ($logFile !== null) {
            $this->setLogFile($logFile);
        }
    }

    public function setLogFile(string $logFile): void
    {
        $this->closeFile();

        $dir = dirname($logFile);
        if ($dir !== '' && $dir !== '.' && !is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $handle = fopen($logFile, 'ab');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Unable to open log file "%s".', $logFile));
        }

        $this->fileHandle = $handle;
    }

    /**
     * @param mixed                $level
     * @param string               $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        if (!is_string($level) && !is_int($level)) {
            throw new InvalidArgumentException('Log level must be a string or integer.');
        }

        $levelString = (string) $level;
        $this->assertValidLevel($levelString);

        $record = [
            'level' => $levelString,
            'message' => (string) $message,
            'context' => $context,
        ];
        $this->records[] = $record;

        if ($this->fileHandle !== null) {
            $line = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($line !== false) {
                fwrite($this->fileHandle, $line . PHP_EOL);
            }
        }
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function getRecords(): array
    {
        return $this->records;
    }

    /**
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function getRecordsByMessage(string $message): array
    {
        $matched = [];
        foreach ($this->records as $record) {
            if ($record['message'] === $message) {
                $matched[] = $record;
            }
        }

        return $matched;
    }

    public function clear(): void
    {
        $this->records = [];
    }

    public function __destruct()
    {
        $this->closeFile();
    }

    private function closeFile(): void
    {
        if ($this->fileHandle !== null) {
            fclose($this->fileHandle);
            $this->fileHandle = null;
        }
    }

    private function assertValidLevel(string $level): void
    {
        $levels = [
            LogLevel::EMERGENCY => true,
            LogLevel::ALERT => true,
            LogLevel::CRITICAL => true,
            LogLevel::ERROR => true,
            LogLevel::WARNING => true,
            LogLevel::NOTICE => true,
            LogLevel::INFO => true,
            LogLevel::DEBUG => true,
        ];

        if (!isset($levels[$level])) {
            throw new InvalidArgumentException(sprintf('Invalid log level "%s".', $level));
        }
    }
}
