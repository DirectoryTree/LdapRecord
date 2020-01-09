<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;
use LdapRecord\Models\ActiveDirectory\Concerns\HasPrimaryGroup;

class User extends Entry implements Authenticatable
{
    use HasPassword;
    use HasPrimaryGroup;
    use CanAuthenticate;

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
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'lastlogon'          => 'windows-int',
        'lastlogoff'         => 'windows-int',
        'pwdlastset'         => 'windows-int',
        'lockouttime'        => 'windows-int',
        'accountexpires'     => 'windows-int',
        'badpasswordtime'    => 'windows-int',
        'lastlogontimestamp' => 'windows-int',
    ];

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current user is apart of.
     *
     * @return \LdapRecord\Models\Relations\HasMany
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'member');
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
     * The primary group relationship of the current user.
     *
     * Retrieves the primary group the user is apart of.
     *
     * @return \LdapRecord\Models\Relations\HasOne
     */
    public function primaryGroup()
    {
        return $this->hasOnePrimaryGroup(Group::class, 'primarygroupid');
    }
}
