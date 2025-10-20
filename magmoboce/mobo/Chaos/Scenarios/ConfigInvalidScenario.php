<?php

declare(strict_types=1);

namespace MoBo\Chaos\Scenarios;

use MoBo\Chaos\ChaosScenarioInterface;
use MoBo\Chaos\ChaosScenarioResult;
use MoBo\Kernel;

final class ConfigInvalidScenario implements ChaosScenarioInterface
{
    public function getName(): string
    {
        return 'config.invalid';
    }

    public function getDescription(): string
    {
        return 'Applies an invalid configuration override to ensure reload fails safely.';
    }

    public function run(): ChaosScenarioResult
    {
        $kernel = Kernel::getInstance();
        $configLoader = $kernel->getConfigLoader();

        if ($configLoader === null) {
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_SKIPPED,
                0.0,
                'Layered configuration loader unavailable; skipping.',
                []
            );
        }

        $environment = getenv('MOBO_ENV') ?: 'development';
        $configDir = $configLoader->getConfigRoot();
        $overridePath = $configDir . '/environments/' . $environment . '.php';

        $original = file_exists($overridePath) ? file_get_contents($overridePath) : null;
        $restore = function () use ($overridePath, $original): void {
            if ($original === null) {
                if (file_exists($overridePath)) {
                    @unlink($overridePath);
                }
            } else {
                file_put_contents($overridePath, $original);
            }
        };

        $startedAt = microtime(true);

        try {
            $payload = [
                'kernel' => ['name' => 123], // invalid, must be string
            ];
            $export = "<?php\n\nreturn " . var_export($payload, true) . ";\n";
            $directory = dirname($overridePath);
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                return new ChaosScenarioResult(
                    $this->getName(),
                    ChaosScenarioResult::STATUS_SKIPPED,
                    0.0,
                    'Unable to create environment override directory.',
                    []
                );
            }
            file_put_contents($overridePath, $export);

            $result = $kernel->reloadConfig();
        } finally {
            $restore();
        }

        $durationMs = (microtime(true) - $startedAt) * 1000;

        if ($result === false) {
            return new ChaosScenarioResult(
                $this->getName(),
                ChaosScenarioResult::STATUS_PASSED,
                $durationMs,
                'Invalid configuration rejected and previous state retained.',
                []
            );
        }

        return new ChaosScenarioResult(
            $this->getName(),
            ChaosScenarioResult::STATUS_FAILED,
            $durationMs,
            'Kernel accepted invalid configuration unexpectedly.',
            []
        );
    }
}
