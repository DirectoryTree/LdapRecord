<?php

namespace LdapRecord\Models\OpenLDAP;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Concerns\CanAuthenticate;

class User extends Entry implements Authenticatable
{
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
        'inetorgperson',
    ];
}
