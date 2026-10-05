<?php

namespace LdapRecord\Models\Scopes;

use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class HasObjectClasses implements Scope
{
    /**
     * Restrict the query to the model's object classes.
     */
    public function apply(Builder $query, Model $model): void
    {
        foreach ($model::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }
    }
}
