<?php

$vendorAutoload = __DIR__.'/../vendor/autoload.php';

if (! file_exists($vendorAutoload)) {
    throw new RuntimeException('Composer autoload not found. Run "composer install" first.');
}

require $vendorAutoload;
