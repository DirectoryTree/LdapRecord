<?php

namespace LdapRecord\Tests\Integration\Fixtures;

use LdapRecord\Models\OpenLDAP\Group as OpenLDAPGroup;
use LdapRecord\Models\Relations\HasMany;

class Group extends OpenLDAPGroup
{
    public static array $objectClasses = [
        'top',
        'posixGroup',
    ];

    public function members(): HasMany
    {
        return $this->hasMany([User::class, Group::class], 'memberUid');
    }
}
