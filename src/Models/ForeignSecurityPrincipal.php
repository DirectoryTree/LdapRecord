<?php

namespace LdapRecord\Models;

/**
 * Class ForeignSecurityPrincipal.
 *
 * Represents an LDAP ForeignSecurityPrincipal.
 */
class ForeignSecurityPrincipal extends Entry
{
    use Concerns\HasMemberOf;

    /**
     * The object classes of the LDAP model.
     * 
     * @var array
     */
    public static $objectClasses = ['foreignsecurityprincipal'];
}
