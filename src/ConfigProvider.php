<?php
declare(strict_types=1);

namespace Ctw\Middleware\HttpExceptionMiddleware;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            'factories' => [
                HttpExceptionMiddleware::class => HttpExceptionMiddlewareFactory::class,
            ],
        ];
    }
}
