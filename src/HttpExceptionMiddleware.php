<?php
declare(strict_types=1);

namespace Ctw\Middleware\HttpExceptionMiddleware;

use Ctw\Http\HttpException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class HttpExceptionMiddleware extends AbstractHttpExceptionMiddleware
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            // continue;
        }

        if (!$this->isDevelopmentMode()) {
            if ($exception instanceof HttpException\HttpExceptionInterface) {
                return $this->asJson($request)
                    ? $this->getJsonResponse($exception)
                    : $this->getHtmlResponse($exception);
            }
        }

        throw $exception;
    }
}
