<?php

declare(strict_types=1);

namespace MoBo\Security;

use MoBo\ConfigManager;
use MoBo\EventBus;
use MoBo\Logger;

final class CapabilityGate
{
    private ConfigManager $config;
    private Logger $logger;
    private AuditWriter $auditWriter;
    private EventBus $eventBus;

    public function __construct(
        ConfigManager $config,
        Logger $logger,
        AuditWriter $auditWriter,
        EventBus $eventBus
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->auditWriter = $auditWriter;
        $this->eventBus = $eventBus;
    }

    /**
     * @param array<string, mixed> $context
     * @throws CapabilityDeniedException
     */
    public function assertAllowed(string $capability, string $actor = 'system', array $context = []): void
    {
        $registry = $this->config->get('security.capabilities', []);
        $allowed = is_array($registry) ? ($registry[$capability] ?? false) : false;

        if ($allowed === true) {
            return;
        }

        $this->handleDenied($capability, $actor, $context);

        throw new CapabilityDeniedException(
            $capability,
            $actor,
            $context
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function handleDenied(string $capability, string $actor, array $context): void
    {
        $payload = [
            'capability' => $capability,
            'actor' => $actor,
            'context' => $context,
        ];

        $this->logger->warning(
            "Capability denied: {$capability}",
            'SECURITY',
            $payload
        );

        $this->auditWriter->write(
            'kernel.capability.denied',
            $payload,
            [
                'user_id' => $context['user_id'] ?? null,
                'project_id' => $context['project_id'] ?? null,
                'org_id' => $context['org_id'] ?? null,
            ]
        );

        $this->eventBus->emit('security.capability_denied', [
            'capability' => $capability,
            'actor' => $actor,
            'context' => $context,
        ]);
    }
}
