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
     * Apply the global scopes to the given builder instance.
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applyGlobalScopes(Builder $query)
    {
        $query->whereEquals($this->schema->objectClass(), $this->schema->objectClassContact());
    }
}
