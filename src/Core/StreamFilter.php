<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Core;

use php_user_filter;

/**
 * Rewrites PHP source at include-time via HookManager.
 * Does not inspect network traffic or stream contexts.
 */
class StreamFilter extends php_user_filter
{
    public const FILTER_NAME = 'wursta.sentinel';

    private string $buffer = '';

    public function onCreate(): bool
    {
        $this->buffer = '';

        return true;
    }

    /**
     * @param resource $in
     * @param resource $out
     * @param int      $consumed
     * @param-out int  $consumed
     */
    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $data = $bucket->data;
            $datalen = $bucket->datalen;
            if (!is_string($data) || !is_int($datalen)) {
                return PSFS_ERR_FATAL;
            }
            $this->buffer .= $data;
            $consumed = (int) $consumed + $datalen;
        }

        if (!$closing) {
            return PSFS_FEED_ME;
        }

        $transformed = HookManager::transformCode($this->buffer);
        $this->buffer = '';

        $bufferHandle = fopen('php://memory', 'wb+');
        if ($bufferHandle === false) {
            return PSFS_ERR_FATAL;
        }

        $outBucket = stream_bucket_new($bufferHandle, $transformed);
        fclose($bufferHandle);
        stream_bucket_append($out, $outBucket);

        return PSFS_PASS_ON;
    }

    public static function register(): bool
    {
        if (in_array(self::FILTER_NAME, stream_get_filters(), true)) {
            return true;
        }

        return stream_filter_register(self::FILTER_NAME, self::class);
    }
}
