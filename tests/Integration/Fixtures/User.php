<?php

namespace LdapRecord\Tests\Integration\Fixtures;

use LdapRecord\Models\Concerns\HasPassword;
use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Models\Relations\HasMany;

class User extends Entry
{
    use HasPassword;

    public static array $objectClasses = [
        'top',
        'posixAccount',
        'inetOrgPerson',
    ];

    protected string $passwordAttribute = 'userpassword';

    protected string $passwordHashMethod = 'ssha';

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'memberuid', 'uid');
    }
}
