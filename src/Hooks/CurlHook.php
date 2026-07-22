<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Hooks;

use Wursta\Sentinel\Core\HookInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class CurlHook implements HookInterface
{
    private const TARGET_CLASS = '\\Wursta\\Sentinel\\Hooks\\CurlHook';

    /** @var array<string, string> */
    private static array $patterns = [
        '/(?<![a-zA-Z0-9_\\\\])\\\\?curl_init\s*\(/i' => self::TARGET_CLASS . '::curl_init(',
        '/(?<![a-zA-Z0-9_\\\\])\\\\?curl_exec\s*\(/i' => self::TARGET_CLASS . '::curl_exec(',
        '/(?<![a-zA-Z0-9_\\\\])\\\\?curl_setopt\s*\(/i' => self::TARGET_CLASS . '::curl_setopt(',
        '/(?<![a-zA-Z0-9_\\\\])\\\\?curl_setopt_array\s*\(/i' => self::TARGET_CLASS . '::curl_setopt_array(',
        '/(?<![a-zA-Z0-9_\\\\])\\\\?curl_close\s*\(/i' => self::TARGET_CLASS . '::curl_close(',
    ];

    /** @var array<int, array<int, mixed>> */
    private static array $options = [];

    private static ?LoggerInterface $logger = null;

    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    public static function reset(): void
    {
        self::$options = [];
        self::$logger = null;
    }

    public function transformCode(string $code): string
    {
        $transformed = preg_replace(array_keys(self::$patterns), array_values(self::$patterns), $code);

        return is_string($transformed) ? $transformed : $code;
    }

    /**
     * @param string|null $url
     *
     * @return resource|false
     */
    public static function curl_init($url = null)
    {
        $handle = $url === null ? \curl_init() : \curl_init($url);
        if ($handle !== false) {
            self::$options[self::handleId($handle)] = [];
            if ($url !== null) {
                self::$options[self::handleId($handle)][CURLOPT_URL] = $url;
            }
        }

        return $handle;
    }

    /**
     * @param resource $ch
     * @param mixed    $value
     */
    public static function curl_setopt($ch, int $option, $value): bool
    {
        $id = self::handleId($ch);
        if (!isset(self::$options[$id])) {
            self::$options[$id] = [];
        }
        self::$options[$id][$option] = $value;

        return \curl_setopt($ch, $option, $value);
    }

    /**
     * @param resource          $ch
     * @param array<int, mixed> $options
     */
    public static function curl_setopt_array($ch, array $options): bool
    {
        $id = self::handleId($ch);
        if (!isset(self::$options[$id])) {
            self::$options[$id] = [];
        }
        self::$options[$id] = $options + self::$options[$id];

        return \curl_setopt_array($ch, $options);
    }

    /**
     * @param resource $ch
     *
     * @return bool|string
     */
    public static function curl_exec($ch)
    {
        $id = self::handleId($ch);
        $options = self::$options[$id] ?? [];
        self::logRequest($options);

        return \curl_exec($ch);
    }

    /**
     * @param resource $ch
     */
    public static function curl_close($ch): void
    {
        unset(self::$options[self::handleId($ch)]);
        \curl_close($ch);
    }

    /**
     * @param array<int, mixed> $options
     *
     * @return array{type: string, method: string, url: string, headers: list<string>, body: string, timestamp: float}
     */
    public static function buildRequestLog(array $options): array
    {
        $url = '';
        if (isset($options[CURLOPT_URL]) && (is_string($options[CURLOPT_URL]) || is_numeric($options[CURLOPT_URL]))) {
            $url = (string) $options[CURLOPT_URL];
        }

        return [
            'type' => 'curl',
            'method' => self::resolveMethod($options),
            'url' => $url,
            'headers' => self::normalizeHeaders($options[CURLOPT_HTTPHEADER] ?? []),
            'body' => self::resolveBody($options),
            'timestamp' => microtime(true),
        ];
    }

    /**
     * @param resource|object $ch
     */
    private static function handleId($ch): int
    {
        if (is_object($ch)) {
            return spl_object_id($ch);
        }

        return (int) $ch;
    }

    /**
     * @param array<int, mixed> $options
     */
    private static function logRequest(array $options): void
    {
        $logger = self::$logger ?? new NullLogger();
        $payload = self::buildRequestLog($options);
        $logger->info('curl', $payload);
    }

    /**
     * @param array<int, mixed> $options
     */
    private static function resolveMethod(array $options): string
    {
        if (!empty($options[CURLOPT_CUSTOMREQUEST])) {
            $custom = $options[CURLOPT_CUSTOMREQUEST];
            if (is_string($custom) || is_numeric($custom)) {
                return strtoupper((string) $custom);
            }
        }

        if (!empty($options[CURLOPT_POST]) || array_key_exists(CURLOPT_POSTFIELDS, $options)) {
            return 'POST';
        }

        if (!empty($options[CURLOPT_PUT])) {
            return 'PUT';
        }

        if (!empty($options[CURLOPT_NOBODY])) {
            return 'HEAD';
        }

        return 'GET';
    }

    /**
     * @param mixed $headers
     *
     * @return list<string>
     */
    private static function normalizeHeaders($headers): array
    {
        if (!is_array($headers)) {
            return [];
        }

        $normalized = [];
        foreach ($headers as $header) {
            if (is_string($header) && $header !== '') {
                $normalized[] = $header;
            }
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $options
     */
    private static function resolveBody(array $options): string
    {
        if (!array_key_exists(CURLOPT_POSTFIELDS, $options)) {
            return '';
        }

        $body = $options[CURLOPT_POSTFIELDS];
        if (is_array($body)) {
            return http_build_query($body);
        }

        if (is_string($body)) {
            return $body;
        }

        if (is_int($body) || is_float($body) || is_bool($body)) {
            return (string) $body;
        }

        return '';
    }
}
