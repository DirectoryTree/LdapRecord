<?php


namespace LdapRecord\Models\ActiveDirectory\Scopes;

use LdapRecord\Exceptions\RootDseNotFoundException;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class InConfigurationContext implements Scope
{
    /**
     * Refines the base dn to be inside the configuration context
     *
     * @param Builder $query
     * @param Model $model
     * @throws RootDseNotFoundException
     *
     * @return void
     */
    public function apply(Builder $query, Model $model)
    {
        $configurationContext = (new Entry)
            ->getRootDseAttribute('configurationNamingContext');

        $query->in($configurationContext);
    }
}