<?php

namespace LdapRecord\Models\ActiveDirectory\Scopes;

use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class RejectComputerObjectClass implements Scope
{
    /**
     * Prevent computer objects from being included in results.
     *
     * @return void
     */
    public function apply(Builder $query, Model $model)
    {
        $query->where('objectclass', '!=', 'computer');
    }
}
