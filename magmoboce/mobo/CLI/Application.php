<?php

declare(strict_types=1);

namespace MoBo\CLI;

use InvalidArgumentException;

final class Application
{
    /**
     * @var array<string, CommandInterface>
     */
    private array $commands = [];

    public function register(CommandInterface $command): void
    {
        $name = $command->getName();
        if (isset($this->commands[$name])) {
            throw new InvalidArgumentException("Command {$name} already registered.");
        }

        $this->commands[$name] = $command;
    }

    /**
     * @param list<string> $argv
     */
    public function run(array $argv): int
    {
        array_shift($argv); // script name
        $commandName = array_shift($argv);

        if ($commandName === null || $commandName === 'help' || $commandName === '--help') {
            $this->renderHelp();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            $this->renderError("Unknown command: {$commandName}");
            $this->renderHelp();
            return 1;
        }

        return $this->commands[$commandName]->execute($argv);
    }

    private function renderHelp(): void
    {
        fwrite(STDOUT, "MagMoBoCE CLI\n\nAvailable commands:\n");
        ksort($this->commands);
        foreach ($this->commands as $name => $command) {
            $description = $command->getDescription();
            fwrite(STDOUT, sprintf("  %-25s %s\n", $name, $description));
        }
        fwrite(STDOUT, "\nUsage: php mag <command> [options]\n");
    }

    private function renderError(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
