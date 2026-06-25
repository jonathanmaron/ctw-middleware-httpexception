# PHP 8.5.7 Upgrade — `ctw/ctw-middleware-httpexception`

- **Branch:** `php85` (cut from `master`)
- **Runtime:** PHP 8.3.31 → **8.5.7**
- **Date:** 2026-06-25

This is a **TODO list** of the changes required for this package to run cleanly
under PHP 8.5.7. Boxes are intentionally left unchecked.

---

## ✅ Applied on `php85` (diactoros blocker resolved — package now fully green)

> Supersedes the "❌ FAILS" analysis below.

`composer.json` changes:

- [x] `laminas/laminas-diactoros` `^2.5` → **`^3.0`** (installs 3.8.0).
- [x] `psr/http-message` `^1.0` → **`^1.1 || ^2.0`**.
- [x] `ctw/ctw-middleware` `^4.0` → **`dev-php85`** (diactoros 3 / middlewares-utils 4 / servicemanager 4.5).
- [x] `ctw/ctw-http` `^4.0` → **`dev-php85`** (pulls the explicit-nullable
  `$previous` fix, clearing the two `ctw/ctw-http` deprecations).
- [x] `mezzio/mezzio-laminasviewrenderer ^2.2` / `mezzio/mezzio-template ^2.4`
  **left as-is** — `composer update -W` resolves PHP 8.5-compatible releases
  within range (2.19.0 / 2.13.0); no major bump needed.

**Result:** `composer update -W` is green and `phpunit --no-coverage` reports
**3 tests, 15 assertions, 0 deprecations**.

Residual: only the shared PHPStan `missingType.*` unmatched-ignore (§3, owned by
`ctw/ctw-qa`). Note `laminas/laminas-json` is flagged abandoned (pre-existing
transitive dep). Re-tag the `ctw/*` deps to stable releases before merge.

> ⚠️ **Heaviest dependency surface in the set** — declares `laminas-diactoros`
> *and* two `mezzio/*` packages directly. Expect to bump several constraints.

Detection commands used:

```bash
composer update -W
php vendor/bin/phpunit --no-coverage --display-deprecations --display-warnings --display-notices --display-errors
composer rector      # rector --dry-run
composer phpstan
```

---

## 1. `composer update -W` — ❌ FAILS (hard blocker, direct + transitive)

```
Problem 1
  - Root composer.json requires laminas/laminas-diactoros ^2.5
  - laminas/laminas-diactoros[2.5 ... 2.26] require php ^7.3 ... ~8.3.0
    -> your php version (8.5.7) does not satisfy that requirement.
```

`laminas/laminas-diactoros` 2.x caps PHP at `~8.3.0`. This package requires it
**directly** (`^2.5`) and transitively via `ctw/ctw-middleware ^4.0`.

- [ ] **`composer.json`** — bump `laminas/laminas-diactoros` `^2.5` → **`^3.0`**.
- [ ] **`composer.json`** — bump the Mezzio constraints; the current
  `mezzio/mezzio-laminasviewrenderer ^2.2` and `mezzio/mezzio-template ^2.4`
  (and the 2.x Mezzio line generally) predate PHP 8.4/8.5 support. Move to the
  current major (`mezzio/mezzio-laminasviewrenderer ^3.0`,
  `mezzio/mezzio-template ^3.0`) and re-resolve. **These caps are hidden right
  now** — composer aborts on Diactoros first, so re-run `composer update -W`
  after the Diactoros bump to surface the real Mezzio requirements.
- [ ] **`composer.json`** — `psr/http-message ^1.0` likely needs widening to
  `^1.1 || ^2.0` for Diactoros 3 / current Mezzio.
- [ ] **Blocked on `ctw/ctw-middleware`** and **`ctw/ctw-http`** — bump those
  constraints once their PHP 8.5 releases are published (see their
  `dev-php85/UPDATE.md`).

> §2 was captured against the existing (master) lockfile because the update
> aborts. Additional Mezzio/laminas-view deprecations may appear once the tree
> actually updates — re-run detection after §1.

---

## 2. PHP 8.5 runtime deprecations (current deps)

The "implicitly nullable parameter" deprecation, all in **third-party** vendor
code — **no first-party `src/` change required here:**

| Location | Method / parameter |
| --- | --- |
| `vendor/ctw/ctw-http/src/HttpException/AbstractException.php:16` | `AbstractException::__construct()` `$previous` |
| `vendor/ctw/ctw-http/src/HttpException/HttpExceptionInterface.php:10` | `HttpExceptionInterface::__construct()` `$previous` |
| `vendor/middlewares/utils/src/Dispatcher.php:21` | `Dispatcher::run()` `$request` |
| `vendor/middlewares/utils/src/Factory.php:88` | `Factory::createUploadedFile()` `$size` |
| `vendor/middlewares/utils/src/Factory.php:90` | `Factory::createUploadedFile()` `$filename` |
| `vendor/middlewares/utils/src/Factory.php:91` | `Factory::createUploadedFile()` `$mediaType` |
| `vendor/middlewares/utils/src/CallableHandler.php:25` | `CallableHandler::__construct()` `$responseFactory` |

- [ ] The `ctw/ctw-http` rows are fixed upstream in `ctw/ctw-http`
  (`ctw-http/dev-php85/UPDATE.md` §2) and clear here once re-published.
- [ ] The `middlewares/utils` rows clear by updating that dependency once §1 is
  unblocked.

---

## 3. QA tooling issues

- [ ] **PHPStan unmatched ignore pattern** (`missingType.generics`) — fix
  centrally in **`ctw/ctw-qa`** (`ctw-qa/dev-php85/UPDATE.md` §3). PHPStan
  currently reports **1 error**, this spurious one only.

---

## 4. Notes (non-blocking)

- Run locally with `--no-coverage` (no Xdebug/PCOV here). Not a PHP 8.5 issue.

---

## 5. Verification snapshot (current state on `php85`)

| Check | Result |
| --- | --- |
| `composer update -W` | ❌ fails — direct + transitive `laminas-diactoros` 2.x (§1); Mezzio caps still hidden |
| PHPUnit (`--no-coverage`, stale deps) | 3 tests, 15 assertions, **7 deprecations** (2× `ctw/ctw-http` + 5× `middlewares/utils`, §2) |
| Rector (dry-run) | ✅ no changes proposed |
| PHPStan | ❌ 1 error (shared unmatched-ignore, §3) |

**First-party work needed here:** the `composer.json` constraint bumps in §1
(Diactoros + Mezzio + psr/http-message). No `src/` edits identified yet — re-run
detection after the dependency tree updates.
