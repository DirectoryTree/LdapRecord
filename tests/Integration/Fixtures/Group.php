<?php

namespace LdapRecord\Tests\Integration\Fixtures;

use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Models\Relations\HasManyIn;

class Group extends Entry
{
    public static array $objectClasses = [
        'top',
        'posixGroup',
    ];

    public function users(): HasManyIn
    {
        return $this->hasManyIn(User::class, 'memberuid', 'uid')->using($this, 'memberuid');
    }
}
