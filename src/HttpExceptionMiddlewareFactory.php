<?php
declare(strict_types=1);

namespace Ctw\Middleware\HttpExceptionMiddleware;

use Mezzio\Template\TemplateRendererInterface as Template;
use Mezzio\LaminasView\LaminasViewRenderer as TemplateRenderer;
use Psr\Container\ContainerInterface;

class HttpExceptionMiddlewareFactory
{
    public function __invoke(ContainerInterface $container): HttpExceptionMiddleware
    {
        $template = $container->get(Template::class);
        //$template = $container->get('ctw_template_renderer');

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);

        return $middleware;
    }
}
