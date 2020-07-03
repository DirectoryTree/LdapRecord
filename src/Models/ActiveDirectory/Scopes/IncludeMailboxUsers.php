<?php

namespace LdapRecord\Models\ActiveDirectory\Scopes;

use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class IncludeMailboxUsers implements Scope
{
    /**
     * Adds a clause to include users with an Exchange mailbox
     *
     * @param Builder $query
     * @param Model   $model
     *
     * @return void
     */
    public function apply(Builder $query, Model $model)
    {
        $query->whereHas('msExchMailboxGuid');
    }
}