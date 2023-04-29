<?php

namespace LdapRecord\Tests\Integration\Fixtures;

use LdapRecord\Models\Concerns\HasPassword;
use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Models\Relations\HasMany;

class PosixAccount extends Entry
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
        return $this->hasMany(PosixGroup::class, 'memberuid', 'uid');
    }
}
