<?php
declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware;

use Ctw\Http\HttpException;
use Ctw\Http\HttpStatus;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddleware;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddlewareFactory;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\LaminasView\LaminasViewRenderer as TemplateRenderer;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;

class HttpExceptionMiddlewareTest extends AbstractCase
{
    public function testHttpExceptionMiddlewareViaHtml(): void
    {
        $message = hash('sha256', (string) microtime(true));

        $stack = [
            $this->getInstance(),
            static function () use ($message): never {
                throw new HttpException\BadRequestException($message);
            },
        ];

        $response = Dispatcher::run($stack);
        $body     = $response->getBody();
        $contents = $body->getContents();

        $array = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($array));

        [$entity, $exception] = $array;
        assert(is_array($entity));
        assert(is_array($exception));

        $this->verifyEntity($entity, $message);
        $this->verifyException($exception, $message);
    }

    public function testHttpExceptionMiddlewareViaProblemJson(): void
    {
        $message = hash('sha256', (string) microtime(true));

        $request = Factory::createServerRequest('GET', '/');
        $request = $request->withHeader('Accept', 'application/json');

        $stack = [
            $this->getInstance(),
            static function () use ($message): never {
                throw new HttpException\BadRequestException($message);
            },
        ];

        $response = Dispatcher::run($stack, $request);
        $body     = $response->getBody();
        $contents = $body->getContents();

        $headers = $response->getHeaders();

        self::assertArrayHasKey('Content-Type', $headers);
        self::assertArrayHasKey(0, $headers['Content-Type']);
        self::assertSame($headers['Content-Type'][0], 'application/problem+json');

        $entity = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($entity));

        $this->verifyProblemJson($entity, $message);
    }

    private function verifyEntity(array $array, string $message): void
    {
        unset($message);

        $statusCode = HttpStatus::STATUS_BAD_REQUEST;

        $expected = $statusCode;
        self::assertSame($expected, $array['statusCode']);

        $expected = 'Bad Request';
        self::assertSame($expected, $array['name']);

        $expected = 'The request cannot be fulfilled due to bad syntax.';
        self::assertSame($expected, $array['phrase']);

        $expected = HttpException\BadRequestException::class;
        self::assertSame($expected, $array['exception']);

        $expected = sprintf('https://httpstatuses.com/%d', $statusCode);
        self::assertSame($expected, $array['url']);
    }

    private function verifyProblemJson(array $array, string $message): void
    {
        $statusCode = HttpStatus::STATUS_BAD_REQUEST;

        $expected = sprintf('https://httpstatuses.com/%d', $statusCode);
        self::assertSame($expected, $array['type']);

        $expected = 'Bad Request';
        self::assertSame($expected, $array['title']);

        $expected = $statusCode;
        self::assertSame($expected, $array['status']);

        $expected = $message;
        self::assertSame($expected, $array['detail']);
    }

    private function verifyException(array $array, string $message): void
    {
        $expected = HttpStatus::STATUS_BAD_REQUEST;
        self::assertSame($expected, $array['statusCode']);

        $expected = $message;
        self::assertSame($expected, $array['message']);
    }

    private function getInstance(): HttpExceptionMiddleware
    {
        $template  = new TemplateRenderer();
        $path      = (string) realpath(__DIR__ . '/TestAsset/error');
        $namespace = 'error';
        $template->addPath($path, $namespace);

        $container = new ServiceManager();
        $container->setService('ctw_template_renderer', $template);

        $factory = new HttpExceptionMiddlewareFactory();

        return $factory->__invoke($container);
    }
}
