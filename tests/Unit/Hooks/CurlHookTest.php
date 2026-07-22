<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Tests\Unit\Hooks;

use Wursta\Sentinel\Hooks\CurlHook;
use Wursta\Sentinel\Logger;
use PHPUnit\Framework\TestCase;

final class CurlHookTest extends TestCase
{
    protected function setUp(): void
    {
        CurlHook::reset();
    }

    protected function tearDown(): void
    {
        CurlHook::reset();
    }

    public function testTransformCodeRewritesCurlFunctions(): void
    {
        $hook = new CurlHook();
        $code = <<<'PHP'
<?php
$ch = curl_init('https://example.com');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt_array($ch, [CURLOPT_TIMEOUT => 1]);
curl_exec($ch);
curl_close($ch);
PHP;

        $transformed = $hook->transformCode($code);

        self::assertStringContainsString('\\Wursta\\Sentinel\\Hooks\\CurlHook::curl_init(', $transformed);
        self::assertStringContainsString('\\Wursta\\Sentinel\\Hooks\\CurlHook::curl_setopt(', $transformed);
        self::assertStringContainsString('\\Wursta\\Sentinel\\Hooks\\CurlHook::curl_setopt_array(', $transformed);
        self::assertStringContainsString('\\Wursta\\Sentinel\\Hooks\\CurlHook::curl_exec(', $transformed);
        self::assertStringContainsString('\\Wursta\\Sentinel\\Hooks\\CurlHook::curl_close(', $transformed);
    }

    public function testTransformCodeDoesNotTouchPrefixedNames(): void
    {
        $hook = new CurlHook();
        $code = "<?php\nmy_curl_exec(\$ch);\n";

        self::assertSame($code, $hook->transformCode($code));
    }

    public function testBuildRequestLogResolvesMethodUrlHeadersBody(): void
    {
        $log = CurlHook::buildRequestLog([
            CURLOPT_URL => 'https://example.com/api',
            CURLOPT_CUSTOMREQUEST => 'put',
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Test: 1'],
            CURLOPT_POSTFIELDS => '{"a":1}',
        ]);

        self::assertSame('curl', $log['type']);
        self::assertSame('PUT', $log['method']);
        self::assertSame('https://example.com/api', $log['url']);
        self::assertSame(['Content-Type: application/json', 'X-Test: 1'], $log['headers']);
        self::assertSame('{"a":1}', $log['body']);
        self::assertIsFloat($log['timestamp']);
    }

    public function testCurlExecLogsStructuredRequest(): void
    {
        $logger = new Logger();
        CurlHook::setLogger($logger);

        $ch = CurlHook::curl_init('https://example.com');
        self::assertNotFalse($ch);

        CurlHook::curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        CurlHook::curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        CurlHook::curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        // Avoid real network: force fail fast without asserting response.
        CurlHook::curl_setopt($ch, CURLOPT_URL, 'http://127.0.0.1:1');
        CurlHook::curl_exec($ch);
        CurlHook::curl_close($ch);

        $records = $logger->getRecordsByMessage('curl');
        self::assertCount(1, $records);
        self::assertSame('GET', $records[0]['context']['method']);
        self::assertSame('http://127.0.0.1:1', $records[0]['context']['url']);
    }
}
