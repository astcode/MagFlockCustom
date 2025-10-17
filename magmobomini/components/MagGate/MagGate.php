<?php

namespace Components\MagGate;

use MoBo\Contracts\ComponentInterface;
use MoBo\Logger;
use MoBo\EventBus;
use Components\MagPuma\MagPuma;

class MagGate implements ComponentInterface
{
    private Router $router;
    private MiddlewarePipeline $pipeline;
    private array $config = [];
    private ?Logger $logger = null;
    private ?EventBus $eventBus = null;
    private bool $isBooted = false;
    
    public function getName(): string
    {
        return 'MagGate';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getDependencies(): array
    {
        return ['MagDB']; // MagPuma is optional, will check at runtime
    }
    
    public function configure(array $config): void
    {
        $this->config = $config;
        
        if ($this->logger) {
            $this->logger->info("MagGate configured", 'MAGGATE');
        }
    }
    
    public function boot(): void
    {
        // Load config if not already loaded
        if (empty($this->config)) {
            $configPath = __DIR__ . '/config.php';
            if (file_exists($configPath)) {
                $this->config = require $configPath;
            } else {
                throw new \RuntimeException("MagGate config not found at: {$configPath}");
            }
        }
        
        // Initialize router
        $this->router = new Router($this->logger);
        
        // Initialize middleware pipeline
        $this->pipeline = new MiddlewarePipeline($this->logger);
        
        // Load global middleware
        if (isset($this->config['middleware']['global'])) {
            foreach ($this->config['middleware']['global'] as $middleware) {
                $this->pipeline->add($middleware);
            }
        }
        
        $this->isBooted = true;
        
        if ($this->logger) {
            $this->logger->info("MagGate booted", 'MAGGATE');
        }
    }
    
    public function start(): void
    {
        if ($this->logger) {
            $this->logger->info("MagGate started", 'MAGGATE', [
                'routes' => $this->router->count(),
                'middleware' => $this->pipeline->count(),
            ]);
        }
    }
    
    public function stop(): void
    {
        if ($this->logger) {
            $this->logger->info("MagGate stopped", 'MAGGATE');
        }
    }
    
    public function health(): array
    {
        return [
            'status' => 'healthy',
            'routes' => $this->router->count(),
            'middleware' => $this->pipeline->count(),
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
    
    // Helper methods for MagPuma integration
    
    private function hasMagPuma(): bool
    {
        return app()->has('MagPuma');
    }
    
    private function getMagPuma(): ?MagPuma
    {
        return app()->get('MagPuma');
    }
    
    // Public API
    
    public function handle(Request $request): Response
    {
        try {
            // Optional: Run through MagPuma if available
            if ($this->hasMagPuma()) {
                $magPuma = $this->getMagPuma();
                $ipProfile = $magPuma->analyzeIP($request->ip());
                $action = $magPuma->decide($ipProfile, $request);
                
                if ($action->action === 'block') {
                    return new Response([
                        'error' => 'Access denied',
                        'reason' => 'Security policy violation',
                    ], 403);
                }
            }
            
            // Run through middleware pipeline
            $response = $this->pipeline->handle($request, function($request) {
                // Route the request
                return $this->router->dispatch($request);
            });
            
            if ($this->logger) {
                $this->logger->info("Request handled", 'MAGGATE', [
                    'method' => $request->method(),
                    'path' => $request->path(),
                    'status' => $response->getStatusCode(),
                    'time' => $request->getElapsedTime() . 'ms',
                ]);
            }
            
            return $response;
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Request failed", 'MAGGATE', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            
            return new Response([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function router(): Router
    {
        return $this->router;
    }
    
    public function middleware(): MiddlewarePipeline
    {
        return $this->pipeline;
    }
}