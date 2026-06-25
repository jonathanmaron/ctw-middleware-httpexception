# PHP 8.5.7 Migration — `ctw/ctw-middleware-httpexception`

- **Branch:** `php85` (cut from `master`)
- **Runtime:** PHP 8.3.31 → **8.5.7**
- **PHPUnit:** 12 → **13.2.1**
- **Date:** 2026-06-25
- **Status:** ✅ done

This is the completed migration checklist for running this package cleanly under
PHP 8.5.7. Every box is checked and verified against a fresh audit (see
**Final audit** below). This package has the **heaviest dependency surface** in
the set — it declares `laminas/laminas-diactoros` and two `mezzio/*` packages
directly, and pins both `ctw/ctw-http` and `ctw/ctw-middleware` to `dev-php85`.

---

## Audit checklist

### `composer.json` — dependency resolution

- [x] **(fatal) `composer update -W` aborts** — `laminas/laminas-diactoros`
  2.x caps PHP at `~8.3.0`, so the solver rejects PHP 8.5.7. This package
  declares `laminas/laminas-diactoros` **directly** (`^2.5`) **and** pulls it
  transitively via `ctw/ctw-middleware ^4.0`.

  ```
  Problem 1
    - Root composer.json requires laminas/laminas-diactoros ^2.5
    - laminas/laminas-diactoros[2.5 ... 2.26] require php ^7.3 ... ~8.3.0
      -> your php version (8.5.7) does not satisfy that requirement.
  ```

  **Fix:** bump the **direct** constraint `laminas/laminas-diactoros` `^2.5` →
  **`^3.0`** (installs 3.8.0); widen `psr/http-message` `^1.0` →
  **`^1.1 || ^2.0`** (installs 2.0) for diactoros 3 / current Mezzio; require
  `ctw/ctw-middleware: dev-php85` (diactoros 3 / `middlewares/utils` 4) and
  `ctw/ctw-http: dev-php85` (see below).

### Vendor runtime deprecations (`ctw/ctw-http`)

The two "implicitly nullable parameter" deprecations on the `$previous`
constructor parameter come from `vendor/ctw/ctw-http` — fixed upstream on its
own `dev-php85` branch and cleared here by pinning that branch. **No first-party
`src/` change is required.**

- [x] **(deprecation) `vendor/ctw/ctw-http/src/HttpException/AbstractException.php:16`** —
  `AbstractException::__construct()` `$previous` implicitly nullable.
  **Fix:** cleared by pinning `ctw/ctw-http: dev-php85` (explicit `?type` on
  `$previous`).
- [x] **(deprecation) `vendor/ctw/ctw-http/src/HttpException/HttpExceptionInterface.php:10`** —
  `HttpExceptionInterface::__construct()` `$previous` implicitly nullable.
  **Fix:** cleared by pinning `ctw/ctw-http: dev-php85`.

### Vendor runtime deprecations (`middlewares/utils`)

All five "implicitly nullable parameter" deprecations originate in the
third-party `middlewares/utils` dependency — **no first-party `src/` change is
required.**

- [x] **(deprecation) `vendor/middlewares/utils/src/Dispatcher.php:21`** —
  `Dispatcher::run()` `$request` implicitly nullable.
  **Fix:** cleared by the `middlewares/utils` → `^4` bump (v4 declares explicit
  `?type` parameters); pulled in via `ctw/ctw-middleware: dev-php85`.
- [x] **(deprecation) `vendor/middlewares/utils/src/Factory.php:88`** —
  `Factory::createUploadedFile()` `$size` implicitly nullable.
  **Fix:** cleared by the `middlewares/utils` → `^4` bump.
- [x] **(deprecation) `vendor/middlewares/utils/src/Factory.php:90`** —
  `Factory::createUploadedFile()` `$filename` implicitly nullable.
  **Fix:** cleared by the `middlewares/utils` → `^4` bump.
- [x] **(deprecation) `vendor/middlewares/utils/src/Factory.php:91`** —
  `Factory::createUploadedFile()` `$mediaType` implicitly nullable.
  **Fix:** cleared by the `middlewares/utils` → `^4` bump.
- [x] **(deprecation) `vendor/middlewares/utils/src/CallableHandler.php:25`** —
  `CallableHandler::__construct()` `$responseFactory` implicitly nullable.
  **Fix:** cleared by the `middlewares/utils` → `^4` bump.

### Mezzio constraints

- [x] **(warning) `mezzio/mezzio-laminasviewrenderer ^2.2` /
  `mezzio/mezzio-template ^2.4`** — risk that the declared 2.x lines predate PHP
  8.5 support and force a major bump.
  **Fix:** none needed — `composer update -W` resolves PHP 8.5-compatible
  releases **within range** (2.19.0 and 2.13.0). Constraints left as-is.

### PHPUnit 13

- [x] **(tooling) PHPUnit `^12` → `^13`** — bumped for PHP 8.5 (installs 13.2.1).
  The existing tests use no expectation-free `createMock()` doubles, so no
  `createStub()` migration was required.

---

## composer.json & CI

- [x] **require `php`** — `^8.3` → **`^8.5`**. Drops PHP 8.3/8.4 from the
  supported range.
- [x] **`laminas/laminas-diactoros`** (direct) — `^2.5` → **`^3.0`** (installs
  3.8.0). Direct half of the diactoros blocker fix.
- [x] **`psr/http-message`** — `^1.0` → **`^1.1 || ^2.0`** (installs 2.0).
- [x] **`ctw/ctw-http`** — `^4.0` → **`dev-php85`**. Clears the two `$previous`
  deprecations from `vendor/ctw/ctw-http`.
- [x] **`ctw/ctw-middleware`** — `^4.0` → **`dev-php85`**. Bumps diactoros →
  3.8.0 and `middlewares/utils` → 4.0.2.
- [x] **`mezzio/mezzio-laminasviewrenderer ^2.2` / `mezzio/mezzio-template ^2.4`** —
  left as-is; in-range PHP 8.5-compatible releases resolve (2.19.0 / 2.13.0).
- [x] **`ctw/ctw-qa`** (dev) — `^5.0` → **`dev-php85`**. PHP 8.5-compatible QA
  toolchain.
- [x] **`phpunit/phpunit`** (dev) — `^12.0` → **`^13.0`** (installs 13.2.1).
- [x] **`.github/workflows/tests.yml`** — matrix pinned to **PHP 8.5 only**
  (`php: [ '8.5' ]`).

> Note: `laminas/laminas-json` is flagged abandoned upstream — it is a
> pre-existing transitive dependency via Mezzio, not a migration blocker.
>
> Before merge: re-tag the `ctw/*` deps to stable releases and replace the
> `dev-php85` pins.

---

## Final audit (PHP 8.5.7)

- [x] **`php -v`** → PHP **8.5.7** (cli).
- [x] **`composer update -W`** → clean; no security advisories (the
  `laminas/laminas-json` abandoned notice is informational only). Resolves
  `laminas/laminas-diactoros 3.8.0`, `psr/http-message 2.0`,
  `middlewares/utils 4.0.2`, `mezzio/mezzio-laminasviewrenderer 2.19.0`,
  `mezzio/mezzio-template 2.13.0`, `ctw/ctw-http dev-php85`,
  `ctw/ctw-middleware dev-php85`, `phpunit/phpunit 13.2.1`.
- [x] **PHPUnit** (`--no-coverage --display-deprecations --display-warnings
  --display-notices --display-errors`) → **OK (3 tests, 15 assertions)**, 0
  deprecations / warnings / notices.
- [x] **PHPStan** → no issues found (analyzes `src` and `test`).
