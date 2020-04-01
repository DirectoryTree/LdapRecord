<?php

namespace LdapRecord\Tests;

use Mockery\Adapter\Phpunit\MockeryTestCase;

class TestCase extends MockeryTestCase
{
    protected function setUp()
    {
        date_default_timezone_set('UTC');
        parent::setUp();
    }
}
