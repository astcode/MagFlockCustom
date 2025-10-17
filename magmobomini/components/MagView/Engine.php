<?php

namespace Components\MagView;

use MoBo\Logger;

class Engine
{
    private string $viewsPath;
    private string $cachePath;
    private bool $cacheEnabled;
    private Logger $logger;
    private array $directives = [];
    private array $shared = [];

    public function __construct(string $viewsPath, string $cachePath, bool $cacheEnabled, Logger $logger)
    {
        $this->viewsPath = rtrim($viewsPath, '/');
        $this->cachePath = rtrim($cachePath, '/');
        $this->cacheEnabled = $cacheEnabled;
        $this->logger = $logger;
    }

    public function render(string $view, array $data = []): string
    {
        $viewPath = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.mag.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view} ({$viewPath})");
        }

        $cachePath = $this->cachePath . '/' . md5($view) . '.php';

        // Check if cache is valid
        if ($this->cacheEnabled && file_exists($cachePath) && filemtime($cachePath) >= filemtime($viewPath)) {
            return $this->evaluate($cachePath, array_merge($this->shared, $data));
        }

        // Compile template
        $compiled = $this->compile(file_get_contents($viewPath));

        // Save to cache
        if ($this->cacheEnabled) {
            file_put_contents($cachePath, $compiled, LOCK_EX);
        }

        return $this->evaluate($cachePath, array_merge($this->shared, $data));
    }

    private function compile(string $template): string
    {
        // {{-- comment --}} - comments (do this FIRST)
        $template = preg_replace('/\{\{--.*?--\}\}/s', '', $template);

        // Process custom directives
        foreach ($this->directives as $name => $handler) {
            // Directives with parameters
            $pattern = '/@' . $name . '\s*\(((?:[^()]+|\((?:[^()]+|\([^()]*\))*\))*)\)/s';
            $template = preg_replace_callback($pattern, function($matches) use ($handler) {
                return $handler($matches[1] ?? '');
            }, $template);

            // Directives without parameters
            $pattern = '/@' . $name . '(?!\w)/';
            $template = preg_replace_callback($pattern, function($matches) use ($handler) {
                return $handler('');
            }, $template);
        }

        // {!! $variable !!} - raw output (do this BEFORE escaped output)
        $template = preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $template);

        // {{ $variable }} - escaped output
        $template = preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo htmlspecialchars((string)($1), ENT_QUOTES, \'UTF-8\'); ?>', $template);

        return $template;
    }

    private function evaluate(string $path, array $data): string
    {
        extract($data);
        $__engine = $this;

        ob_start();
        include $path;
        return ob_get_clean();
    }

    public function directive(string $name, callable $handler): void
    {
        $this->directives[$name] = $handler;
    }

    public function share(string $key, $value): void
    {
        $this->shared[$key] = $value;
    }

    public function getShared(): array
    {
        return $this->shared;
    }
}