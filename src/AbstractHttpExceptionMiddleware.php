<?php
declare(strict_types=1);

namespace Ctw\Middleware\HttpExceptionMiddleware;

use Ctw\Http\HttpException;
use Ctw\Http\HttpStatus;
use Laminas\DevelopmentMode;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Template\TemplateRendererInterface as Template;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

abstract class AbstractHttpExceptionMiddleware implements MiddlewareInterface
{
    protected array $errorHandlerConfig = [];

    private Template $template;

    public function getErrorHandlerConfig(): array
    {
        return $this->errorHandlerConfig;
    }

    public function setErrorHandlerConfig(array $errorHandlerConfig): self
    {
        $this->errorHandlerConfig = $errorHandlerConfig;

        return $this;
    }

    public function getTemplate(): Template
    {
        return $this->template;
    }

    public function setTemplate(Template $template): self
    {
        $this->template = $template;

        return $this;
    }

    protected function asJson(ServerRequestInterface $request): bool
    {
        $header = $request->getHeader('Accept');
        $header = array_filter($header, static function (string $string): bool {
            $pos = strpos($string, 'application/json');

            return is_int($pos);
        });

        return [] !== $header;
    }

    protected function getJsonResponse(HttpException\HttpExceptionInterface $exception): JsonResponse
    {
        $statusCode = $exception->getStatusCode();

        $entity = (new HttpStatus($statusCode))->get();

        $data = [
            'type'   => $entity->url,
            'title'  => $entity->name,
            'status' => $entity->statusCode,
            'detail' => $exception->getMessage(),
        ];

        return new JsonResponse($data, $exception->getStatusCode(), [
            'Content-Type' => 'application/problem+json',
        ]);
    }

    protected function getHtmlResponse(HttpException\HttpExceptionInterface $exception): HtmlResponse
    {
        $config     = $this->getErrorHandlerConfig();
        $template   = $this->getTemplate();
        $statusCode = $exception->getStatusCode();

        $entity = (new HttpStatus($statusCode))->get();

        if (isset($config['template_http_exception']) && is_array($config['template_http_exception'])) {
            $name = $config['template_http_exception'];
        } else {
            $name = 'error::http-exception.phtml';
        }

        $layout = isset($config['layout']) && is_string($config['layout']) ? $config['layout'] : '';

        $data = [
            'entity'    => $entity,
            'exception' => $exception,
        ];

        if ('' !== $layout) {
            $data['layout'] = $layout;
        }

        $html = $template->render($name, $data);

        return new HtmlResponse($html, $exception->getStatusCode());
    }

    protected function isDevelopmentMode(): bool
    {
        // @todo there must be a better way of doing this :-)
        // @todo get development mode status from container in factory?

        // "composer install --no-dev" removes this class
        $class = '\Laminas\DevelopmentMode\Status';
        if (!class_exists($class)) {
            return false;
        }

        ob_start();
        (new DevelopmentMode\Status())->__invoke();
        $ob  = (string) ob_get_clean();
        $pos = strpos($ob, 'ENABLED');

        return is_int($pos);
    }
}
