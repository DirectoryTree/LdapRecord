<?php

namespace LdapRecord\Models;

use LdapRecord\Query\Builder;

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
    protected $objectClasses = ['foreignsecurityprincipal'];
}
