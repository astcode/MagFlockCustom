<?php

declare(strict_types=1);

namespace Tests\Kernel;

use MoBo\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelResetTest extends TestCase
{
    protected function tearDown(): void
    {
        Kernel::resetInstance();
        parent::tearDown();
    }

    public function testResetAllowsReinitialization(): void
    {
        Kernel::resetInstance();

        ob_start();
        try {
            $kernel = Kernel::getInstance();
            $kernel->initialize(__DIR__ . '/../Fixtures/config/test_config.php');
            $this->assertTrue($kernel->boot());

            Kernel::resetInstance();

            $newKernel = Kernel::getInstance();
            $this->assertNotSame($kernel, $newKernel);

            $newKernel->initialize(__DIR__ . '/../Fixtures/config/test_config.php');
            $this->assertTrue($newKernel->boot());
        } finally {
            ob_end_clean();
        }
    }
}
