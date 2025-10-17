<?php

namespace Components\MagGate;

class Request
{
    private array $query;
    private array $post;
    private array $server;
    private array $headers;
    private array $cookies;
    private array $files;
    private ?string $body = null;
    private array $routeParams = [];
    private float $startTime;
    
    public function __construct()
    {
        $this->query = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;
        $this->cookies = $_COOKIE;
        $this->files = $_FILES;
        $this->headers = $this->parseHeaders();
        $this->startTime = microtime(true);
    }
    
    public static function capture(): self
    {
        return new self();
    }
    
    private function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($this->server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[$header] = $value;
            }
        }
        
        // Add special headers
        if (isset($this->server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $this->server['CONTENT_TYPE'];
        }
        
        if (isset($this->server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $this->server['CONTENT_LENGTH'];
        }
        
        return $headers;
    }
    
    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }
    
    public function path(): string
    {
        $path = $this->server['REQUEST_URI'] ?? '/';
        
        // Remove query string
        if (($pos = strpos($path, '?')) !== false) {
            $path = substr($path, 0, $pos);
        }
        
        return $path;
    }
    
    public function url(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $this->path();
    }
    
    public function fullUrl(): string
    {
        return $this->url() . ($this->server['QUERY_STRING'] ?? '');
    }
    
    public function isSecure(): bool
    {
        return !empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off';
    }
    
    public function ip(): string
    {
        // Check for proxy headers
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        
        if (!empty($this->server['HTTP_X_REAL_IP'])) {
            return $this->server['HTTP_X_REAL_IP'];
        }
        
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }
    
    public function header(string $key, $default = null)
    {
        $key = strtoupper(str_replace('-', '_', $key));
        return $this->headers[$key] ?? $default;
    }
    
    public function headers(): array
    {
        return $this->headers;
    }
    
    public function query(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->query;
        }
        
        return $this->query[$key] ?? $default;
    }
    
    public function post(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->post;
        }
        
        return $this->post[$key] ?? $default;
    }
    
    public function input(?string $key = null, $default = null)
    {
        // Check POST first, then query
        if ($key === null) {
            return array_merge($this->query, $this->post);
        }
        
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }
    
    public function json(?string $key = null, $default = null)
    {
        if ($this->body === null) {
            $this->body = file_get_contents('php://input');
        }
        
        $data = json_decode($this->body, true);
        
        if ($key === null) {
            return $data;
        }
        
        return $data[$key] ?? $default;
    }
    
    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }
    
    public function server(string $key, $default = null)
    {
        return $this->server[$key] ?? $default;
    }
    
    public function file(string $key)
    {
        return $this->files[$key] ?? null;
    }
    
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) && $this->files[$key]['error'] === UPLOAD_ERR_OK;
    }
    
    public function wantsJson(): bool
    {
        $accept = $this->header('Accept', '');
        return str_contains($accept, 'application/json') || $this->isHtmx();
    }
    
    public function isHtmx(): bool
    {
        return $this->header('HX-Request') === 'true';
    }
    
    public function isAjax(): bool
    {
        return $this->header('X-Requested-With') === 'XMLHttpRequest';
    }
    
    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }
    
    public function route(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->routeParams;
        }
        
        return $this->routeParams[$key] ?? $default;
    }
    
    public function getElapsedTime(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }
}