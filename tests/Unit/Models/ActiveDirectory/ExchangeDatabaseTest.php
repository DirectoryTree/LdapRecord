<?php

namespace LdapRecord\Tests\Unit\Models\ActiveDirectory;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\ExchangeDatabase;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class ExchangeDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);

        ExchangeDatabase::clearBootedModels();
    }

    protected function tearDown(): void
    {
        DirectoryFake::tearDown();

        parent::tearDown();
    }

    public function test_configuration_context_scope_sets_the_query_base_dn()
    {
        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('read')->once()->andReturn([
                [
                    'dn' => '',
                    'configurationNamingContext' => ['CN=Configuration,DC=local,DC=com'],
                ],
            ])
        );

        $this->assertSame(
            'CN=Configuration,DC=local,DC=com',
            ExchangeDatabase::query()->toBase()->getDn()
        );
    }
}
