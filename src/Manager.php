<?php

declare(strict_types=1);

namespace Wursta\Sentinel;

use Wursta\Sentinel\Core\FileStreamWrapper;
use Wursta\Sentinel\Core\HookManager;
use Wursta\Sentinel\Core\StreamFilter;
use Wursta\Sentinel\Hooks\CurlHook;

final class Manager
{
    private static ?self $instance = null;

    private Logger $logger;

    private bool $enabled = false;

    private function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger();
    }

    public static function getInstance(?Logger $logger = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($logger);
        } elseif ($logger !== null) {
            self::$instance->logger = $logger;
        }

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        if (self::$instance !== null && self::$instance->enabled) {
            self::$instance->disable();
        }
        self::$instance = null;
    }

    public function enable(?string $logFile = null): void
    {
        if ($this->enabled) {
            return;
        }

        if ($logFile !== null) {
            $this->logger->setLogFile($logFile);
        }

        HookManager::reset();
        CurlHook::reset();
        CurlHook::setLogger($this->logger);
        HookManager::registerHook(new CurlHook());

        StreamFilter::register();
        FileStreamWrapper::register([
            $this->librarySrcPath(),
        ]);

        $this->enabled = true;
    }

    public function disable(): void
    {
        if (!$this->enabled) {
            return;
        }

        FileStreamWrapper::restore();
        HookManager::reset();
        CurlHook::reset();
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    private function librarySrcPath(): string
    {
        $path = realpath(__DIR__);

        return $path !== false ? $path : __DIR__;
    }
}
