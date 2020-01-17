<?php

namespace LdapRecord\Models\OpenLDAP;

class OrganizationalUnit extends Entry
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'organizationalunit',
    ];
}
