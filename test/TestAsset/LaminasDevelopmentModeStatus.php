<?php

declare(strict_types=1);

namespace CtwTest\Middleware\HttpExceptionMiddleware\TestAsset;

/**
 * Test fixture loader for the optional development-mode status class.
 *
 * The production package "ctw/ctw-middleware-httpexception" is installed with
 * "composer install --no-dev", which removes the
 * "laminas/laminas-development-mode" package. The middleware guards against
 * this by checking "class_exists('\Laminas\DevelopmentMode\Status')" before
 * using the class, so the detection branch in
 * AbstractHttpExceptionMiddleware::isDevelopmentMode() is otherwise
 * unreachable in tests.
 *
 * This loader declares a behavior-compatible copy of the upstream class at the
 * exact same fully qualified name via eval() at call time. Using eval() keeps
 * the class invisible to static analysis (PHPStan, Rector) scanning the test
 * tree, so it does not change how those tools see the production code, while
 * still satisfying the runtime "class_exists()" guard.
 *
 * The implementation mirrors Laminas\DevelopmentMode\Status: it echoes
 * "Development mode is ENABLED" when a "config/development.config.php" file
 * exists relative to the current working directory, and "DISABLED" otherwise.
 */
final class LaminasDevelopmentModeStatus
{
    /**
     * Declare the upstream development-mode status class if it is not already
     * available.
     */
    public static function load(): void
    {
        if (class_exists('\Laminas\DevelopmentMode\Status')) {
            return;
        }

        eval(self::source());
    }

    /**
     * Return the PHP source of the behavior-compatible status class.
     */
    private static function source(): string
    {
        return <<<'PHP_WRAP'
        namespace Laminas\DevelopmentMode;
        
        class Status
        {
            public const DEVEL_CONFIG = 'config/development.config.php';
        
            private string $develConfigFile;
        
            public function __construct(string $projectDir = '')
            {
                if ('' === $projectDir) {
                    $this->develConfigFile = self::DEVEL_CONFIG;
        
                    return;
                }
        
                $this->develConfigFile = sprintf('%s/%s', $projectDir, self::DEVEL_CONFIG);
            }
        
            public function __invoke(): int
            {
                if (file_exists($this->develConfigFile)) {
                    echo 'Development mode is ENABLED', PHP_EOL;
        
                    return 0;
                }
        
                echo 'Development mode is DISABLED', PHP_EOL;
        
                return 0;
            }
        }
        PHP_WRAP;
    }
}
