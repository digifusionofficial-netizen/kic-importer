<?php

namespace KIC\Importer;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = __NAMESPACE__ . '\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
}
