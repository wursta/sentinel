<?php

declare(strict_types=1);

namespace Wursta\Sentinel\Core;

/**
 * Intercepts file includes and attaches StreamFilter for PHP source rewriting.
 */
class FileStreamWrapper
{
    public const PROTOCOL = 'file';

    /** @see https://www.php.net/manual/en/stream.constants.php */
    private const STREAM_OPEN_FOR_INCLUDE = 128;

    /** @var resource|false|null */
    public $context;

    /** @var resource|false */
    private $resource = false;

    /** @var list<string> */
    private static array $blacklistPrefixes = [];

    private static bool $registered = false;

    /**
     * @param list<string> $blacklistPrefixes Absolute path prefixes to skip rewriting
     */
    public static function register(array $blacklistPrefixes = []): void
    {
        if (self::$registered) {
            return;
        }

        self::$blacklistPrefixes = $blacklistPrefixes;
        StreamFilter::register();
        ini_set('opcache.enable', '0');
        ini_set('opcache.enable_cli', '0');

        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, self::class);
        self::$registered = true;
    }

    public static function restore(): void
    {
        if (!self::$registered) {
            return;
        }

        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_restore(self::PROTOCOL);
        self::$registered = false;
    }

    public static function isRegistered(): bool
    {
        return self::$registered;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        if (substr($mode, 0, 1) === 'r' && !file_exists($path)) {
            return false;
        }

        $shouldProcess = ($options & self::STREAM_OPEN_FOR_INCLUDE) !== 0
            && $this->shouldProcess($path);

        self::restoreNative();

        if (isset($this->context) && is_resource($this->context)) {
            $this->resource = fopen($path, $mode, ($options & STREAM_USE_PATH) !== 0, $this->context);
        } else {
            $this->resource = fopen($path, $mode, ($options & STREAM_USE_PATH) !== 0);
        }

        if ($this->resource !== false && $shouldProcess) {
            stream_filter_append($this->resource, StreamFilter::FILTER_NAME, STREAM_FILTER_READ);
        }

        self::registerNative();

        return $this->resource !== false;
    }

    public function stream_close(): bool
    {
        if ($this->resource === false) {
            return true;
        }

        $result = fclose($this->resource);
        $this->resource = false;

        return $result;
    }

    public function stream_eof(): bool
    {
        if ($this->resource === false) {
            return true;
        }

        return feof($this->resource);
    }

    public function stream_flush(): bool
    {
        if ($this->resource === false) {
            return false;
        }

        return fflush($this->resource);
    }

    /**
     * @return string|false
     */
    public function stream_read(int $count)
    {
        if ($this->resource === false) {
            return false;
        }

        if ($count < 1) {
            return '';
        }

        return fread($this->resource, $count);
    }

    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        if ($this->resource === false) {
            return false;
        }

        return fseek($this->resource, $offset, $whence) === 0;
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat()
    {
        if ($this->resource === false) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);
        $uri = $meta['uri'] ?? '';

        if ($this->shouldProcess($uri)) {
            // PHP 7.4+ is sensitive to reported size after source transform.
            return false;
        }

        return fstat($this->resource);
    }

    /**
     * @return int|false
     */
    public function stream_tell()
    {
        if ($this->resource === false) {
            return false;
        }

        return ftell($this->resource);
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags)
    {
        self::restoreNative();

        if (($flags & STREAM_URL_STAT_QUIET) !== 0) {
            $result = @stat($path);
        } else {
            $result = stat($path);
        }

        self::registerNative();

        return $result === false ? false : $result;
    }

    public function dir_closedir(): bool
    {
        if ($this->resource === false) {
            return false;
        }

        closedir($this->resource);
        $this->resource = false;

        return true;
    }

    public function dir_opendir(string $path, int $options = 0): bool
    {
        self::restoreNative();

        if (isset($this->context) && is_resource($this->context)) {
            $this->resource = opendir($path, $this->context);
        } else {
            $this->resource = opendir($path);
        }

        self::registerNative();

        return $this->resource !== false;
    }

    /**
     * @return string|false
     */
    public function dir_readdir()
    {
        if ($this->resource === false) {
            return false;
        }

        return readdir($this->resource);
    }

    public function dir_rewinddir(): bool
    {
        if ($this->resource === false) {
            return false;
        }

        rewinddir($this->resource);

        return true;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        self::restoreNative();

        if (isset($this->context) && is_resource($this->context)) {
            $result = mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) !== 0, $this->context);
        } else {
            $result = mkdir($path, $mode, ($options & STREAM_MKDIR_RECURSIVE) !== 0);
        }

        self::registerNative();

        return $result;
    }

    public function rename(string $pathFrom, string $pathTo): bool
    {
        self::restoreNative();

        if (isset($this->context) && is_resource($this->context)) {
            $result = rename($pathFrom, $pathTo, $this->context);
        } else {
            $result = rename($pathFrom, $pathTo);
        }

        self::registerNative();

        return $result;
    }

    public function rmdir(string $path, int $options = 0): bool
    {
        self::restoreNative();

        if (isset($this->context) && is_resource($this->context)) {
            $result = rmdir($path, $this->context);
        } else {
            $result = rmdir($path);
        }

        self::registerNative();

        return $result;
    }

    /**
     * @return resource|false
     */
    public function stream_cast(int $castAs)
    {
        return $this->resource;
    }

    public function stream_lock(int $operation): bool
    {
        if ($this->resource === false) {
            return false;
        }

        if ($operation === 0) {
            $operation = LOCK_EX;
        }

        if ($operation < 0 || $operation > 7) {
            return false;
        }

        return flock($this->resource, $operation);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        if ($this->resource === false) {
            return false;
        }

        switch ($option) {
            case STREAM_OPTION_BLOCKING:
                return stream_set_blocking($this->resource, (bool) $arg1);
            case STREAM_OPTION_READ_TIMEOUT:
                return stream_set_timeout($this->resource, $arg1, $arg2);
            case STREAM_OPTION_WRITE_BUFFER:
                return stream_set_write_buffer($this->resource, $arg1) === 0;
            case STREAM_OPTION_READ_BUFFER:
                return stream_set_read_buffer($this->resource, $arg1) === 0;
            default:
                return false;
        }
    }

    /**
     * @return int|false
     */
    public function stream_write(string $data)
    {
        if ($this->resource === false) {
            return false;
        }

        return fwrite($this->resource, $data);
    }

    public function unlink(string $path): bool
    {
        self::restoreNative();

        if (isset($this->context) && is_resource($this->context)) {
            $result = unlink($path, $this->context);
        } else {
            $result = unlink($path);
        }

        self::registerNative();

        return $result;
    }

    /**
     * @param mixed $value
     */
    public function stream_metadata(string $path, int $option, $value): bool
    {
        self::restoreNative();
        $result = false;

        switch ($option) {
            case STREAM_META_TOUCH:
                if (!is_array($value) || !isset($value[0], $value[1])) {
                    $result = touch($path);
                } elseif (is_int($value[0]) && is_int($value[1])) {
                    $result = touch($path, $value[0], $value[1]);
                }
                break;
            case STREAM_META_OWNER_NAME:
            case STREAM_META_OWNER:
                if (is_string($value) || is_int($value)) {
                    $result = chown($path, $value);
                }
                break;
            case STREAM_META_GROUP_NAME:
            case STREAM_META_GROUP:
                if (is_string($value) || is_int($value)) {
                    $result = chgrp($path, $value);
                }
                break;
            case STREAM_META_ACCESS:
                if (is_int($value)) {
                    $result = chmod($path, $value);
                }
                break;
        }

        self::registerNative();

        return $result;
    }

    public function stream_truncate(int $newSize): bool
    {
        if ($this->resource === false || $newSize < 0) {
            return false;
        }

        return ftruncate($this->resource, $newSize);
    }

    private function shouldProcess(string $path): bool
    {
        if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
            return false;
        }

        $real = realpath($path);
        $check = $real !== false ? $real : $path;

        foreach (self::$blacklistPrefixes as $prefix) {
            if ($prefix !== '' && strpos($check, $prefix) === 0) {
                return false;
            }
        }

        return true;
    }

    private static function restoreNative(): void
    {
        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_restore(self::PROTOCOL);
    }

    private static function registerNative(): void
    {
        stream_wrapper_unregister(self::PROTOCOL);
        stream_wrapper_register(self::PROTOCOL, self::class);
        self::$registered = true;
    }
}
