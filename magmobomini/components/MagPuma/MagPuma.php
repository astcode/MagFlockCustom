<?php

namespace Components\MagPuma;

use MoBo\Contracts\ComponentInterface;
use MoBo\Logger;
use MoBo\EventBus;
use Components\MagDB\MagDB;

class MagPuma implements ComponentInterface
{
    private IPIntelligence $ipIntel;
    private ThreatAnalyzer $threatAnalyzer;
    private BotDetector $botDetector;
    private DDoSDetector $ddosDetector;
    private FingerprintEngine $fingerprint;
    private AdaptiveResponse $adaptive;
    private array $config = [];
    private ?Logger $logger = null;
    private ?EventBus $eventBus = null;
    private ?MagDB $db = null;
    private bool $isBooted = false;
    
    public function getName(): string
    {
        return 'MagPuma';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function getDependencies(): array
    {
        return ['MagDB'];
    }
    
    public function configure(array $config): void
    {
        $this->config = $config;
        
        if ($this->logger) {
            $this->logger->info("MagPuma configured", 'MAGPUMA');
        }
    }
    
    public function boot(): void
    {
        // Load config if not already loaded
        if (empty($this->config)) {
            $configPath = __DIR__ . '/config.php';
            if (file_exists($configPath)) {
                $this->config = require $configPath;
            } else {
                throw new \RuntimeException("MagPuma config not found at: {$configPath}");
            }
        }
        
        // Initialize analyzers
        $this->ipIntel = new IPIntelligence($this->db, $this->logger);
        $this->threatAnalyzer = new ThreatAnalyzer($this->logger);
        $this->botDetector = new BotDetector($this->logger);
        $this->ddosDetector = new DDoSDetector($this->db, $this->logger);
        $this->fingerprint = new FingerprintEngine($this->logger);
        $this->adaptive = new AdaptiveResponse($this->logger);
        
        $this->isBooted = true;
        
        if ($this->logger) {
            $this->logger->info("🐆 MagPuma booted - The Gatekeeper is watching", 'MAGPUMA');
        }
    }
    
    public function start(): void
    {
        if ($this->logger) {
            $this->logger->info("🐆 MagPuma started - Protection active", 'MAGPUMA');
        }
    }
    
    public function stop(): void
    {
        if ($this->logger) {
            $this->logger->info("MagPuma stopped", 'MAGPUMA');
        }
    }
    
    public function health(): array
    {
        return [
            'status' => 'healthy',
            'watching' => true,
        ];
    }
    
    public function recover(): bool
    {
        return true;
    }
    
    public function shutdown(int $timeout = 30): void
    {
        $this->stop();
    }
    
    // Public API
    
    public function analyzeIP(string $ip): IPProfile
    {
        return $this->ipIntel->analyze($ip);
    }
    
    public function analyzeThreat($request): ThreatScore
    {
        return $this->threatAnalyzer->analyze($request);
    }
    
    public function detectBot($request): BotScore
    {
        return $this->botDetector->analyze($request);
    }
    
    public function detectDDoS($request): bool
    {
        return $this->ddosDetector->detect($request);
    }
    
    public function fingerprint($request): string
    {
        return $this->fingerprint->generate($request);
    }
    
    public function decide(IPProfile $profile, $request): ResponseAction
    {
        return $this->adaptive->decide($profile, $request);
    }
}