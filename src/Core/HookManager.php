<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Core;

final class HookManager
{
    /** @var list<HookInterface> */
    private static array $hooks = [];

    public static function registerHook(HookInterface $hook): void
    {
        self::$hooks[] = $hook;
    }

    public static function transformCode(string $code): string
    {
        foreach (self::$hooks as $hook) {
            $code = $hook->transformCode($code);
        }

        return $code;
    }

    public static function reset(): void
    {
        self::$hooks = [];
    }

    /**
     * @return list<HookInterface>
     */
    public static function getHooks(): array
    {
        return self::$hooks;
    }
}
