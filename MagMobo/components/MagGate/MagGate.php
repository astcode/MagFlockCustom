<?php
namespace Components\MagGate;

use MoBo\Contracts\AbstractComponent;

class MagGate extends AbstractComponent {
    private array $routes = [];

    public function boot(): void {
        parent::boot();
        // demo routes
        $this->routes['GET']['/health'] = fn() => ['status' => 200, 'body' => ['ok' => true]];
        $this->routes['GET']['/'] = fn() => ['status' => 404, 'body' => ['error' => 'Not Found', 'path' => '/']];
    }

    public function routeCount(): int {
        $count = 0;
        foreach ($this->routes as $m => $map) { $count += count($map); }
        return $count;
    }

    public function dispatch(string $method, string $path): array {
        $method = strtoupper($method);
        if (isset($this->routes[$method][$path])) {
            return ($this->routes[$method][$path])();
        }
        return ['status' => 404, 'body' => ['error' => 'Not Found', 'path' => $path]];
    }
}
