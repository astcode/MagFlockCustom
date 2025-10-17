<?php
namespace Components\MagView;

use MoBo\Contracts\AbstractComponent;

class MagView extends AbstractComponent {
    public function render(string $name, array $data = []): string {
        // super simple inline renderer for now
        if ($name === 'dashboard') {
            $title = $data['title'] ?? 'Dashboard';
            $sections = $data['sections'] ?? [];
            $html = "<!doctype html><html><head><meta charset='utf-8'><title>{$title}</title></head><body>";
            $html .= "<h1>{$title}</h1><ul>";
            foreach ($sections as $s) {
                $html .= "<li><strong>".htmlspecialchars($s['name']).":</strong> ".htmlspecialchars((string)$s['value'])."</li>";
            }
            $html .= "</ul></body></html>";
            return $html;
        }
        return "<html><body>Unknown view: ".htmlspecialchars($name)."</body></html>";
    }
}
