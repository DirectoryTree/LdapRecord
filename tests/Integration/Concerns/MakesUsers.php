<?php

namespace LdapRecord\Tests\Integration\Concerns;

use LdapRecord\Models\Model;
use LdapRecord\Models\OpenLDAP\User;

trait MakesUsers
{
    protected function makeUser(Model $ou, array $attributes = []): User
    {
        return (new User)
            ->inside($ou)
            ->fill(array_merge([
                'uid' => $this->faker()->userName(),
                'givenName' => $firstName = $this->faker()->firstName(),
                'sn' => $lastName = $this->faker()->lastName(),
                'cn' => "$firstName $lastName",
            ], $attributes));
    }
}
