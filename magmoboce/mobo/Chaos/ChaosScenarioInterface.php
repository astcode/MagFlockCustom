<?php

declare(strict_types=1);

namespace MoBo\Chaos;

interface ChaosScenarioInterface
{
    public function getName(): string;

    public function getDescription(): string;

    public function run(): ChaosScenarioResult;
}
