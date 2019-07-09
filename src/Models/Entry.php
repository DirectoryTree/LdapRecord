<?php

namespace LdapRecord\Models;

use LdapRecord\Query\Builder;

/**
 * Class Entry.
 *
 * Represents an LDAP record that could not be identified as another type of model.
 */
class Entry extends Model
{

    /**
     * Apply the global scopes to the given builder instance.
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applyGlobalScopes(Builder $query)
    {
        //
    }
}
