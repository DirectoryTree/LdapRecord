<?php

namespace LdapRecord\Tests\Integration\Concerns;

use LdapRecord\Models\Model;
use LdapRecord\Models\OpenLDAP\Group;
use LdapRecord\Tests\Integration\TestCase;

/** @mixin TestCase */
trait MakesGroups
{
    protected function makeGroup(Model $ou, array $attributes = []): Group
    {
        return (new Group)
            ->inside($ou)
            ->fill(array_merge(
                ['cn' => $this->faker()->name()],
                $attributes
            ));
    }
}
