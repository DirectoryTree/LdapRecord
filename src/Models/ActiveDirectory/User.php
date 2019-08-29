<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasGroups;
use LdapRecord\Models\Concerns\HasPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;

class User extends Entry implements Authenticatable
{
    use HasGroups, HasPassword, CanAuthenticate;

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
    ];

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current user is apart of.
     *
     * @return \LdapRecord\Models\Relations\BelongsToMany
     */
    public function groups()
    {
        return $this->belongsToMany(Group::class, 'member');
    }

    /**
     * The manager relationship.
     *
     * Retrieves the manager of the current user.
     *
     * @return \LdapRecord\Models\Relations\HasOne
     */
    public function manager()
    {
        return $this->hasOne(static::class, 'manager');
    }

    /**
     * Retrieves the primary group of the current user.
     *
     * @return Group|null
     */
    public function getPrimaryGroup()
    {
        $groupSid = preg_replace('/\d+$/', $this->getFirstAttribute('primarygroupid'), $this->getConvertedSid());

        $model = reset($this->groups()->getRelated());

        return $this->newQueryWithoutScopes()->setModel(new $model())->findBySid($groupSid);
    }
}
