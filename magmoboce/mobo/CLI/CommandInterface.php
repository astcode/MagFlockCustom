<?php

declare(strict_types=1);

namespace MoBo\CLI;

interface CommandInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param list<string> $args
     */
    public function execute(array $args): int;
}
