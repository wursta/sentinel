<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Tests\Integration;

use Wursta\Sentinel\Logger;
use Wursta\Sentinel\Manager;
use PHPUnit\Framework\TestCase;

final class CurlInterceptTest extends TestCase
{
    /** @var resource|null */
    private static $serverProcess;

    private static string $baseUrl = '';

    private static string $serverHost = '127.0.0.1';

    private static int $serverPort = 0;

    public static function setUpBeforeClass(): void
    {
        self::$serverPort = self::findFreePort();
        self::$baseUrl = sprintf('http://%s:%d', self::$serverHost, self::$serverPort);

        $stub = realpath(__DIR__ . '/../fixtures/http_stub.php');
        self::assertNotFalse($stub);

        $command = [
            PHP_BINARY,
            '-S',
            sprintf('%s:%d', self::$serverHost, self::$serverPort),
            $stub,
        ];

        self::$serverProcess = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', sys_get_temp_dir() . '/interceptor-http-stub-stdout.log', 'a'],
                2 => ['file', sys_get_temp_dir() . '/interceptor-http-stub-stderr.log', 'a'],
            ],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        self::assertNotFalse(self::$serverProcess);
        self::waitForServer(self::$baseUrl . '/health');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            $deadline = microtime(true) + 2.0;
            while (proc_get_status(self::$serverProcess)['running'] && microtime(true) < $deadline) {
                usleep(50000);
            }
            if (proc_get_status(self::$serverProcess)['running']) {
                proc_terminate(self::$serverProcess, 9);
            }
            proc_close(self::$serverProcess);
            self::$serverProcess = null;
        }
    }

    protected function setUp(): void
    {
        Manager::resetInstance();
        $logger = new Logger();
        $manager = Manager::getInstance($logger);
        $manager->enable();
        $logger->clear();
    }

    protected function tearDown(): void
    {
        Manager::resetInstance();
    }

    public function testInterceptsGetRequest(): void
    {
        $baseUrl = self::$baseUrl;
        $response = include __DIR__ . '/../fixtures/curl_get.php';

        self::assertIsString($response);
        $records = Manager::getInstance()->getLogger()->getRecordsByMessage('curl');
        self::assertCount(1, $records);
        self::assertSame('GET', $records[0]['context']['method']);
        self::assertSame($baseUrl . '/get', $records[0]['context']['url']);
        self::assertContains('X-Test: get-header', $records[0]['context']['headers']);
    }

    public function testInterceptsPostRequest(): void
    {
        $baseUrl = self::$baseUrl;
        $response = include __DIR__ . '/../fixtures/curl_post.php';

        self::assertIsString($response);
        $records = Manager::getInstance()->getLogger()->getRecordsByMessage('curl');
        self::assertCount(1, $records);
        self::assertSame('POST', $records[0]['context']['method']);
        self::assertSame($baseUrl . '/post', $records[0]['context']['url']);
        self::assertSame('{"hello":"world"}', $records[0]['context']['body']);
        self::assertContains('Content-Type: application/json', $records[0]['context']['headers']);
        self::assertContains('X-Test: post-header', $records[0]['context']['headers']);
    }

    public function testInterceptsPutRequest(): void
    {
        $baseUrl = self::$baseUrl;
        $response = include __DIR__ . '/../fixtures/curl_put.php';

        self::assertIsString($response);
        $records = Manager::getInstance()->getLogger()->getRecordsByMessage('curl');
        self::assertCount(1, $records);
        self::assertSame('PUT', $records[0]['context']['method']);
        self::assertSame($baseUrl . '/put', $records[0]['context']['url']);
        self::assertSame('put-body', $records[0]['context']['body']);
        self::assertContains('X-Test: put-header', $records[0]['context']['headers']);
    }

    public function testInterceptsDeleteRequest(): void
    {
        $baseUrl = self::$baseUrl;
        $response = include __DIR__ . '/../fixtures/curl_delete.php';

        self::assertIsString($response);
        $records = Manager::getInstance()->getLogger()->getRecordsByMessage('curl');
        self::assertCount(1, $records);
        self::assertSame('DELETE', $records[0]['context']['method']);
        self::assertSame($baseUrl . '/delete', $records[0]['context']['url']);
        self::assertContains('X-Test: delete-header', $records[0]['context']['headers']);
    }

    private static function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $name = stream_socket_get_name($socket, false);
        self::assertIsString($name);
        fclose($socket);

        $parts = explode(':', $name);
        $port = (int) end($parts);
        self::assertGreaterThan(0, $port);

        return $port;
    }

    private static function waitForServer(string $url): void
    {
        $deadline = microtime(true) + 5.0;
        $lastError = '';

        while (microtime(true) < $deadline) {
            $ch = curl_init($url);
            if ($ch === false) {
                break;
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 1);
            $result = curl_exec($ch);
            $errno = curl_errno($ch);
            $lastError = curl_error($ch);
            curl_close($ch);

            if ($result !== false && $errno === 0) {
                return;
            }

            usleep(50000);
        }

        self::fail('HTTP stub server did not start: ' . $lastError);
    }

}
