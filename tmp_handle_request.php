<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$request = Illuminate\Http\Request::create('/api/custom-message/send', 'POST', [
    'to' => '+1234567890',
    'message' => 'Hello from POS',
]);

$response = $app->handle($request);
echo $response->getStatusCode() . PHP_EOL;
echo $response->getContent() . PHP_EOL;
