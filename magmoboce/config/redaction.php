<?php

declare(strict_types=1);

return [
    'logging.redact_keys',
    'database.connections.*.password',
    'database.connections.*.username',
    'services.*.secret',
    'services.*.key',
];
