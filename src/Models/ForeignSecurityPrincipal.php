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
     * Apply the global scopes to the given builder instance.
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applyGlobalScopes(Builder $query)
    {
        $query->whereEquals($this->schema->objectClass(), $this->schema->objectClassForeignSecurityPrincipal());
    }
}
