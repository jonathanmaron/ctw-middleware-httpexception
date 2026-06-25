<?php

declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware;

use Ctw\Http\HttpException;
use Ctw\Middleware\HttpExceptionMiddleware\AbstractHttpExceptionMiddleware;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddleware;
use Laminas\Diactoros\Response\HtmlResponse;
use Mezzio\Template\TemplateRendererInterface as Template;
use Middlewares\Utils\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Exercises the guard clause that treats development mode as disabled when the
 * optional "laminas/laminas-development-mode" package has been removed by
 * "composer install --no-dev".
 *
 * This scenario must run in a separate process and must not load the
 * development-mode status fixture, so that
 * "class_exists('\Laminas\DevelopmentMode\Status')" returns false and the
 * early "return false" branch in isDevelopmentMode() is exercised.
 */
#[CoversClass(HttpExceptionMiddleware::class)]
#[CoversClass(AbstractHttpExceptionMiddleware::class)]
final class HttpExceptionMiddlewareDevelopmentModeAbsentTest extends AbstractCase
{
    /**
     * Test that the middleware treats development mode as disabled and renders
     * the error page when the development-mode status class is not available.
     */
    #[RunInSeparateProcess]
    public function testProcessRendersErrorPageWhenDevelopmentModeClassIsAbsent(): void
    {
        self::assertFalse(
            class_exists(\Laminas\DevelopmentMode\Status::class),
            'The development-mode status class must be absent for this scenario.',
        );

        $exception = new HttpException\BadRequestException('no-dev-mode-package');

        $template = self::createStub(Template::class);
        $template->method('render')
            ->willReturn('<html>no-dev-mode</html>');

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);

        $handler = new readonly class($exception) implements RequestHandlerInterface {
            public function __construct(
                private HttpException\AbstractException $exception
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $response = $middleware->process(Factory::createServerRequest('GET', '/'), $handler);

        self::assertInstanceOf(HtmlResponse::class, $response);
        self::assertSame('<html>no-dev-mode</html>', (string) $response->getBody());
    }
}
