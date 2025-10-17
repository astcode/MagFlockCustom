<?php

namespace Components\MagGate;

class Response
{
    private $content;
    private int $statusCode;
    private array $headers;
    
    public function __construct($content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }
    
    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);
        
        // Determine content type
        if (is_array($this->content) || is_object($this->content)) {
            $this->headers['Content-Type'] = 'application/json';
            $this->content = json_encode($this->content, JSON_PRETTY_PRINT);
        }
        
        // Set default content type
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'text/html; charset=UTF-8';
        }
        
        // Send headers
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }
        
        // Send content
        echo $this->content;
    }
    
    public function header(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }
    
    public function headers(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    public function isError(): bool
    {
        return $this->statusCode >= 400;
    }
    
    public function getContent()
    {
        return $this->content;
    }
    
    public static function json($data, int $statusCode = 200): self
    {
        return new self($data, $statusCode, ['Content-Type' => 'application/json']);
    }
    
    public static function html(string $html, int $statusCode = 200): self
    {
        return new self($html, $statusCode, ['Content-Type' => 'text/html']);
    }
    
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, ['Location' => $url]);
    }
}