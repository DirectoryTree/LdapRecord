<?php

namespace LdapRecord\Models;

use LdapRecord\Query\Builder;

/**
 * Class Contact.
 *
 * Represents an LDAP contact.
 */
class Contact extends Entry
{
    use Concerns\HasMemberOf,
        Concerns\HasUserProperties;

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
