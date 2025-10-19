<?php

declare(strict_types=1);

return [
    'system.boot' => [],
    'system.boot_failed' => ['error'],
    'system.ready' => [],
    'system.shutdown' => ['timeout'],

    'component.registered' => ['name'],
    'component.state_changed' => ['name', 'old_state', 'new_state'],
    'component.started' => ['name'],
    'component.stopped' => ['name'],
    'component.failed' => ['name', 'error'],
    'component.recovery_failed' => ['name', 'restart_count'],

    'health.status_changed' => ['name', 'old_status', 'new_status'],
    'health.failed' => ['name', 'error'],
    'health.check_complete' => ['system', 'components'],

    'magdb.failover.detected' => ['previous', 'reason', 'target'],
    'magdb.failover.completed' => ['new_primary', 'reason'],
    'magdb.failover.failed' => ['reason', 'target'],

    'config.reloaded' => ['version', 'changed_keys'],
    'config.reload_failed' => ['error'],
    'security.capability_denied' => ['capability', 'actor', 'context'],
];
