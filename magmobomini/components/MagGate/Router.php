<?php

namespace Components\MagGate;

use MoBo\Logger;

class Router
{
    private array $routes = [];
    private array $namedRoutes = [];
    private array $groupStack = [];
    private ?Logger $logger = null;
    
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }
    
    public function get(string $path, $handler): Route
    {
        return $this->addRoute('GET', $path, $handler);
    }
    
    public function post(string $path, $handler): Route
    {
        return $this->addRoute('POST', $path, $handler);
    }
    
    public function put(string $path, $handler): Route
    {
        return $this->addRoute('PUT', $path, $handler);
    }
    
    public function patch(string $path, $handler): Route
    {
        return $this->addRoute('PATCH', $path, $handler);
    }
    
    public function delete(string $path, $handler): Route
    {
        return $this->addRoute('DELETE', $path, $handler);
    }
    
    public function any(string $path, $handler): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $path, $handler);
    }
    
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }
    
    private function addRoute($methods, string $path, $handler): Route
    {
        $methods = (array) $methods;
        
        // Apply group attributes
        $attributes = $this->mergeGroupAttributes();
        
        if (isset($attributes['prefix'])) {
            $path = rtrim($attributes['prefix'], '/') . '/' . ltrim($path, '/');
        }
        
        $route = new Route($methods, $path, $handler);
        
        // Apply group middleware
        if (isset($attributes['middleware'])) {
            $route->middleware($attributes['middleware']);
        }
        
        foreach ($methods as $method) {
            $this->routes[$method][] = $route;
        }
        
        return $route;
    }
    
    private function mergeGroupAttributes(): array
    {
        $attributes = [];
        
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $attributes['prefix'] = ($attributes['prefix'] ?? '') . '/' . trim($group['prefix'], '/');
            }
            
            if (isset($group['middleware'])) {
                $attributes['middleware'] = array_merge(
                    $attributes['middleware'] ?? [],
                    (array) $group['middleware']
                );
            }
        }
        
        return $attributes;
    }
    
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();
        
        if (!isset($this->routes[$method])) {
            return $this->notFound($request);
        }
        
        foreach ($this->routes[$method] as $route) {
            if ($params = $route->matches($path)) {
                $request->setRouteParams($params);
                return $route->handle($request);
            }
        }
        
        return $this->notFound($request);
    }
    
    private function notFound(Request $request): Response
    {
        // Check if client wants JSON
        if ($request->wantsJson()) {
            return new Response([
                'error' => 'Not Found',
                'path' => $request->path(),
            ], 404);
        }
        
        // Return HTML 404
        return new Response('404 - Not Found', 404, [
            'Content-Type' => 'text/html',
        ]);
    }
    
    public function count(): int
    {
        return array_sum(array_map('count', $this->routes));
    }
}