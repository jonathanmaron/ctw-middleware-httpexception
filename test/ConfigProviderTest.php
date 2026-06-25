<?php

declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware;

use Ctw\Middleware\HttpExceptionMiddleware\ConfigProvider;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddleware;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddlewareFactory;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConfigProvider::class)]
final class ConfigProviderTest extends AbstractCase
{
    /**
     * Test that __invoke returns the full configuration array nesting the
     * dependency definitions under the "dependencies" key when invoked.
     */
    public function testInvokeReturnsConfigurationWithDependenciesKey(): void
    {
        $configProvider = new ConfigProvider();

        $expected = [
            'dependencies' => [
                'factories' => [
                    HttpExceptionMiddleware::class => HttpExceptionMiddlewareFactory::class,
                ],
            ],
        ];

        self::assertSame($expected, $configProvider->__invoke());
    }

    /**
     * Test that getDependencies maps the middleware to its factory under the
     * "factories" key when called directly.
     */
    public function testGetDependenciesMapsMiddlewareToItsFactory(): void
    {
        $configProvider = new ConfigProvider();

        $expected = [
            'factories' => [
                HttpExceptionMiddleware::class => HttpExceptionMiddlewareFactory::class,
            ],
        ];

        self::assertSame($expected, $configProvider->getDependencies());
    }
}
