<?php

declare(strict_types=1);

namespace MoBo\CLI\Commands;

use MoBo\CLI\AbstractCommand;
use MoBo\CLI\Arguments;
use MoBo\Chaos\ChaosScenarioFactory;
use MoBo\Chaos\ChaosScenarioRunner;
use MoBo\Kernel;

final class MagDSChaosCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('magds:chaos', 'Run MagDS chaos scenarios (db down, latency, component crash, config invalid)');
    }

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int
    {
        $parsed = Arguments::parse($args);
        $positionals = $parsed['positionals'];
        $options = $parsed['options'];

        $action = $positionals[0] ?? 'run';

        if (!in_array($action, ['run', 'list'], true)) {
            $this->error('Usage: magds:chaos [list|run] [--scenario=name1,name2] [--report=path]');
            return 1;
        }

        $kernel = Kernel::getInstance();
        $scenarios = ChaosScenarioFactory::buildDefaultScenarios($kernel);
        $runner = new ChaosScenarioRunner($kernel->getLogger(), $kernel->getTelemetry());
        $runner->registerScenarios($scenarios);

        if ($action === 'list') {
            $this->renderList($runner);
            return 0;
        }

        $selected = null;
        if (isset($options['scenario'])) {
            $selected = array_filter(array_map('trim', explode(',', (string) $options['scenario'])));
        }

        $results = $runner->run($selected);
        $this->renderResults($results);

        if (isset($options['report'])) {
            $reportPath = (string) $options['report'];
            if ($reportPath === 'auto') {
                $timestamp = gmdate('Ymd_His');
                $reportPath = dirname(__DIR__, 3) . '/docs/ops/ChaosReports/' . $timestamp . '.json';
            }

            try {
                $runner->writeReport($results, $reportPath);
                $this->writeln('Report written to ' . $reportPath);
            } catch (\Throwable $exception) {
                $this->error('Unable to write chaos report: ' . $exception->getMessage());
            }
        }

        foreach ($results as $result) {
            if ($result->getStatus() === 'failed') {
                return 1;
            }
        }

        return 0;
    }

    private function renderList(ChaosScenarioRunner $runner): void
    {
        $this->writeln('Available chaos scenarios:');
        foreach ($runner->getScenarios() as $scenario) {
            $this->writeln(sprintf(' - %-20s %s', $scenario->getName(), $scenario->getDescription()));
        }
    }

    /**
     * @param list<\MoBo\Chaos\ChaosScenarioResult> $results
     */
    private function renderResults(array $results): void
    {
        $this->writeln(str_repeat('-', 70));
        $this->writeln(sprintf('%-25s %-10s %-12s %s', 'Scenario', 'Status', 'Duration', 'Message'));
        $this->writeln(str_repeat('-', 70));

        foreach ($results as $result) {
            $this->writeln(sprintf(
                '%-25s %-10s %-12s %s',
                $result->getName(),
                $result->getStatus(),
                sprintf('%.2f ms', $result->getDurationMs()),
                $result->getMessage()
            ));
        }

        $this->writeln(str_repeat('-', 70));
    }
}
