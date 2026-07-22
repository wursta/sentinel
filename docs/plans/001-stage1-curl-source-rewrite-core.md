---
title: "Этап 1: ядро source-rewrite и CurlHook"
status: planned
todos:
  - id: composer-tooling
    content: "Обновить composer.json (php, ext-curl, psr/log, autoload-dev), добавить phpunit.xml.dist"
    status: pending
  - id: core-classes
    content: "Реализовать HookInterface, HookManager, StreamFilter, FileStreamWrapper"
    status: pending
  - id: curl-hook-logger-manager
    content: "Реализовать CurlHook (transform + runtime), Logger (PSR-3), Manager"
    status: pending
  - id: unit-tests
    content: "Unit-тесты HookManager, StreamFilter, CurlHook"
    status: pending
  - id: integration-tests
    content: "Integration-тесты с fixtures и локальным HTTP stub (GET/POST/PUT/DELETE)"
    status: pending
  - id: agents-md-check
    content: "Написать AGENTS.md и прогнать make check"
    status: pending
---

# Этап 1: ядро source-rewrite и CurlHook

## Контекст

Репозиторий сейчас — scaffolding: пустые [`src/`](../../src/), [`tests/`](../../tests/), namespace `Wursta\Sentinel`, PHP 7.4 в Docker ([`.docker.loc/images/php/Dockerfile`](../../.docker.loc/images/php/Dockerfile)), `make check` через docker compose.

**Механизм перехвата (не сетевой фильтр):**

```mermaid
flowchart LR
  enable["Manager::enable"] --> wrap["FileStreamWrapper registers file://"]
  wrap --> include["include/require .php"]
  include --> filter["StreamFilter buffers source"]
  filter --> hooks["HookManager::transformCode"]
  hooks --> curlHook["CurlHook rewrites curl_*"]
  curlHook --> runtime["CurlHook::curl_exec logs then calls \\curl_exec"]
```

## Структура файлов

```
src/
  Manager.php
  Logger.php
  Core/
    HookInterface.php
    HookManager.php
    StreamFilter.php
    FileStreamWrapper.php
  Hooks/
    CurlHook.php
tests/
  Unit/Core/StreamFilterTest.php
  Unit/Core/HookManagerTest.php
  Unit/Hooks/CurlHookTest.php
  Integration/CurlInterceptTest.php
  fixtures/curl_get.php
  fixtures/curl_post.php
  ...
AGENTS.md
phpunit.xml.dist
```

Namespace: `Wursta\Sentinel\...` (не `YourVendor`).

## Ключевые классы

### [`src/Core/HookInterface.php`](../../src/Core/HookInterface.php)

Для source-rewrite интерфейс другой, чем в исходном эскизе:

```php
interface HookInterface
{
    public function transformCode(string $code): string;
}
```

### [`src/Core/HookManager.php`](../../src/Core/HookManager.php)

- `registerHook(HookInterface $hook): void`
- `transformCode(string $code): string` — последовательно применяет все хуки (не выбор «одного по context»)
- `reset(): void` — для тестов/disable

### [`src/Core/StreamFilter.php`](../../src/Core/StreamFilter.php)

Единый `php_user_filter`:
- буферизует бакеты до `$closing`
- вызывает `HookManager::transformCode($buffer)`
- отдаёт переписанный код через `stream_bucket_new` + `PSFS_PASS_ON`
- **не** читает `stream_context` для определения cURL

### [`src/Core/FileStreamWrapper.php`](../../src/Core/FileStreamWrapper.php)

Полноценный `file://` wrapper (паттерн sanprojects/PHP-VCR):
- `register()` / `restore()`: `stream_wrapper_unregister` + `register` / `restore`
- при `stream_open` с `STREAM_OPEN_FOR_INCLUDE` и `.php` — `stream_filter_append(..., STREAM_FILTER_READ)`
- временный `restore()` вокруг реальных `fopen`/`stat`/… чтобы избежать рекурсии
- `stream_stat()` для обрабатываемых include возвращает `false` (иначе PHP 7.4+ ломается на размере файла после transform)
- blacklist путей библиотеки (`src/`), чтобы не переписывать собственный код

### [`src/Hooks/CurlHook.php`](../../src/Hooks/CurlHook.php)

Две роли:
1. **`transformCode`**: `preg_replace` как в PHP-VCR, только 5 функций:
   - `curl_init` / `curl_setopt` / `curl_setopt_array` / `curl_exec` / `curl_close`
   - паттерн с negative lookbehind `(?<![a-zA-Z0-9_\\\\])`, замена на `\Wursta\Sentinel\Hooks\CurlHook::curl_*(`
2. **Static runtime wrappers**:
   - хранят опции по id handle (`(int)$ch` для resource PHP 7.4, `spl_object_id` для object PHP 8+)
   - `curl_setopt` / `curl_setopt_array` — запоминают опции, вызывают `\curl_setopt` / `\curl_setopt_array`
   - `curl_exec` — собирает method/url/headers/body из опций, логирует через `Logger`, затем `\curl_exec`
   - `curl_close` — чистит state, вызывает `\curl_close`
   - method: `CURLOPT_CUSTOMREQUEST` → иначе POST если `CURLOPT_POST`/`POSTFIELDS` → иначе PUT/DELETE по custom → иначе GET

Логирование на этапе 1 — **структурированный** запись (удобно для тестов), не только CLI-строка:
`{type, method, url, headers, body, timestamp}` → `Logger::info` / канал `curl`.

Инъекция логгера: `CurlHook::setLogger(LoggerInterface $logger)` (static, т.к. вызовы после rewrite статические).

### [`src/Logger.php`](../../src/Logger.php)

- `implements Psr\Log\LoggerInterface` (добавить `psr/log` в `require`)
- запись в файл (если задан) и/или in-memory buffer `getRecords()` / `clear()` для unit/integration тестов
- PHP 7.4-совместимо (без `new` в default params)

### [`src/Manager.php`](../../src/Manager.php)

Singleton facade:
- `enable(?string $logFile = null): void` — `ini_set('opcache.enable', '0')` + `opcache.enable_cli`; регистрирует filter; регистрирует `CurlHook` в `HookManager`; `FileStreamWrapper::register()`
- `disable(): void` — restore wrapper, reset hooks, clear enabled flag
- `isEnabled()`, `getLogger()`

## Composer / tooling

Обновить [`composer.json`](../../composer.json):
- `"require": { "php": ">=7.4", "ext-curl": "*", "psr/log": "^1.1 || ^2 || ^3" }`
- `"autoload-dev": { "psr-4": { "Wursta\\Sentinel\\Tests\\": "tests/" } }`

Добавить [`phpunit.xml.dist`](../../phpunit.xml.dist) (bootstrap `vendor/autoload.php`, testsuite `tests`).

Создать [`AGENTS.md`](../../AGENTS.md): архитектура source-rewrite, ограничения (bootstrap до include целевого кода, opcache off, нет `curl_multi_*` / `eval` / уже загруженных файлов), как запускать `make check`.

## Тесты

**Unit**
- `HookManagerTest` — register, цепочка transform, reset
- `StreamFilterTest` — регистрация фильтра + transform через temp stream / прямой вызов логики transform (без полного wrapper, где возможно)
- `CurlHookTest` — `transformCode` заменяет нужные вызовы и не трогает `my_curl_exec`; unit на сбор method/url из опций через reflection/package-visible helper; logging в in-memory logger

**Integration** ([`tests/Integration/CurlInterceptTest.php`](../../tests/Integration/CurlInterceptTest.php))
- `Manager::enable()` **до** `include` фикстур из `tests/fixtures/`
- фикстуры делают реальный HTTP к локальному built-in PHP server (`setUpBeforeClass` на `127.0.0.1`, router отвечает 200) — без внешних зависимостей вроде httpbin
- кейсы GET / POST / PUT / DELETE: assert в логах method, url, headers, body
- после теста `Manager::disable()`

## Критерии готовности

- `make check` (cs-check + analyse + test) без ошибок
- Перехват работает только для кода, загруженного **после** `enable()` через include
- Нет зависимости от `sanprojects/interceptor` / php-vcr — только `psr/log` + ext-curl
