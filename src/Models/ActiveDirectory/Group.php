<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasGroups;
use LdapRecord\Models\Concerns\HasMembers;

class Group extends Entry
{
    use HasGroups, HasMembers;

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
     * @return \LdapRecord\Models\Relations\HasMany
     */
    public function members()
    {
        return $this->hasMany([static::class, User::class, Contact::class], 'memberof');
    }

    /**
     * Get a new batch modification for modifying members.
     *
     * @param int   $type
     * @param array $members
     *
     * @return \LdapRecord\Models\BatchModification
     */
    public function newMemberModification($type, array $members = [])
    {
        return $this->newBatchModification($this->groups()->getRelationKey(), $type, $members);
    }
}
