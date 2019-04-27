<?php

namespace LdapRecord\Tests;

use LdapRecord\Manager;
use LdapRecord\Connections\Ldap;
use LdapRecord\Connections\Provider;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Connections\ProviderInterface;

class LdapRecordTest extends TestCase
{
    public function test_construct()
    {
        $providers = [
            'first' => new Provider(),
            'second' => new Provider(),
        ];

        $ad = new Manager($providers);

        $this->assertEquals($providers['first'], $ad->getProvider('first'));
        $this->assertEquals($providers['second'], $ad->getProvider('second'));
    }

    public function test_add_provider_with_configuration_instance()
    {
        $ad = new Manager();

        $ad->addProvider(new DomainConfiguration(), 'first');

        $this->assertInstanceOf(ProviderInterface::class, $ad->getProvider('first'));
    }

    public function test_add_provider_with_configuration_array()
    {
        $ad = new Manager();

        $ad->addProvider([], 'first');

        $this->assertInstanceOf(ProviderInterface::class, $ad->getProvider('first'));
    }

    public function test_get_providers()
    {
        $providers = [
            'first' => new Provider(),
            'second' => new Provider(),
        ];

        $ad = new Manager($providers);

        $this->assertEquals($providers, $ad->getProviders());
    }

    public function test_get_provider()
    {
        $provider = $this->mock(Provider::class);

        $ad = new Manager([
            'default' => $provider,
        ]);

        $this->assertEquals($provider, $ad->getProvider('default'));
    }

    public function test_get_default_provider()
    {
        $ad = new Manager();

        $provider = new Provider();

        $ad->addProvider($provider, 'new')
            ->setDefaultProvider('new');

        $this->assertInstanceOf(Provider::class, $ad->getDefaultProvider());
    }

    public function test_connect()
    {
        $connection = $this->mock(Ldap::class);

        $connection->shouldReceive('connect')->once()->andReturn(true)
            ->shouldReceive('setOptions')->once()
            ->shouldReceive('bind')->once()->andReturn(true)
            ->shouldReceive('isBound')->once()->andReturn(true)
            ->shouldReceive('close')->once()->andReturn(true);

        $ad = new Manager();

        $provider = new Provider([], $connection);

        $ad->addProvider($provider);

        $this->assertInstanceOf(Provider::class, $ad->connect('default'));
    }

    public function test_invalid_default_provider()
    {
        $this->expectException(\LdapRecord\LdapRecordException::class);

        $ad = new Manager();

        $ad->getDefaultProvider();
    }

    public function test_invalid_provider()
    {
        $this->expectException(\InvalidArgumentException::class);

        $ad = new Manager();

        $ad->addProvider('first', 'invalid');
    }

    public function test_the_first_provider_is_set_as_default()
    {
        $ad = new Manager([
            'test1' => [
                'hosts' => ['test1.dc']
            ],
            'test2' => [
                'hosts' => ['test2.dc']
            ],
        ]);

        $provider = $ad->getDefaultProvider();

        $this->assertEquals('test1', $provider->getConnection()->getName());
        $this->assertEquals('test1.dc', $provider->getConfiguration()->get('hosts')[0]);
    }

    public function test_adding_providers_sets_connection_name()
    {
        $ad = new Manager();

        $ad->addProvider(new DomainConfiguration(), 'domain-a');

        $this->assertEquals('domain-a', $ad->getProvider('domain-a')->getConnection()->getName());
    }
}
