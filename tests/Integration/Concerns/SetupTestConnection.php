<?php

namespace LdapRecord\Tests\Integration\Concerns;

use LdapRecord\Container;

trait SetupTestConnection
{
    use CreatesTestConnection;

    protected function setUpTestConnection()
    {
        Container::addConnection($this->makeConnection());
    }
}
