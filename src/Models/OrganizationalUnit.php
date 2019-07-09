<?php

namespace LdapRecord\Models;

use LdapRecord\Query\Builder;

/**
 * Class OrganizationalUnit.
 *
 * Represents an LDAP organizational unit.
 */
class OrganizationalUnit extends Entry
{
    use Concerns\HasDescription;

    /**
     * Apply the global scopes to the given builder instance.
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applyGlobalScopes(Builder $query)
    {
        $query->whereEquals($this->schema->objectClass(), $this->schema->objectClassOu());
    }

    /**
     * Retrieves the organization units OU attribute.
     *
     * @return string
     */
    public function getOu()
    {
        return $this->getFirstAttribute($this->schema->organizationalUnitShort());
    }

    /**
     * {@inheritdoc}
     */
    protected function getCreatableDn()
    {
        return $this->getDnBuilder()->addOU($this->getOu());
    }
}
