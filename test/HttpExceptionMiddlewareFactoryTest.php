<?php

declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware;

use AssertionError;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddlewareFactory;
use Mezzio\Template\TemplateRendererInterface as Template;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Container\ContainerInterface;

#[CoversClass(HttpExceptionMiddlewareFactory::class)]
final class HttpExceptionMiddlewareFactoryTest extends AbstractCase
{
    /**
     * Test that __invoke builds the middleware and wires the template renderer
     * resolved from the "ctw_template_renderer" service when that service is
     * registered in the container.
     */
    public function testInvokeResolvesTemplateFromCtwTemplateRendererService(): void
    {
        $template = self::createStub(Template::class);

        $container = self::createStub(ContainerInterface::class);
        $container->method('has')
            ->willReturnMap([['ctw_template_renderer', true], ['config', false]]);
        $container->method('get')
            ->willReturnMap([['ctw_template_renderer', $template]]);

        $factory    = new HttpExceptionMiddlewareFactory();
        $middleware = $factory->__invoke($container);

        self::assertSame($template, $middleware->getTemplate());
        self::assertSame([], $middleware->getErrorHandlerConfig());
    }

    /**
     * Test that __invoke falls back to the TemplateRendererInterface service
     * when the "ctw_template_renderer" alias is not registered in the
     * container.
     */
    public function testInvokeFallsBackToTemplateRendererInterfaceService(): void
    {
        $template = self::createStub(Template::class);

        $container = self::createStub(ContainerInterface::class);
        $container->method('has')
            ->willReturnMap([['ctw_template_renderer', false], [Template::class, true], ['config', false]]);
        $container->method('get')
            ->willReturnMap([[Template::class, $template]]);

        $factory    = new HttpExceptionMiddlewareFactory();
        $middleware = $factory->__invoke($container);

        self::assertSame($template, $middleware->getTemplate());
    }

    /**
     * Test that __invoke applies the error-handler configuration to the
     * middleware when the container provides a valid nested
     * "mezzio.error_handler" config array.
     */
    public function testInvokeAppliesErrorHandlerConfigWhenPresent(): void
    {
        $template = self::createStub(Template::class);

        $errorHandlerConfig = [
            'template_http_exception' => 'error::custom.phtml',
            'layout'                  => 'layout::default',
        ];

        $config = [
            'mezzio' => [
                'error_handler' => $errorHandlerConfig,
            ],
        ];

        $container = self::createStub(ContainerInterface::class);
        $container->method('has')
            ->willReturnMap([['ctw_template_renderer', true], ['config', true]]);
        $container->method('get')
            ->willReturnMap([['ctw_template_renderer', $template], ['config', $config]]);

        $factory    = new HttpExceptionMiddlewareFactory();
        $middleware = $factory->__invoke($container);

        self::assertSame($errorHandlerConfig, $middleware->getErrorHandlerConfig());
    }

    /**
     * Test that __invoke ignores the configuration and leaves the error-handler
     * config empty when the "mezzio.error_handler" key is absent from the
     * container config.
     */
    public function testInvokeLeavesErrorHandlerConfigEmptyWhenErrorHandlerKeyAbsent(): void
    {
        $template = self::createStub(Template::class);

        $container = self::createStub(ContainerInterface::class);
        $container->method('has')
            ->willReturnMap([['ctw_template_renderer', true], ['config', true]]);
        $container->method('get')
            ->willReturnMap([
                ['ctw_template_renderer', $template],
                [
                    'config', [
                        'mezzio' => [],
                    ]],
            ]);

        $factory    = new HttpExceptionMiddlewareFactory();
        $middleware = $factory->__invoke($container);

        self::assertSame([], $middleware->getErrorHandlerConfig());
    }

    /**
     * Test that __invoke triggers an assertion failure when neither template
     * service is registered, because the resolved template remains null.
     */
    public function testInvokeThrowsAssertionErrorWhenNoTemplateServiceAvailable(): void
    {
        $container = self::createStub(ContainerInterface::class);
        $container->method('has')
            ->willReturn(false);

        $factory = new HttpExceptionMiddlewareFactory();

        $this->expectException(AssertionError::class);

        $factory->__invoke($container);
    }
}
