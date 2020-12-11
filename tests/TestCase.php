<?php

namespace LdapRecord\Tests;

use LdapRecord\Container;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class TestCase extends MockeryTestCase
{
    protected function setUp() : void
    {
        ini_set('date.timezone', 'UTC');
    }

    protected function tearDown(): void
    {
        Container::reset();

        parent::tearDown();
    }
}
