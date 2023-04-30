<?php

namespace LdapRecord\Tests\Integration\Concerns;

use LdapRecord\Models\OpenLDAP\OrganizationalUnit;

trait SetupTestOu
{
    protected OrganizationalUnit $ou;

    protected function setupTestOu()
    {
        $this->ou = OrganizationalUnit::query()->where('ou', $name = 'Group Test OU')->firstOr(
            fn () => OrganizationalUnit::create(['ou' => $name])
        );

        $this->ou->deleteLeafNodes();
    }
}
