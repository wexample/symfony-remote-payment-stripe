<?php

namespace Wexample\SymfonyRemotePaymentStripe\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyRemotePaymentStripe\Class\StripePaymentProvider;

class KernelBootTest extends KernelTestCase
{
    public function testStripeIsAProviderAndARemote(): void
    {
        self::bootKernel();
        $registry = static::getContainer()->get('test.registry');

        $this->assertInstanceOf(StripePaymentProvider::class, $registry->getGateway('stripe'));
        $this->assertSame('stripe', $registry->findGatewayForMethod('bancontact')->getName());
        $this->assertArrayHasKey('stripe', $registry->getBalanceReaders());
        $this->assertSame('stripe', static::getContainer()->get('test.remotes')->get('stripe')->getKey());
    }
}
