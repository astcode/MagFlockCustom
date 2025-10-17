<?php
namespace Components\MagPuma;

use MoBo\Contracts\AbstractComponent;

class MagPuma extends AbstractComponent {
    public function analyzeRequest(array $req): array {
        // naive scoring stub
        $signals = [];
        $level = 0; $trust = 100;

        $q = $req['path'] ?? '';
        if (preg_match('/(drop|union|--)/i', $q)) {
            $signals[] = 'sql_injection_pattern';
            $level = 80; $trust = 10;
        }

        return ['signals' => $signals, 'level' => $level, 'trust' => $trust];
    }

    public function detectBot(string $ua): array {
        $uaL = strtolower($ua);
        if (str_contains($uaL, 'googlebot')) {
            return ['is_bot' => true, 'is_good_bot' => true, 'type' => 'googlebot', 'confidence' => 95];
        }
        return ['is_bot' => false, 'is_good_bot' => false, 'type' => 'unknown', 'confidence' => 38];
    }

    public function fingerprint(array $ctx): array {
        $raw = ($ctx['ip'] ?? '') . '|' . ($ctx['ua'] ?? '') . '|' . ($ctx['accept'] ?? '');
        return ['hash' => md5($raw)];
    }

    public function policyFor(array $req): array {
        return ['action' => 'allow', 'rate_limit' => '1000/minute', 'log' => false];
    }
}
