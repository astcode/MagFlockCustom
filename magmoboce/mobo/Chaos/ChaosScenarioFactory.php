<?php

declare(strict_types=1);

namespace MoBo\Chaos;

use Components\MagDB\MagDB;
use MoBo\Chaos\Scenarios\ComponentCrashScenario;
use MoBo\Chaos\Scenarios\ConfigInvalidScenario;
use MoBo\Chaos\Scenarios\DbDownScenario;
use MoBo\Chaos\Scenarios\DbLatencyScenario;
use MoBo\Kernel;

final class ChaosScenarioFactory
{
    /**
     * @return array<string, ChaosScenarioInterface>
     */
    public static function buildDefaultScenarios(Kernel $kernel): array
    {
        $magdb = $kernel->get('MagDB');

        $scenarios = [];

        if ($magdb instanceof MagDB) {
            $scenarios[] = new DbDownScenario($magdb);
            $scenarios[] = new DbLatencyScenario($magdb);
        }

        $scenarios[] = new ComponentCrashScenario($kernel->getLifecycle());
        $scenarios[] = new ConfigInvalidScenario();

        $map = [];
        foreach ($scenarios as $scenario) {
            $map[$scenario->getName()] = $scenario;
        }

        return $map;
    }
}
