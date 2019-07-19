<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasMemberOf;
use LdapRecord\Models\Concerns\HasUserProperties;

/**
 * Class Contact.
 *
 * Represents an LDAP contact.
 */
class Contact extends Entry
{
    use HasMemberOf,
        HasUserProperties;

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
