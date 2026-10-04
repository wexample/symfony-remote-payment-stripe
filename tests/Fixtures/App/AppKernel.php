<?php

namespace Wexample\SymfonyRemotePaymentStripe\Tests\Fixtures\App;

use Wexample\SymfonyRemote\WexampleSymfonyRemoteBundle;
use Wexample\SymfonyRemotePayment\WexampleSymfonyRemotePaymentBundle;
use Wexample\SymfonyRemotePaymentStripe\WexampleSymfonyRemotePaymentStripeBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new WexampleSymfonyRemoteBundle(),
            new WexampleSymfonyRemotePaymentBundle(),
            new WexampleSymfonyRemotePaymentStripeBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [__DIR__.'/config/config.yaml'];
    }
}
