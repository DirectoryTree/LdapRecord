<?php

namespace LdapRecord\Tests\Integration\Fixtures;

use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Models\Relations\HasManyIn;

class PosixGroup extends Entry
{
    public static array $objectClasses = [
        'top',
        'posixGroup',
    ];

    public function users(): HasManyIn
    {
        return $this->hasManyIn(PosixAccount::class, 'memberuid', 'uid')->using($this, 'memberuid');
    }
}
