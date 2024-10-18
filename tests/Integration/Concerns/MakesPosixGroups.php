<?php

namespace LdapRecord\Tests\Integration\Concerns;

use LdapRecord\Models\Model;
use LdapRecord\Tests\Integration\Fixtures\PosixGroup;

/** @mixin \LdapRecord\Tests\Integration\TestCase */
trait MakesPosixGroups
{
    protected function makePosixGroup(Model $ou, array $attributes = []): PosixGroup
    {
        return (new PosixGroup)
            ->inside($ou)
            ->fill(array_merge([
                'cn' => $this->faker()->name(),
                'gidNumber' => $this->faker()->numberBetween(1000, 2000),
            ], $attributes));
    }
}
