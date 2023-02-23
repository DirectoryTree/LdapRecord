<?php

namespace LdapRecord\Models;

use LdapRecord\Query\Model\Builder;

interface Scope
{
    /**
     * Apply the scope to the given query.
     *
     * @return void
     */
    public function apply(Builder $query, Model $model);
}
