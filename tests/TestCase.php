<?php

namespace LdapRecord\Tests;

use LdapRecord\Container;
use Mockery\Adapter\Phpunit\MockeryTestCase;

class TestCase extends MockeryTestCase
{
    /**
     * Set up the test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // We will override the timezone while running our tests to ensure
        // we are using a consistent test environment, since we will
        // be testing various date/time related functions.
        ini_set('date.timezone', 'UTC');

        Container::getNewInstance();
    }
}
