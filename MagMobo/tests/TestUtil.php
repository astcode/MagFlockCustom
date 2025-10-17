<?php
// tests/TestUtil.php
declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use MoBo\Kernel;

/**
 * Acquire the Kernel instance in a constructor-agnostic way.
 * Supports common singletons/factories:
 *   Kernel::getInstance(), ::instance(), ::singleton(), ::create(), ::make()
 * Falls back to 'new Kernel(...)' only if the ctor is public.
 */
function loadKernel(array $overrideConfig = []): Kernel {
    $base = $GLOBALS['config'] ?? [];
    $merged = array_replace_recursive($base, $overrideConfig);

    // Try common static factories/singletons first
    if (method_exists(Kernel::class, 'getInstance')) {
        /** @var Kernel $k */
        $k = Kernel::getInstance($merged);
    } elseif (method_exists(Kernel::class, 'instance')) {
        $k = Kernel::instance($merged);
    } elseif (method_exists(Kernel::class, 'singleton')) {
        $k = Kernel::singleton($merged);
    } elseif (method_exists(Kernel::class, 'create')) {
        $k = Kernel::create($merged);
    } elseif (method_exists(Kernel::class, 'make')) {
        $k = Kernel::make($merged);
    } else {
        // If ctor is public, use it; otherwise instruct to expose a factory
        $ref = new ReflectionClass(Kernel::class);
        $ctor = $ref->getConstructor();
        if ($ctor && $ctor->isPublic()) {
            /** @var Kernel $k */
            $k = $ref->newInstance($merged);
        } else {
            fwrite(STDERR,
                "Kernel constructor is not public and no static factory was found.\n".
                "Please expose a public static factory, e.g. Kernel::getInstance(array \$config = []).\n"
            );
            exit(1);
        }
    }

    // Init if available (stay no-op if kernel already initialized)
    if (method_exists($k, 'init')) {
        $k->init();
    }
    return $k;
}

/** Pretty header box without weird variable interpolation */
function hr(string $title): void {
    $line = str_repeat('═', 58);
    // Center the title roughly
    $innerWidth = 58;
    $pad = max(0, intdiv($innerWidth - mb_strlen($title), 2));
    $left = str_repeat(' ', $pad);
    $right = str_repeat(' ', max(0, $innerWidth - $pad - mb_strlen($title)));

    echo "╔{$line}╗\n";
    echo "║{$left}{$title}{$right}║\n";
    echo "╚{$line}╝\n\n";
}

/** Convenience wrappers the tests use */
function readDbProfile(Kernel $kernel): ?array {
    // Try Kernel->config() first; if you keep ConfigManager elsewhere, adjust here.
    $cfg = method_exists($kernel, 'config')
        ? ($kernel->config()->get('database') ?? [])
        : ($GLOBALS['config']['database'] ?? []);

    $default = $cfg['default'] ?? null;
    $conns = $cfg['connections'] ?? [];
    if (!$default || !isset($conns[$default])) {
        $default = 'magdsdb';
        if (!isset($conns[$default])) {
            return null;
        }
    }
    return $conns[$default];
}
