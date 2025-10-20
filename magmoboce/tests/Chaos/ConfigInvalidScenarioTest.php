<?php

declare(strict_types=1);

namespace Tests\Chaos;

use MoBo\Chaos\Scenarios\ConfigInvalidScenario;
use MoBo\Kernel;
use PHPUnit\Framework\TestCase;
use Tests\Kernel\KernelHarness;

final class ConfigInvalidScenarioTest extends TestCase
{
    private ?Kernel $kernel = null;

    protected function setUp(): void
    {
        parent::setUp();
        ob_start();
        try {
            $this->kernel = KernelHarness::bootWith(
                [],
                includeConfigured: true,
                configuredNames: ['MagDB'],
                configPath: __DIR__ . '/../Fixtures/config/test_magds_failover.php',
                logLevel: 'error'
            );
        } finally {
            ob_end_clean();
        }
    }

    protected function tearDown(): void
    {
        if ($this->kernel !== null) {
            KernelHarness::shutdown($this->kernel);
            $this->kernel = null;
        }

        parent::tearDown();
    }

    public function testScenarioDoesNotFailHard(): void
    {
        $scenario = new ConfigInvalidScenario();
        $result = $scenario->run();

        self::assertSame('config.invalid', $result->getName());
        self::assertContains($result->getStatus(), ['passed', 'skipped']);
    }
}
