<?php

namespace Components\MagPuma;

class BotScore
{
    public bool $isGoodBot = false;
    public bool $isBot = false;
    public string $botType = 'unknown';
    public int $lacksBrowserFeatures = 0;
    public int $suspiciousUserAgent = 0;
    public int $automatedBehavior = 0;
    public int $headlessDetection = 0;
    public int $confidence = 0;
    
    public function calculateConfidence(): void
    {
        if ($this->isGoodBot) {
            $this->confidence = 95;
            return;
        }
        
        // Average all scores
        $this->confidence = (int) ((
            $this->lacksBrowserFeatures +
            $this->suspiciousUserAgent +
            $this->automatedBehavior +
            $this->headlessDetection
        ) / 4);
        
        $this->isBot = $this->confidence >= 50;
        
        if ($this->isBot) {
            if ($this->headlessDetection > 0) {
                $this->botType = 'headless_browser';
            } elseif ($this->suspiciousUserAgent > 50) {
                $this->botType = 'automated_script';
            } else {
                $this->botType = 'unknown_bot';
            }
        }
    }
    
    public function toArray(): array
    {
        return [
            'is_good_bot' => $this->isGoodBot,
            'is_bot' => $this->isBot,
            'bot_type' => $this->botType,
            'confidence' => $this->confidence,
            'lacks_browser_features' => $this->lacksBrowserFeatures,
            'suspicious_user_agent' => $this->suspiciousUserAgent,
            'automated_behavior' => $this->automatedBehavior,
            'headless_detection' => $this->headlessDetection,
        ];
    }
}