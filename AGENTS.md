# Interceptor — agent notes

PHP library that intercepts and logs HTTP requests made via cURL.

## Architecture (stage 1)

Interception does **not** attach filters to cURL handles. libcurl traffic never goes through PHP stream filters.

Instead, at include-time:

1. `Manager::enable()` registers `FileStreamWrapper` for the `file://` protocol and `StreamFilter` (`wursta.sentinel`).
2. On `include` / `require` of `.php` files, the wrapper appends the stream filter.
3. `StreamFilter` buffers source and runs `HookManager::transformCode()`.
4. `CurlHook::transformCode()` rewrites `curl_init` / `curl_setopt` / `curl_setopt_array` / `curl_exec` / `curl_close` to `CurlHook::*` wrappers.
5. Wrappers record options, log a structured request on `curl_exec`, then call the real `\curl_*` functions.

```
Manager::enable
  → FileStreamWrapper (file://)
  → include app code
  → StreamFilter rewrites source
  → CurlHook::curl_exec logs → \curl_exec
```

### Core types

| Class | Role |
|-------|------|
| `Wursta\Sentinel\Manager` | Facade: enable / disable / logger |
| `Core\FileStreamWrapper` | Hijacks `file://` for include rewriting |
| `Core\StreamFilter` | `php_user_filter` that transforms source |
| `Core\HookManager` | Ordered list of code transformers |
| `Core\HookInterface` | `transformCode(string): string` |
| `Hooks\CurlHook` | cURL rewrite + runtime logging |
| `Logger` | PSR-3 logger with in-memory records (+ optional file) |

## Usage

```php
use Wursta\Sentinel\Manager;

$manager = Manager::getInstance();
$manager->enable('/tmp/interceptor.log'); // optional log file

// Code loaded AFTER enable() is rewritten:
require __DIR__ . '/client_that_uses_curl.php';

$manager->disable();
```

Bootstrap before application autoload/includes (e.g. `auto_prepend_file` or the first lines of a front controller).

## Limits (stage 1)

- Only `curl_init`, `curl_setopt`, `curl_setopt_array`, `curl_exec`, `curl_close` (no `curl_multi_*`).
- Files already loaded before `enable()` are not rewritten.
- `eval()`, dynamic `call_user_func('curl_exec', ...)`, and OOP `$ch->exec()` are not rewritten.
- Opcache is disabled while interception is active.
- Library `src/` paths are blacklisted from rewriting.

## Commands

All checks run in Docker via Makefile:

- `make test` — PHPUnit
- `make cs-check` — PHP CS Fixer (check)
- `make fix` — PHP CS Fixer (fix)
- `make analyse` — PHPStan
- `make check` — cs-check + analyse + test
- `make composer-install` — `composer install` in container

## Dependencies

- Runtime: `php >= 7.4`, `ext-curl`, `ext-json`, `psr/log`
- No logic libraries (e.g. not `sanprojects/interceptor` / php-vcr)
- Dev: PHPUnit, PHPStan, PHP CS Fixer
