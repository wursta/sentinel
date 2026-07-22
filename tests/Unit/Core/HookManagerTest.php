<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Tests\Unit\Core;

use Wursta\Sentinel\Core\HookInterface;
use Wursta\Sentinel\Core\HookManager;
use PHPUnit\Framework\TestCase;

final class HookManagerTest extends TestCase
{
    protected function setUp(): void
    {
        HookManager::reset();
    }

    protected function tearDown(): void
    {
        HookManager::reset();
    }

    public function testRegisterAndTransformCode(): void
    {
        HookManager::registerHook($this->createHook('a', 'b'));
        HookManager::registerHook($this->createHook('b', 'c'));

        self::assertSame('c', HookManager::transformCode('a'));
        self::assertCount(2, HookManager::getHooks());
    }

    public function testResetClearsHooks(): void
    {
        HookManager::registerHook($this->createHook('a', 'b'));
        HookManager::reset();

        self::assertSame([], HookManager::getHooks());
        self::assertSame('a', HookManager::transformCode('a'));
    }

    private function createHook(string $from, string $to): HookInterface
    {
        return new class($from, $to) implements HookInterface {
            private string $from;
            private string $to;

            public function __construct(string $from, string $to)
            {
                $this->from = $from;
                $this->to = $to;
            }

            public function transformCode(string $code): string
            {
                return str_replace($this->from, $this->to, $code);
            }
        };
    }
}
