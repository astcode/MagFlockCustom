<?php

namespace Components\MagGate;

class Route
{
    private array $methods;
    private string $path;
    private $handler;
    private array $middleware = [];
    private ?string $name = null;
    private array $where = [];
    
    public function __construct(array $methods, string $path, $handler)
    {
        $this->methods = $methods;
        $this->path = $path;
        $this->handler = $handler;
    }
    
    public function middleware($middleware): self
    {
        $this->middleware = array_merge($this->middleware, (array) $middleware);
        return $this;
    }
    
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }
    
    public function where(array $constraints): self
    {
        $this->where = array_merge($this->where, $constraints);
        return $this;
    }
    
    public function matches(string $path): ?array
    {
        $pattern = $this->compilePattern();
        
        if (preg_match($pattern, $path, $matches)) {
            return $this->extractParams($matches);
        }
        
        return null;
    }
    
    private function compilePattern(): string
    {
        $pattern = $this->path;
        
        // Replace {param} with regex
        $pattern = preg_replace_callback('/\{(\w+)(\?)?\}/', function($matches) {
            $param = $matches[1];
            $optional = isset($matches[2]);
            
            // Check for custom constraint
            if (isset($this->where[$param])) {
                $regex = $this->where[$param];
            } else {
                $regex = '[^/]+';
            }
            
            if ($optional) {
                return "(?:/(?P<{$param}>{$regex}))?";
            }
            
            return "(?P<{$param}>{$regex})";
        }, $pattern);
        
        return '#^' . $pattern . '$#';
    }
    
    private function extractParams(array $matches): array
    {
        $params = [];
        
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        
        return $params;
    }
    
    public function handle(Request $request): Response
    {
        // Run route-specific middleware
        if (!empty($this->middleware)) {
            $pipeline = new MiddlewarePipeline();
            foreach ($this->middleware as $middleware) {
                $pipeline->add($middleware);
            }
            
            return $pipeline->handle($request, function($request) {
                return $this->executeHandler($request);
            });
        }
        
        return $this->executeHandler($request);
    }
    
    private function executeHandler(Request $request): Response
    {
        if (is_callable($this->handler)) {
            $result = call_user_func($this->handler, $request);
        } elseif (is_string($this->handler)) {
            // Controller@method format
            [$controller, $method] = explode('@', $this->handler);
            $instance = new $controller();
            $result = $instance->$method($request);
        } else {
            throw new \RuntimeException("Invalid route handler");
        }
        
        // Convert result to Response
        if ($result instanceof Response) {
            return $result;
        }
        
        if (is_array($result)) {
            return new Response($result);
        }
        
        return new Response($result, 200, ['Content-Type' => 'text/html']);
    }
}