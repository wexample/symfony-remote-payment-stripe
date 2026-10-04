<?php

namespace Wexample\SymfonyRemotePaymentStripe\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_remote_payment_stripe');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('name')->defaultValue('stripe')->info('Provider name, as payments and the remote registry show it.')->end()
                ->scalarNode('secret_key')->defaultNull()->info('sk_live_… / sk_test_…; the remote reads Unconfigured while it is empty.')->end()
                ->scalarNode('webhook_secret')->defaultNull()->info('whsec_… of the endpoint /_payment/webhook/<name>.')->end()
                ->arrayNode('methods')
                    ->info('Stripe payment method types offered: card, sepa_debit, bancontact…')
                    ->scalarPrototype()->end()
                    ->defaultValue(['card'])
                ->end()
            ->end();

        return $treeBuilder;
    }
}
