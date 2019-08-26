<?php

namespace LdapRecord\Models\OpenLDAP;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;
use LdapRecord\Models\Concerns\HasPassword;

class User extends Entry implements Authenticatable
{
    use HasPassword, CanAuthenticate;

    /**
     * The attribute to use for password changes.
     *
     * @var string
     */
    protected $passwordAttribute = 'userpassword';

    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'inetorgperson',
    ];
}
