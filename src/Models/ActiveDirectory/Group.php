<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasGroups;

class Group extends Entry
{
    use HasGroups;

    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'group',
    ];

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current group is apart of.
     *
     * @return \LdapRecord\Models\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(static::class, 'member');
    }

    /**
     * The members relationship.
     *
     * Retrieves members that are apart of the current group.
     *
     * @return \LdapRecord\Models\Relations\HasManyUsing
     */
    public function members()
    {
        return $this->hasManyUsing([
            static::class, User::class, Contact::class,
        ], 'memberof')->using($this->groups());
    }
}
