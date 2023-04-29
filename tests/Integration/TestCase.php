<?php

namespace LdapRecord\Tests\Integration;

use Faker\Factory;
use Faker\Generator;
use LdapRecord\Tests\Integration\Concerns\SetupTestConnection;
use LdapRecord\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected ?Generator $faker = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(SetupTestConnection::class, class_uses($this))) {
            $this->setUpTestConnection();
        }
    }

    protected function faker(): Generator
    {
        return $this->faker ??= Factory::create();
    }
}
