<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasGroups;

class Contact extends Entry
{
    use HasGroups;

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current contact is apart of.
     *
     * @return \LdapRecord\Models\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'member');
    }

    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'contact',
    ];
}
