# Sentinel

[![CI](https://github.com/wursta/sentinel/actions/workflows/ci.yml/badge.svg)](https://github.com/wursta/sentinel/actions/workflows/ci.yml)
[![Codecov](https://codecov.io/github/wursta/sentinel/branch/main/graph/badge.svg)](https://codecov.io/github/wursta/sentinel)

Intercepts and logs HTTP requests made via cURL at runtime.

## Installation

```bash
composer require wursta/sentinel
```

## Usage

```php
use Wursta\Sentinel\Manager;

$manager = Manager::getInstance();
$manager->enable('/tmp/interceptor.log'); // optional log file

// Code loaded after enable() is automatically rewritten:
require __DIR__ . '/client_that_uses_curl.php';

// Access logged requests
$logger = $manager->getLogger();
$records = $logger->getRecordsByMessage('curl');

$manager->disable();
```

## How It Works

Sentinel intercepts cURL calls by rewriting PHP source code at include-time:

1. Registers a custom `file://` stream wrapper
2. Appends a source filter to PHP files during `include`/`require`
3. Rewrites `curl_init`, `curl_setopt`, `curl_exec`, `curl_close` calls
4. Logs request details on `curl_exec`

The library itself is blacklisted from rewriting.

## Supported Functions

- `curl_init()` — captures URL and options
- `curl_setopt()` / `curl_setopt_array()` — tracks all options
- `curl_exec()` — logs full request (method, URL, headers, body)
- `curl_close()` — cleans up tracked options

## Log Format

Each intercepted request produces a JSON log entry:

```json
{
  "level": "info",
  "message": "curl",
  "context": {
    "type": "curl",
    "method": "POST",
    "url": "https://api.example.com/endpoint",
    "headers": ["Content-Type: application/json", "X-Api-Key: ***"],
    "body": "{\"key\":\"value\"}",
    "timestamp": 1721678900.1234
  }
}
```

## Limitations

- Only `curl_init`/`curl_setopt`/`curl_setopt_array`/`curl_exec`/`curl_close` (no `curl_multi_*`)
- Files already loaded before `enable()` are not rewritten
- `eval()`, dynamic calls, and OOP `$ch->exec()` are not rewritten
- Opcode cache is disabled during interception
- Library `src/` paths are blacklisted

## Requirements

- PHP >= 7.4
- ext-curl, ext-json
- psr/log ^1.1 || ^2 || ^3
