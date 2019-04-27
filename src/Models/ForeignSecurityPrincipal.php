<?php

namespace LdapRecord\Models;

/**
 * Class ForeignSecurityPrincipal
 *
 * Represents an LDAP ForeignSecurityPrincipal.
 *
 * @package LdapRecord\Models
 */
class ForeignSecurityPrincipal extends Entry
{
    use Concerns\HasMemberOf;
}
