<?php

namespace LdapRecord\Models;

use LdapRecord\Query\Builder;

/**
 * Class Container.
 *
 * Represents an LDAP container.
 */
class Container extends Entry
{
    use Concerns\HasDescription,
        Concerns\HasCriticalSystemObject;

    /**
     * Apply the global scopes to the given builder instance.
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applyGlobalScopes(Builder $query)
    {
        $query->whereEquals($this->schema->objectClass(), $this->schema->objectClassContainer());
    }

    /**
     * Returns the containers system flags integer.
     *
     * An integer value that contains flags that define additional properties of the class.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms680022(v=vs.85).aspx
     *
     * @return string
     */
    public function getSystemFlags()
    {
        return $this->getFirstAttribute($this->schema->systemFlags());
    }
}
