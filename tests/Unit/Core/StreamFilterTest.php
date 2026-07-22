<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Tests\Unit\Core;

use Wursta\Sentinel\Core\HookInterface;
use Wursta\Sentinel\Core\HookManager;
use Wursta\Sentinel\Core\StreamFilter;
use PHPUnit\Framework\TestCase;

final class StreamFilterTest extends TestCase
{
    protected function setUp(): void
    {
        HookManager::reset();
        StreamFilter::register();
    }

    protected function tearDown(): void
    {
        HookManager::reset();
    }

    public function testRegisterIsIdempotent(): void
    {
        self::assertTrue(StreamFilter::register());
        self::assertTrue(StreamFilter::register());
        self::assertContains(StreamFilter::FILTER_NAME, stream_get_filters());
    }

    public function testFilterTransformsBufferedSource(): void
    {
        HookManager::registerHook(new class implements HookInterface {
            public function transformCode(string $code): string
            {
                return str_replace('curl_exec', 'HOOKED_curl_exec', $code);
            }
        });

        $source = "<?php\ncurl_exec(\$ch);\n";
        $stream = fopen('php://memory', 'wb+');
        self::assertNotFalse($stream);

        fwrite($stream, $source);
        rewind($stream);

        $filter = stream_filter_append($stream, StreamFilter::FILTER_NAME, STREAM_FILTER_READ);
        self::assertNotFalse($filter);

        $result = stream_get_contents($stream);
        fclose($stream);

        self::assertIsString($result);
        self::assertStringContainsString('HOOKED_curl_exec($ch)', $result);
        self::assertDoesNotMatchRegularExpression('/(?<!HOOKED_)curl_exec\(\$ch\)/', $result);
    }
}
