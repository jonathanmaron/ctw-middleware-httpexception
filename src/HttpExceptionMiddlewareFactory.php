<?php
declare(strict_types=1);

namespace Ctw\Middleware\HttpExceptionMiddleware;

use Mezzio\Template\TemplateRendererInterface as Template;
use Psr\Container\ContainerInterface;

class HttpExceptionMiddlewareFactory
{
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

        $config = $container->has('config') ? $container->get('config') : [];
        if (is_array($config) && isset($config['mezzio']) && is_array(
            $config['mezzio']
        ) && isset($config['mezzio']['error_handler'])) {
            $errorHandlerConfig = $config['mezzio']['error_handler'];
            if (is_array($errorHandlerConfig)) {
                $middleware->setErrorHandlerConfig($errorHandlerConfig);
            }
        }

        return $middleware;
    }
}
