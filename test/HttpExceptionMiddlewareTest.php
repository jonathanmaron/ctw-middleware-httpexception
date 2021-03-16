<?php
declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware;

use Ctw\Http\Entity\HttpStatus as Entity;
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

        [$entity, $exception] = unserialize($contents);

        $this->verifyEntity($entity, $message);
        $this->verifyException($exception, $message);
    }

    private function verifyEntity(Entity $entity, string $message): void
    {
        unset($message);

        $expected = HttpStatus::STATUS_BAD_REQUEST;
        $this->assertSame($expected, $entity->statusCode);

        $expected = 'Bad Request';
        $this->assertSame($expected, $entity->name);

        $expected = 'The request cannot be fulfilled due to bad syntax.';
        $this->assertSame($expected, $entity->phrase);

        $expected = 'Ctw\\Http\\HttpException\\BadRequestException';
        $this->assertSame($expected, $entity->exception);

        $expected = 'https://httpstatuses.com/400';
        $this->assertSame($expected, $entity->url);
    }

    private function verifyException(HttpException\BadRequestException $exception, string $message): void
    {
        $expected = HttpStatus::STATUS_BAD_REQUEST;
        $this->assertSame($expected, $exception->getStatusCode());

        $expected = $message;
        $this->assertSame($expected, $exception->getMessage());
    }
}
