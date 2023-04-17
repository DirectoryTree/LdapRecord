<?php

namespace LdapRecord\Models\OpenLDAP;

use LdapRecord\Models\Relations\HasMany;

class Group extends Entry
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'groupofuniquenames',
    ];

    /**
     * The members relationship.
     *
     * Retrieves members that are apart of the group.
     */
    public function members(): HasMany
    {
        return $this->hasMany([static::class, User::class], 'memberUid');
    }
}
