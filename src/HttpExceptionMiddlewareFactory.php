<?php
declare(strict_types=1);

namespace Ctw\Middleware\HttpExceptionMiddleware;

use Mezzio\Template\TemplateRendererInterface as Template;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class HttpExceptionMiddlewareFactory
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container): HttpExceptionMiddleware
    {
        $template = null;
        if ($container->has('ctw_template_renderer')) {
            $template = $container->get('ctw_template_renderer');
        } elseif ($container->has(Template::class)) {
            $template = $container->get(Template::class);
        }

        assert($template instanceof Template);

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);

        return $middleware;
    }
}
