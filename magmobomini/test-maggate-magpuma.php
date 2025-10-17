<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';

use Components\MagGate\Request;
use Components\MagGate\Response;

echo "🔥🔥🔥 TESTING MAGGATE + MAGPUMA 🐆🐆🐆\n\n";

// Boot the kernel
$kernel->boot();

// Get components
$magGate = $kernel->get('MagGate');
$magPuma = $kernel->get('MagPuma');

echo "✅ MagGate loaded\n";
echo "✅ MagPuma loaded\n\n";

// Define some test routes
$router = $magGate->router();

$router->get('/', function(Request $request) {
    return Response::json([
        'message' => 'Welcome to MagFlock!',
        'protected_by' => 'MagPuma 🐆',
    ]);
});

$router->get('/api/users', function(Request $request) {
    return Response::json([
        'users' => [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ],
    ]);
});

$router->get('/api/users/{id}', function(Request $request) {
    $id = $request->route('id');
    return Response::json([
        'user' => ['id' => $id, 'name' => 'User ' . $id],
    ]);
});

$router->post('/api/users', function(Request $request) {
    return Response::json([
        'message' => 'User created',
        'data' => $request->json(),
    ], 201);
});

echo "✅ Routes registered: " . $router->count() . "\n\n";

// Test 1: Normal request
echo "TEST 1: Normal GET request\n";
echo "----------------------------\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0';
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate, br';
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

$request = Request::capture();
$response = $magGate->handle($request);
echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . json_encode($response->getContent(), JSON_PRETTY_PRINT) . "\n\n";

// Test 2: SQL Injection attempt
echo "TEST 2: SQL Injection attempt\n";
echo "----------------------------\n";
$_SERVER['REQUEST_URI'] = '/api/users?id=1 OR 1=1';
$_GET['id'] = '1 OR 1=1';

$request = Request::capture();
$ipProfile = $magPuma->analyzeIP($request->ip());
$threatScore = $magPuma->analyzeThreat($request);

echo "IP Trust Score: " . $ipProfile->trustScore . "\n";
echo "Threat Level: " . $threatScore->threatLevel . "\n";
echo "Threats: " . implode(', ', $threatScore->getThreats()) . "\n\n";

// Test 3: Bot detection
echo "TEST 3: Bot detection\n";
echo "----------------------------\n";
$_SERVER['HTTP_USER_AGENT'] = 'curl/7.68.0';
$_SERVER['HTTP_ACCEPT'] = '*/*';
unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
unset($_SERVER['HTTP_ACCEPT_ENCODING']);

$request = Request::capture();
$botScore = $magPuma->detectBot($request);

echo "Is Bot: " . ($botScore->isBot ? 'YES' : 'NO') . "\n";
echo "Bot Type: " . $botScore->botType . "\n";
echo "Confidence: " . $botScore->confidence . "%\n\n";

// Test 4: Good bot (Googlebot)
echo "TEST 4: Good bot detection\n";
echo "----------------------------\n";
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

$request = Request::capture();
$botScore = $magPuma->detectBot($request);

echo "Is Good Bot: " . ($botScore->isGoodBot ? 'YES' : 'NO') . "\n";
echo "Bot Type: " . $botScore->botType . "\n";
echo "Confidence: " . $botScore->confidence . "%\n\n";

// Test 5: Fingerprinting
echo "TEST 5: Fingerprinting\n";
echo "----------------------------\n";
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0';
$_SERVER['HTTP_ACCEPT'] = 'text/html,application/json';
$_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
$_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate, br';

$request = Request::capture();
$fingerprint = $magPuma->fingerprint($request);

echo "Fingerprint: " . substr($fingerprint, 0, 32) . "...\n\n";

// Test 6: Adaptive response
echo "TEST 6: Adaptive response\n";
echo "----------------------------\n";
$ipProfile = $magPuma->analyzeIP('192.168.1.100');
$action = $magPuma->decide($ipProfile, $request);

echo "Action: " . $action->action . "\n";
echo "Rate Limit: " . ($action->getRateLimit() ?? 'none') . "\n";
echo "Should Log: " . ($action->shouldLog() ? 'YES' : 'NO') . "\n\n";

echo "🎉🎉🎉 ALL TESTS COMPLETE! 🐆🔥💪\n";