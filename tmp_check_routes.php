<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (app('router')->getRoutes()->getRoutes() as $route) {
    if (str_contains($route->uri(), 'custom-message')) {
        echo $route->uri() . PHP_EOL;
    }
}
