<?php

namespace Wexample\SymfonyRemotePaymentStripe\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyRemotePaymentStripe\Class\StripePaymentProvider;

class WexampleSymfonyRemotePaymentStripeExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        // Autoconfigured: tagged as a payment provider and as a remote.
        $container->setDefinition(
            StripePaymentProvider::class,
            (new Definition(StripePaymentProvider::class, [
                $config['secret_key'],
                $config['webhook_secret'],
                $config['methods'],
                $config['name'],
            ]))
                ->setAutoconfigured(true)
                ->setPublic(true)
        );
    }
}
