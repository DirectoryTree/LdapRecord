<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasGroups;

class Computer extends Entry
{
    use HasGroups;

    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'user',
        'computer',
    ];

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current computer is apart of.
     *
     * @return \LdapRecord\Models\Relations\HasMany
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'member');
    }

    /**
     * The managed by relationship.
     *
     * @return \LdapRecord\Models\Relations\HasOne
     */
    public function managedBy()
    {
        return $this->hasOne([Contact::class, Group::class, User::class], 'managedby');
    }
}
