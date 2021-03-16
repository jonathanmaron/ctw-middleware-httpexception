<?php
declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware;

use Ctw\Middleware\HttpExceptionMiddleware\ConfigProvider;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddleware;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddlewareFactory;

class ConfigProviderTest extends AbstractCase
{
    public function testConfigProvider(): void
    {
        $configProvider = new ConfigProvider();

        $expected = [
            'dependencies' => [
                'factories' => [
                    HttpExceptionMiddleware::class => HttpExceptionMiddlewareFactory::class,
                ],
            ],
        ];

        $this->assertSame($expected, $configProvider->__invoke());
    }
}
