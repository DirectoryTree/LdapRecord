<?php

namespace LdapRecord\Tests;

use Mockery\Adapter\Phpunit\MockeryTestCase;

class TestCase extends MockeryTestCase
{
    protected function setUp() : void
    {
        ini_set('date.timezone', 'UTC');
    }
}
