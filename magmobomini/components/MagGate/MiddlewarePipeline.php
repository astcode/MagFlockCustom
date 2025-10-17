<?php

namespace Components\MagGate;

use MoBo\Logger;

class MiddlewarePipeline
{
    private array $middleware = [];
    private ?Logger $logger = null;
    
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }
    
    public function add($middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }
    
    public function handle(Request $request, callable $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->carry(),
            $destination
        );
        
        return $pipeline($request);
    }
    
    private function carry(): callable
    {
        return function ($stack, $middleware) {
            return function ($request) use ($stack, $middleware) {
                // Resolve middleware
                if (is_string($middleware)) {
                    $middleware = $this->resolve($middleware);
                }
                
                if (is_callable($middleware)) {
                    return $middleware($request, $stack);
                }
                
                if (is_object($middleware) && method_exists($middleware, 'handle')) {
                    return $middleware->handle($request, $stack);
                }
                
                throw new \RuntimeException("Invalid middleware: " . gettype($middleware));
            };
        };
    }
    
    private function resolve(string $middleware)
    {
        // Parse middleware:parameters format
        [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, null);
        
        // Map middleware aliases
        $map = [
            'auth' => \Components\MagGate\Middleware\Auth::class,
            'cors' => \Components\MagGate\Middleware\CORS::class,
            'rate-limit' => \Components\MagGate\Middleware\RateLimit::class,
            'magpuma.pre' => \Components\MagPuma\PreFlightMiddleware::class,
            'magpuma.post' => \Components\MagPuma\PostFlightMiddleware::class,
        ];
        
        $class = $map[$name] ?? $name;
        
        if (!class_exists($class)) {
            throw new \RuntimeException("Middleware not found: {$name}");
        }
        
        $instance = new $class();
        
        // Set parameters if provided
        if ($parameters && method_exists($instance, 'setParameters')) {
            $instance->setParameters(explode(',', $parameters));
        }
        
        return $instance;
    }
    
    public function count(): int
    {
        return count($this->middleware);
    }
}