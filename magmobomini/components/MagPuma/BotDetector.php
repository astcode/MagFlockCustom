<?php

namespace Components\MagPuma;

use MoBo\Logger;
use Components\MagGate\Request;

class BotDetector
{
    private ?Logger $logger;
    
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger;
    }
    
    public function analyze(Request $request): BotScore
    {
        $score = new BotScore();
        
        // 1. Good Bots (Search engines, etc.)
        $score->isGoodBot = $this->isGoodBot($request);
        
        if ($score->isGoodBot) {
            $score->botType = $this->identifyGoodBot($request);
            $score->confidence = 95;
            return $score;
        }
        
        // 2. Bad Bot Detection
        $score->lacksBrowserFeatures = $this->lacksBrowserFeatures($request);
        $score->suspiciousUserAgent = $this->hasSuspiciousUserAgent($request);
        $score->automatedBehavior = $this->hasAutomatedBehavior($request);
        $score->headlessDetection = $this->isHeadless($request);
        
        // Calculate confidence
        $score->calculateConfidence();
        
        if ($score->isBot && $this->logger) {
            $this->logger->info("Bot detected", 'MAGPUMA', [
                'ip' => $request->ip(),
                'bot_type' => $score->botType,
                'confidence' => $score->confidence,
            ]);
        }
        
        return $score;
    }
    
    private function isGoodBot(Request $request): bool
    {
        $goodBots = [
            'Googlebot',
            'Bingbot',
            'Slurp', // Yahoo
            'DuckDuckBot',
            'Baiduspider',
            'YandexBot',
            'facebookexternalhit',
            'LinkedInBot',
            'Twitterbot',
            'Slackbot',
            'WhatsApp',
        ];
        
        $ua = $request->userAgent();
        
        foreach ($goodBots as $bot) {
            if (stripos($ua, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function identifyGoodBot(Request $request): string
    {
        $ua = $request->userAgent();
        
        if (stripos($ua, 'Googlebot') !== false) return 'googlebot';
        if (stripos($ua, 'Bingbot') !== false) return 'bingbot';
        if (stripos($ua, 'Slurp') !== false) return 'yahoo';
        if (stripos($ua, 'DuckDuckBot') !== false) return 'duckduckgo';
        if (stripos($ua, 'facebookexternalhit') !== false) return 'facebook';
        if (stripos($ua, 'LinkedInBot') !== false) return 'linkedin';
        if (stripos($ua, 'Twitterbot') !== false) return 'twitter';
        
        return 'unknown_good_bot';
    }
    
    private function lacksBrowserFeatures(Request $request): int
    {
        $score = 0;
        
        // Real browsers send Accept headers
        if (empty($request->header('Accept'))) {
            $score += 30;
        }
        
        // Real browsers send Accept-Language
        if (empty($request->header('Accept-Language'))) {
            $score += 25;
        }
        
        // Real browsers send Accept-Encoding
        if (empty($request->header('Accept-Encoding'))) {
            $score += 25;
        }
        
        // Real browsers send Connection
        if (empty($request->header('Connection'))) {
            $score += 20;
        }
        
        return min(100, $score);
    }
    
    private function hasSuspiciousUserAgent(Request $request): int
    {
        $ua = strtolower($request->userAgent());
        
        // Empty UA
        if (empty($ua)) {
            return 80;
        }
        
        $score = 0;
        
        // Known bot patterns
        $botPatterns = [
            'bot',
            'crawler',
            'spider',
            'scraper',
            'curl',
            'wget',
            'python',
            'java',
            'perl',
            'ruby',
            'go-http-client',
            'axios',
            'okhttp',
        ];
        
        foreach ($botPatterns as $pattern) {
            if (str_contains($ua, $pattern)) {
                $score += 40;
            }
        }
        
        // Very short UA (less than 20 chars)
        if (strlen($ua) < 20) {
            $score += 30;
        }
        
        // Missing version numbers
        if (!preg_match('/\d+\.\d+/', $ua)) {
            $score += 20;
        }
        
        return min(100, $score);
    }
    
    private function hasAutomatedBehavior(Request $request): int
    {
        $score = 0;
        
        // Perfect timing (bots often have consistent request timing)
        // This would require session tracking - simplified for MVP
        
        // Sequential resource access
        // This would require session tracking - simplified for MVP
        
        // No referrer on deep pages
        if (empty($request->header('Referer')) && $request->path() !== '/') {
            $score += 15;
        }
        
        return min(100, $score);
    }
    
    private function isHeadless(Request $request): int
    {
        $ua = $request->userAgent();
        
        $headlessIndicators = [
            'HeadlessChrome',
            'PhantomJS',
            'Selenium',
            'Puppeteer',
            'Playwright',
        ];
        
        foreach ($headlessIndicators as $indicator) {
            if (stripos($ua, $indicator) !== false) {
                return 100;
            }
        }
        
        return 0;
    }
}