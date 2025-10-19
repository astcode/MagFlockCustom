<?php

declare(strict_types=1);

namespace MoBo\CLI;

abstract class AbstractCommand implements CommandInterface
{
    public function __construct(
        private readonly string $name,
        private readonly string $description = ''
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    protected function write(string $message): void
    {
        fwrite(STDOUT, $message);
    }

    protected function writeln(string $message = ''): void
    {
        $this->write($message . PHP_EOL);
    }

    protected function error(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }
}
