<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Core;

interface HookInterface
{
    public function transformCode(string $code): string;
}
