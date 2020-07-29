<?php

namespace LdapRecord\Models\ActiveDirectory\Scopes;

use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\ActiveDirectory\Entry;

class InConfigurationContext implements Scope
{
    /**
     * Refines the base dn to be inside the configuration context
     *
     * @param Builder $query
     * @param Model   $model
     *
     * @return void
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public function apply(Builder $query, Model $model)
    {
        $query->in(Entry::getRootDse());
    }
}
