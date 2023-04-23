<?php

namespace LdapRecord\Tests\Integration\Fixtures;

use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Models\Relations\HasMany;

class User extends Entry
{
    public static array $objectClasses = [
        'top',
        'posixAccount',
        'inetOrgPerson',
    ];

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'memberuid', 'uid');
    }
}
