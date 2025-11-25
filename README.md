# Package "ctw/ctw-middleware-httpexception"

[![Latest Stable Version](https://poser.pugx.org/ctw/ctw-middleware-httpexception/v/stable)](https://packagist.org/packages/ctw/ctw-middleware-httpexception)
[![GitHub Actions](https://github.com/jonathanmaron/ctw-middleware-httpexception/actions/workflows/tests.yml/badge.svg)](https://github.com/jonathanmaron/ctw-middleware-httpexception/actions/workflows/tests.yml)
[![Scrutinizer Build](https://scrutinizer-ci.com/g/jonathanmaron/ctw-middleware-httpexception/badges/build.png?b=master)](https://scrutinizer-ci.com/g/jonathanmaron/ctw-middleware-httpexception/build-status/master)
[![Scrutinizer Quality](https://scrutinizer-ci.com/g/jonathanmaron/ctw-middleware-httpexception/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/jonathanmaron/ctw-middleware-httpexception/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/jonathanmaron/ctw-middleware-httpexception/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jonathanmaron/ctw-middleware-httpexception/?branch=master)

PSR-15 middleware that catches HTTP exceptions and renders custom error pages for production environments, with automatic JSON support for API requests.

## Introduction

### Why This Library Exists

Web applications need graceful error handling that presents user-friendly error pages in production while preserving detailed error information during development. The default PHP error handling exposes sensitive information and provides poor user experience.

This middleware integrates with `ctw/ctw-http` exceptions to:

- **Catch HTTP exceptions**: Intercepts exceptions implementing `HttpExceptionInterface`
- **Render custom templates**: Uses Mezzio's template renderer for branded error pages
- **Support JSON responses**: Automatically returns RFC 7807 Problem Details for API requests
- **Respect development mode**: Re-throws exceptions in development for debugging

### Problems This Library Solves

1. **Generic error pages**: Default framework error pages are ugly and expose technical details
2. **Missing JSON errors**: API clients receive HTML error pages instead of structured JSON
3. **Development vs production**: Need different error handling behavior per environment
4. **Inconsistent responses**: Different error codes handled inconsistently across the application
5. **Security exposure**: Stack traces and internal paths leak in production errors

### Where to Use This Library

- **Production web applications**: Present branded, user-friendly error pages
- **REST APIs**: Return RFC 7807 Problem Details JSON for all HTTP errors
- **Hybrid applications**: Automatically serve HTML or JSON based on `Accept` header
- **Mezzio applications**: Integrates seamlessly with the Mezzio template system
- **Multi-environment deployments**: Different behavior in development vs production

### Design Goals

1. **Content negotiation**: Serves HTML or JSON based on client `Accept` header
2. **RFC 7807 compliance**: JSON errors follow Problem Details specification
3. **Template-based rendering**: Uses Mezzio template renderer for HTML responses
4. **Development awareness**: Re-throws exceptions when development mode is enabled
5. **Exception hierarchy**: Only catches `HttpExceptionInterface`, re-throws others

## Requirements

- PHP 8.3 or higher
- ctw/ctw-middleware ^4.0
- ctw/ctw-http ^4.0
- mezzio/mezzio-template ^2.4
- mezzio/mezzio-laminasviewrenderer ^2.2

## Installation

Install by adding the package as a [Composer](https://getcomposer.org) requirement:

```bash
composer require ctw/ctw-middleware-httpexception
```

## Usage Examples

### Basic Pipeline Registration (Mezzio)

```php
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddleware;

// In config/pipeline.php - place early in the pipeline
$app->pipe(HttpExceptionMiddleware::class);
```

### ConfigProvider Registration

```php
// config/config.php
return [
    // ...
    \Ctw\Middleware\HttpExceptionMiddleware\ConfigProvider::class,
];
```

### Throwing HTTP Exceptions

```php
use Ctw\Http\HttpException;

// In a handler or middleware
throw new HttpException\NotFoundException('Page not found');
throw new HttpException\ForbiddenException('Access denied');
throw new HttpException\InternalServerErrorException('Something went wrong');
```

### HTML Response (Browser)

When a browser requests a page and an exception occurs:

```http
HTTP/1.1 404 Not Found
Content-Type: text/html; charset=UTF-8

<!DOCTYPE html>
<html>
<head><title>404 Not Found</title></head>
<body>
  <h1>Not Found</h1>
  <p>The requested resource could not be found.</p>
</body>
</html>
```

### JSON Response (API)

When a client sends `Accept: application/json`:

```http
HTTP/1.1 404 Not Found
Content-Type: application/problem+json

{
  "type": "https://httpstatuses.com/404",
  "title": "Not Found",
  "status": 404,
  "detail": "Page not found"
}
```

### Configuration Options

```php
// config/autoload/error-handler.global.php
return [
    'error_handler' => [
        'template_http_exception' => 'error::http-exception',
        'layout' => 'layout::default',
    ],
];
```

| Option | Description | Default |
|--------|-------------|---------|
| `template_http_exception` | Template name for HTML error pages | `error::http-exception.phtml` |
| `layout` | Layout template for error pages | (none) |

### Template Variables

The error template receives:

| Variable | Type | Description |
|----------|------|-------------|
| `entity` | `HttpStatus` | HTTP status entity with code, name, phrase |
| `exception` | `HttpExceptionInterface` | The caught exception |
| `layout` | `string` | Layout template name (if configured) |

### Development Mode

In development mode (when `laminas/laminas-development-mode` is enabled), exceptions are re-thrown to allow the Whoops error handler or similar tools to display detailed debugging information.
