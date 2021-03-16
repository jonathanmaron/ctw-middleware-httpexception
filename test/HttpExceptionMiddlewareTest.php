<?php
declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware;

use Ctw\Http\HttpException;
use Ctw\Http\HttpStatus;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddleware;
use Mezzio\LaminasView\LaminasViewRenderer as TemplateRenderer;
use Middlewares\Utils\Dispatcher;
use Psr\Http\Message\ResponseInterface;

class HttpExceptionMiddlewareTest extends AbstractCase
{
    public function testHttpExceptionMiddleware(): void
    {
        $message = hash('sha256', (string) microtime(true));

        $template  = new TemplateRenderer();
        $path      = realpath(__DIR__ . '/TestAsset/error');
        $namespace = 'error';
        $template->addPath($path, $namespace);

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);

        $stack = [
            $middleware,
            function () use ($message): ResponseInterface {
                throw new HttpException\BadRequestException($message);
            },
        ];

        $response = Dispatcher::run($stack);
        $contents = $response->getBody()->getContents();

        [$entity, $exception] = json_decode($contents, true);

        $this->verifyEntity($entity, $message);
        $this->verifyException($exception, $message);
    }

    private function verifyEntity(array $array, string $message): void
    {
        unset($message);

        $statusCode = HttpStatus::STATUS_BAD_REQUEST;

        $expected = $statusCode;
        $this->assertSame($expected, $array['statusCode']);

        $expected = 'Bad Request';
        $this->assertSame($expected, $array['name']);

        $expected = 'The request cannot be fulfilled due to bad syntax.';
        $this->assertSame($expected, $array['phrase']);

        $expected = HttpException\BadRequestException::class;
        $this->assertSame($expected, $array['exception']);

        $expected = sprintf('https://httpstatuses.com/%d', $statusCode);
        $this->assertSame($expected, $array['url']);
    }

    private function verifyException(array $array, string $message): void
    {
        $expected = HttpStatus::STATUS_BAD_REQUEST;
        $this->assertSame($expected, $array['statusCode']);

        $expected = $message;
        $this->assertSame($expected, $array['message']);
    }
}
