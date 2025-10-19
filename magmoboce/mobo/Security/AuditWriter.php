<?php

declare(strict_types=1);

namespace MoBo\Security;

use MoBo\ConfigManager;
use MoBo\Logger;

final class AuditWriter
{
    private ConfigManager $config;
    private Logger $logger;

    public function __construct(ConfigManager $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     */
    public function write(string $action, array $payload, array $context = []): void
    {
        $path = (string) $this->config->get('security.audit_log.path');
        if ($path === '') {
            $this->logger->error('Audit log path not configured', 'SECURITY');
            return;
        }

        $entry = $this->buildEntry($action, $payload, $context);

        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            $this->logger->error('Unable to create audit log directory', 'SECURITY', ['path' => $directory]);
            return;
        }

        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->logger->error('Failed to encode audit entry', 'SECURITY', ['action' => $action]);
            return;
        }

        $result = @file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            $this->logger->error('Failed to write audit entry', 'SECURITY', ['path' => $path]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildEntry(string $action, array $payload, array $context): array
    {
        $redactedPayload = $this->config->redact($payload);

        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c');

        return [
            'id' => $this->generateUuidV4(),
            'action' => $action,
            'org_id' => $context['org_id'] ?? null,
            'project_id' => $context['project_id'] ?? null,
            'user_id' => $context['user_id'] ?? null,
            'payload' => $redactedPayload,
            'ip_address' => $context['ip_address'] ?? null,
            'immutable' => true,
            'legal_hold_id' => $context['legal_hold_id'] ?? null,
            'created_at' => $timestamp,
        ];
    }

    private function generateUuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
