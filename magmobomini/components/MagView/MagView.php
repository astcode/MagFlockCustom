<?php

namespace Components\MagView;

use MoBo\Contracts\ComponentInterface;
use MoBo\Logger;
use MoBo\EventBus;

class MagView implements ComponentInterface
{
    private ?Logger $logger = null;
    private ?EventBus $eventBus = null;
    private array $config = [];
    private ?Engine $engine = null;

    public function getName(): string
    {
        return 'MagView';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function configure(array $config): void
    {
        $this->config = array_merge([
            'views_path' => __DIR__ . '/../../views',
            'cache_path' => __DIR__ . '/../../storage/cache/views',
            'cache_enabled' => true,
            'auto_escape' => true
        ], $config);

        if ($this->logger) {
            $this->logger->info("MagView configured", 'MAGVIEW', $this->config);
        }
    }

    public function boot(): void
    {
        // Get kernel services
        $kernel = app();
        $this->logger = $kernel->getLogger();
        $this->eventBus = $kernel->getEventBus();

        // Create cache directory if it doesn't exist
        if (!is_dir($this->config['cache_path'])) {
            mkdir($this->config['cache_path'], 0755, true);
        }

        // Initialize engine
        $this->engine = new Engine(
            $this->config['views_path'],
            $this->config['cache_path'],
            $this->config['cache_enabled'],
            $this->logger
        );

        // Register default directives
        $this->registerDefaultDirectives();

        $this->logger->info("MagView booted", 'MAGVIEW');
    }

    public function start(): void
    {
        if ($this->logger) {
            $this->logger->info("MagView started", 'MAGVIEW');
        }
    }

    public function stop(): void
    {
        if ($this->logger) {
            $this->logger->info("MagView stopped", 'MAGVIEW');
        }
    }

    public function health(): array
    {
        return [
            'status' => 'healthy',
            'views_path' => $this->config['views_path'],
            'cache_path' => $this->config['cache_path'],
            'cache_enabled' => $this->config['cache_enabled'],
            'cached_views' => count(glob($this->config['cache_path'] . '/*.php'))
        ];
    }

    public function recover(): bool
    {
        return true;
    }

    public function shutdown(int $timeout = 30): void
    {
        $this->stop();
    }

    public function render(string $view, array $data = []): string
    {
        if (!$this->engine) {
            throw new \RuntimeException("MagView engine not initialized. Did you call boot()?");
        }
        return $this->engine->render($view, $data);
    }

    public function directive(string $name, callable $handler): void
    {
        if (!$this->engine) {
            throw new \RuntimeException("MagView engine not initialized. Did you call boot()?");
        }
        $this->engine->directive($name, $handler);
    }

    public function clearCache(): void
    {
        $files = glob($this->config['cache_path'] . '/*.php');
        foreach ($files as $file) {
            unlink($file);
        }
        if ($this->logger) {
            $this->logger->info("MagView cache cleared", 'MAGVIEW');
        }
    }

    private function registerDefaultDirectives(): void
    {
        // @if
        $this->engine->directive('if', function($expression) {
            return "<?php if({$expression}): ?>";
        });

        // @elseif
        $this->engine->directive('elseif', function($expression) {
            return "<?php elseif({$expression}): ?>";
        });

        // @else
        $this->engine->directive('else', function() {
            return "<?php else: ?>";
        });

        // @endif
        $this->engine->directive('endif', function() {
            return "<?php endif; ?>";
        });

        // @foreach
        $this->engine->directive('foreach', function($expression) {
            return "<?php foreach({$expression}): ?>";
        });

        // @endforeach
        $this->engine->directive('endforeach', function() {
            return "<?php endforeach; ?>";
        });

        // @for
        $this->engine->directive('for', function($expression) {
            return "<?php for({$expression}): ?>";
        });

        // @endfor
        $this->engine->directive('endfor', function() {
            return "<?php endfor; ?>";
        });

        // @while
        $this->engine->directive('while', function($expression) {
            return "<?php while({$expression}): ?>";
        });

        // @endwhile
        $this->engine->directive('endwhile', function() {
            return "<?php endwhile; ?>";
        });

        // @include
        $this->engine->directive('include', function($expression) {
            return "<?php echo \$__engine->render({$expression}, get_defined_vars()); ?>";
        });

        // @json
        $this->engine->directive('json', function($expression) {
            return "<?php echo json_encode({$expression}); ?>";
        });

        // @dd (dump and die)
        $this->engine->directive('dd', function($expression) {
            return "<?php var_dump({$expression}); die(); ?>";
        });

        // @dump
        $this->engine->directive('dump', function($expression) {
            return "<?php var_dump({$expression}); ?>";
        });
    }
}