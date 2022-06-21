<?php

namespace LdapRecord\Tests\Integration\Fixtures;

use LdapRecord\Models\OpenLDAP\Group as OpenLDAPGroup;

class Group extends OpenLDAPGroup
{
    public static $objectClasses = [
        'top',
        'posixGroup'
    ];

    public function members()
    {
        return $this->hasMany([User::class, Group::class], 'memberUid');
    }
}
