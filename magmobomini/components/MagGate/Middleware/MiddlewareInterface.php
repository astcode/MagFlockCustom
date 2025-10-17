<?php

namespace Components\MagGate\Middleware;

use Components\MagGate\Request;
use Components\MagGate\Response;

interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}