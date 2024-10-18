<?php

namespace LdapRecord\Tests\Integration\Concerns;

use LdapRecord\Models\Model;
use LdapRecord\Tests\Integration\Fixtures\PosixAccount;

/** @mixin \LdapRecord\Tests\Integration\TestCase */
trait MakePosixUsers
{
    protected function makePosixUser(Model $ou, array $attributes = []): PosixAccount
    {
        return (new PosixAccount)
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
    }
}
