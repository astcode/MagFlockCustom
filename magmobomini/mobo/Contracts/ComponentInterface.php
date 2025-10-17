<?php

namespace MoBo\Contracts;

interface ComponentInterface
{
    /**
     * Get component name
     */
    public function getName(): string;

    /**
     * Get component version
     */
    public function getVersion(): string;

    /**
     * Get component dependencies
     * @return array Array of component names this component depends on
     */
    public function getDependencies(): array;

    /**
     * Configure the component
     * @param array $config Configuration array
     */
    public function configure(array $config): void;

    /**
     * Boot the component (initialize resources)
     */
    public function boot(): void;

    /**
     * Start the component (begin operations)
     */
    public function start(): void;

    /**
     * Stop the component (pause operations)
     */
    public function stop(): void;

    /**
     * Get component health status
     * @return array Health status information
     */
    public function health(): array;

    /**
     * Attempt to recover from failure
     * @return bool True if recovery successful
     */
    public function recover(): bool;

    /**
     * Shutdown the component gracefully
     * @param int $timeout Timeout in seconds
     */
    public function shutdown(int $timeout = 30): void;
}