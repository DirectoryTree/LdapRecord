<?php

namespace LdapRecord\Tests\Integration;

use Faker\Factory;
use Faker\Generator;
use LdapRecord\Connection;
use LdapRecord\Models\Model;
use LdapRecord\Tests\Integration\Fixtures\Group;
use LdapRecord\Tests\Integration\Fixtures\User;
use LdapRecord\Tests\TestCase as BaseTestCase;

class TestCase extends BaseTestCase
{
    protected ?Generator $faker = null;

    protected function faker(): Generator
    {
        return $this->faker ?? Factory::create();
    }

    protected function makeConnection(array $params = []): Connection
    {
        return new Connection(array_merge([
            'hosts' => ['localhost'],
            'base_dn' => 'dc=local,dc=com',
            'username' => 'cn=admin,dc=local,dc=com',
            'password' => 'secret',
            'use_ssl' => true,
        ], $params));
    }

    protected function createUser(Model $ou, array $attributes = []): User
    {
        $user = (new User())
            ->inside($ou)
            ->fill(array_merge([
                'uid' => $this->faker()->userName(),
                'givenName' => $firstName = $this->faker()->firstName(),
                'sn' => $lastName = $this->faker()->lastName(),
                'cn' => "$firstName $lastName",
                'uidNumber' => $this->faker()->numberBetween(1000, 2000),
                'gidNumber' => $this->faker()->numberBetween(1000, 2000),
                'homeDirectory' => '/foo',
            ], $attributes));

        $user->save();

        return $user;
    }

    protected function createGroup(Model $ou, array $attributes = []): Group
    {
        $group = (new Group())
            ->inside($ou)
            ->fill(array_merge([
                'cn' => $this->faker()->name(),
                'gidNumber' => $this->faker()->numberBetween(1000, 2000),
            ], $attributes));

        $group->save();

        return $group;
    }
}
