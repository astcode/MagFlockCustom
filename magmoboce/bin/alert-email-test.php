#!/usr/bin/env php
<?php

declare(strict_types=1);

$options = getopt('', ['to::', 'subject::', 'body::']);

$to = $options['to'] ?? getenv('ALERT_TEST_TO') ?: getenv('MAIL_ALERT_TO') ?: 'oncall@magmoboce.local';
$from = getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@magmoboce.local';
$fromName = getenv('MAIL_FROM_NAME') ?: 'MagMoBoCE Alerts';
$subject = $options['subject'] ?? '[MagMoBoCE][TEST] Alert pipeline check';
$body = $options['body'] ?? "This is a MagMoBoCE alert pipeline test message.\n\nTimestamp: " . gmdate('c');

$headers = [
    sprintf('From: %s <%s>', $fromName, $from),
    'Content-Type: text/plain; charset=UTF-8',
    'X-MagMoBo-Alert: test',
];

$success = mail($to, $subject, $body, implode("\r\n", $headers));

if ($success) {
    fwrite(STDOUT, "[alert-email-test] Test message dispatched to {$to}\n");
    exit(0);
}

fwrite(STDERR, "[alert-email-test] Failed to send test message to {$to}\n");
exit(1);
