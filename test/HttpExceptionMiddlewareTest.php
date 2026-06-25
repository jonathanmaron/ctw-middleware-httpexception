<?php

declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware;

use Ctw\Http\HttpException;
use Ctw\Http\HttpStatus;
use Ctw\Middleware\HttpExceptionMiddleware\AbstractHttpExceptionMiddleware;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddleware;
use Ctw\Middleware\HttpExceptionMiddleware\HttpExceptionMiddlewareFactory;
use CtwTest\Middleware\HttpExceptionMiddleware\TestAsset\LaminasDevelopmentModeStatus;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\ServiceManager\ServiceManager;
use Mezzio\LaminasView\LaminasViewRenderer as TemplateRenderer;
use Mezzio\Template\TemplateRendererInterface as Template;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

#[CoversClass(HttpExceptionMiddleware::class)]
#[CoversClass(AbstractHttpExceptionMiddleware::class)]
final class HttpExceptionMiddlewareTest extends AbstractCase
{
    /**
     * Ensure the development-mode status fixture is loaded so that the
     * production guard "class_exists('\Laminas\DevelopmentMode\Status')"
     * passes and the detection branch becomes reachable in tests.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        LaminasDevelopmentModeStatus::load();
    }

    /**
     * Test that the middleware renders an HTML error page from the configured
     * template when a caught HttpException is handled and the client does not
     * request JSON.
     */
    public function testProcessRendersHtmlErrorPageWhenClientDoesNotRequestJson(): void
    {
        $message = hash('sha256', (string) microtime(true));

        $stack = [
            $this->getInstance(),
            static function () use ($message): never {
                throw new HttpException\BadRequestException($message);
            },
        ];

        $response = Dispatcher::run($stack);
        $body     = $response->getBody();
        $contents = $body->getContents();

        $array = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($array));

        [$entity, $exception] = $array;
        assert(is_array($entity));
        assert(is_array($exception));

        $this->verifyEntity($entity);
        $this->verifyException($exception, $message);
    }

    /**
     * Test that the middleware renders a problem+json response with the
     * RFC 7807 fields and the matching content type when the client requests
     * JSON via the Accept header.
     */
    public function testProcessRendersProblemJsonWhenClientRequestsJson(): void
    {
        $message = hash('sha256', (string) microtime(true));

        $request = Factory::createServerRequest('GET', '/');
        $request = $request->withHeader('Accept', 'application/json');

        $stack = [
            $this->getInstance(),
            static function () use ($message): never {
                throw new HttpException\BadRequestException($message);
            },
        ];

        $response = Dispatcher::run($stack, $request);
        $body     = $response->getBody();
        $contents = $body->getContents();

        $headers = $response->getHeaders();

        self::assertArrayHasKey('Content-Type', $headers);
        self::assertArrayHasKey(0, $headers['Content-Type']);
        self::assertSame('application/problem+json', $headers['Content-Type'][0]);
        self::assertSame(HttpStatus::STATUS_BAD_REQUEST, $response->getStatusCode());

        $entity = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($entity));

        $this->verifyProblemJson($entity, $message);
    }

    /**
     * Test that the middleware renders the problem+json response even when the
     * Accept header lists several media types and "application/json" is only
     * one of them.
     */
    public function testProcessRendersProblemJsonWhenAcceptHeaderContainsMultipleTypes(): void
    {
        $message = hash('sha256', (string) microtime(true));

        $request = Factory::createServerRequest('GET', '/');
        $request = $request->withHeader('Accept', 'text/html, application/json;q=0.9, */*;q=0.1');

        $stack = [
            $this->getInstance(),
            static function () use ($message): never {
                throw new HttpException\NotFoundException($message);
            },
        ];

        $response = Dispatcher::run($stack, $request);

        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertSame(HttpStatus::STATUS_NOT_FOUND, $response->getStatusCode());

        $entity = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($entity));

        self::assertSame(HttpStatus::STATUS_NOT_FOUND, $entity['status']);
        self::assertSame('Not Found', $entity['title']);
        self::assertSame($message, $entity['detail']);
    }

    /**
     * Test that the middleware preserves the HTTP status code carried by the
     * server-error exception when rendering the problem+json response.
     */
    public function testProcessPreservesStatusCodeForServerErrorException(): void
    {
        $request = Factory::createServerRequest('GET', '/');
        $request = $request->withHeader('Accept', 'application/json');

        $stack = [
            $this->getInstance(),
            static function (): never {
                throw new HttpException\InternalServerErrorException();
            },
        ];

        $response = Dispatcher::run($stack, $request);

        self::assertSame(HttpStatus::STATUS_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $entity = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($entity));

        self::assertSame(HttpStatus::STATUS_INTERNAL_SERVER_ERROR, $entity['status']);
        self::assertSame('Internal Server Error', $entity['title']);
    }

    /**
     * Test that the HTML response uses the custom template name from the
     * error-handler configuration when "template_http_exception" is set to a
     * string.
     */
    public function testGetHtmlResponseUsesCustomTemplateNameFromConfig(): void
    {
        $exception = new HttpException\BadRequestException('custom-template');

        $template = self::createMock(Template::class);
        $template->expects(self::once())
            ->method('render')
            ->with(
                self::identicalTo('error::my-custom-error.phtml'),
                self::callback(static fn (array $data): bool => !isset($data['layout'])),
            )
            ->willReturn('<html>custom</html>');

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);
        $middleware->setErrorHandlerConfig([
            'template_http_exception' => 'error::my-custom-error.phtml',
        ]);

        $response = $this->dispatchThrowing($middleware, $exception);

        self::assertInstanceOf(HtmlResponse::class, $response);
        self::assertSame(HttpStatus::STATUS_BAD_REQUEST, $response->getStatusCode());
        self::assertSame('<html>custom</html>', (string) $response->getBody());
    }

    /**
     * Test that the HTML response passes the configured layout to the template
     * renderer when the error-handler configuration provides a non-empty
     * "layout" string.
     */
    public function testGetHtmlResponsePassesConfiguredLayoutToTemplate(): void
    {
        $exception = new HttpException\ForbiddenException('with-layout');

        $template = self::createMock(Template::class);
        $template->expects(self::once())
            ->method('render')
            ->with(
                self::identicalTo('error::http-exception.phtml'),
                self::callback(static fn (array $data): bool => 'layout::error' === ($data['layout'] ?? null)),
            )
            ->willReturn('<html>layout</html>');

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);
        $middleware->setErrorHandlerConfig([
            'layout' => 'layout::error',
        ]);

        $response = $this->dispatchThrowing($middleware, $exception);

        self::assertSame(HttpStatus::STATUS_FORBIDDEN, $response->getStatusCode());
        self::assertSame('<html>layout</html>', (string) $response->getBody());
    }

    /**
     * Test that the HTML response falls back to the default template name and
     * omits the layout when the error-handler configuration is empty.
     */
    public function testGetHtmlResponseUsesDefaultsWhenConfigIsEmpty(): void
    {
        $exception = new HttpException\BadRequestException('defaults');

        $template = self::createMock(Template::class);
        $template->expects(self::once())
            ->method('render')
            ->with(
                self::identicalTo('error::http-exception.phtml'),
                self::callback(static fn (array $data): bool => !isset($data['layout'])),
            )
            ->willReturn('<html>default</html>');

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);

        $response = $this->dispatchThrowing($middleware, $exception);

        self::assertSame('<html>default</html>', (string) $response->getBody());
    }

    /**
     * Test that the middleware re-throws the original throwable when it does
     * not implement HttpExceptionInterface, leaving non-HTTP exceptions to
     * propagate up the stack.
     */
    public function testProcessRethrowsThrowableThatIsNotAnHttpException(): void
    {
        $expected = new RuntimeException('not-an-http-exception');

        $stack = [$this->getInstance(), static function () use ($expected): never {
            throw $expected;
        }, ];

        try {
            Dispatcher::run($stack);
            self::fail('Expected the original throwable to be re-thrown.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame($expected, $runtimeException);
        }
    }

    /**
     * Test that the middleware re-throws an HttpException instead of rendering
     * an error page when development mode is enabled, so developers see the
     * original error.
     */
    public function testProcessRethrowsHttpExceptionWhenDevelopmentModeIsEnabled(): void
    {
        $expected = new HttpException\BadRequestException('dev-mode');

        $template = self::createStub(Template::class);
        $template->method('render')
            ->willReturn('<html>should-not-be-used</html>');

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);

        $cwd     = (string) getcwd();
        $project = $this->createDevelopmentModeProject();

        try {
            chdir($project);
            $this->dispatchThrowing($middleware, $expected);
            self::fail('Expected the HttpException to be re-thrown in development mode.');
        } catch (HttpException\BadRequestException $badRequestException) {
            self::assertSame($expected, $badRequestException);
        } finally {
            chdir($cwd);
            $this->removeDirectory($project);
        }
    }

    /**
     * Test that the middleware still renders the error page when the
     * development-mode status class reports that development mode is disabled.
     */
    public function testProcessRendersErrorPageWhenDevelopmentModeIsDisabled(): void
    {
        $exception = new HttpException\BadRequestException('prod-mode');

        $template = self::createStub(Template::class);
        $template->method('render')
            ->willReturn('<html>prod</html>');

        $middleware = new HttpExceptionMiddleware();
        $middleware->setTemplate($template);

        $cwd     = (string) getcwd();
        $project = $this->createEmptyProject();

        try {
            chdir($project);
            $response = $this->dispatchThrowing($middleware, $exception);
            self::assertSame('<html>prod</html>', (string) $response->getBody());
        } finally {
            chdir($cwd);
            $this->removeDirectory($project);
        }
    }

    /**
     * Test that getTemplate returns the exact renderer instance previously
     * passed to setTemplate.
     */
    public function testSetTemplateAndGetTemplateRoundTripTheRenderer(): void
    {
        $template = self::createStub(Template::class);

        $middleware = new HttpExceptionMiddleware();

        self::assertSame($middleware, $middleware->setTemplate($template));
        self::assertSame($template, $middleware->getTemplate());
    }

    /**
     * Test that getErrorHandlerConfig returns the exact array previously passed
     * to setErrorHandlerConfig and that the setter returns the middleware for
     * chaining.
     */
    public function testSetErrorHandlerConfigAndGetErrorHandlerConfigRoundTripTheArray(): void
    {
        $config = [
            'template_http_exception' => 'error::custom.phtml',
            'layout'                  => 'layout::default',
        ];

        $middleware = new HttpExceptionMiddleware();

        self::assertSame([], $middleware->getErrorHandlerConfig());
        self::assertSame($middleware, $middleware->setErrorHandlerConfig($config));
        self::assertSame($config, $middleware->getErrorHandlerConfig());
    }

    /**
     * Dispatch a single throwing handler through the given middleware and
     * return the resulting response.
     */
    private function dispatchThrowing(
        HttpExceptionMiddleware $middleware,
        HttpException\AbstractException $exception,
    ): ResponseInterface {
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

        return $middleware->process(Factory::createServerRequest('GET', '/'), $handler);
    }

    /**
     * Create a temporary project directory that contains a development-mode
     * configuration file, simulating an enabled development mode.
     */
    private function createDevelopmentModeProject(): string
    {
        $project = $this->createEmptyProject();

        $configDir = $project . '/config';
        mkdir($configDir);
        file_put_contents($configDir . '/development.config.php', "<?php\n\nreturn [];\n");

        return $project;
    }

    /**
     * Create an empty temporary project directory.
     */
    private function createEmptyProject(): string
    {
        $project = sys_get_temp_dir() . '/httpexception-' . bin2hex(random_bytes(8));
        mkdir($project);

        return $project;
    }

    /**
     * Recursively remove a directory created for a test.
     */
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $config = $directory . '/config/development.config.php';
        if (is_file($config)) {
            unlink($config);
        }

        $configDir = $directory . '/config';
        if (is_dir($configDir)) {
            rmdir($configDir);
        }

        rmdir($directory);
    }

    private function getInstance(): HttpExceptionMiddleware
    {
        $template  = new TemplateRenderer();
        $path      = (string) realpath(__DIR__ . '/TestAsset/error');
        $namespace = 'error';
        $template->addPath($path, $namespace);

        $container = new ServiceManager();
        $container->setService('ctw_template_renderer', $template);

        $factory = new HttpExceptionMiddlewareFactory();

        return $factory->__invoke($container);
    }

    /**
     * @param array<mixed, mixed> $array
     */
    private function verifyEntity(array $array): void
    {
        $statusCode = HttpStatus::STATUS_BAD_REQUEST;

        self::assertSame($statusCode, $array['statusCode']);
        self::assertSame('Bad Request', $array['name']);
        self::assertSame('The request cannot be fulfilled due to bad syntax.', $array['phrase']);
        self::assertSame(HttpException\BadRequestException::class, $array['exception']);
        self::assertSame(sprintf('https://httpstatuses.com/%d', $statusCode), $array['url']);
    }

    /**
     * @param array<mixed, mixed> $array
     */
    private function verifyProblemJson(array $array, string $message): void
    {
        $statusCode = HttpStatus::STATUS_BAD_REQUEST;

        self::assertSame(sprintf('https://httpstatuses.com/%d', $statusCode), $array['type']);
        self::assertSame('Bad Request', $array['title']);
        self::assertSame($statusCode, $array['status']);
        self::assertSame($message, $array['detail']);
    }

    /**
     * @param array<mixed, mixed> $array
     */
    private function verifyException(array $array, string $message): void
    {
        self::assertSame(HttpStatus::STATUS_BAD_REQUEST, $array['statusCode']);
        self::assertSame($message, $array['message']);
    }
}
