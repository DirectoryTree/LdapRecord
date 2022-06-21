<?php

namespace LdapRecord\Tests\Integration\Fixtures;

use LdapRecord\Models\OpenLDAP\User as OpenLDAPUser;

class User extends OpenLDAPUser
{
    public static $objectClasses = [
        'top',
        'posixAccount',
        'inetOrgPerson',
    ];
}
