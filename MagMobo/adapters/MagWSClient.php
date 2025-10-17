<?php
namespace Adapters;

class MagWSClient {
    public function publish(string $topic, array $payload): bool {
        // Stub for now; later: bridge to your Docker WS component
        return true;
    }
    public function subscribe(string $topic): bool {
        return true;
    }
}
