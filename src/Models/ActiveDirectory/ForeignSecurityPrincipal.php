<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasMemberOf;

/**
 * Class ForeignSecurityPrincipal.
 *
 * Represents an LDAP ForeignSecurityPrincipal.
 */
class ForeignSecurityPrincipal extends Entry
{
    use HasMemberOf;

    /**
     * The object classes of the LDAP model.
     * 
     * @var array
     */
    public static $objectClasses = ['foreignsecurityprincipal'];
}
