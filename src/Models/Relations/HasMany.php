<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Query\Collection;

class HasMany extends OneToMany
{
    /**
     * Get the relationships results.
     *
     * @return Collection
     */
    public function getRelationResults(): Collection
    {
        return $this->transformResults($this->getRelationQuery()->paginate());
    }

    /**
     * Get the prepared relationship query.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    protected function getRelationQuery()
    {
        return $this->query->whereRaw($this->relationKey, '=', $this->getForeignValue());
    }

    /**
     * Get the foreign key value.
     *
     * @return string
     */
    protected function getForeignValue()
    {
        // If the foreign key is a distinguished name, we must
        // escape it to be able to properly locate models.
        // Otherwise, we will not receive any results.
        if ($this->foreignKey == 'dn' || $this->foreignKey == 'distinguishedname') {
            return $this->query->escape($this->parent->getDn(), '', LDAP_ESCAPE_DN);
        }

        return $this->parent->getFirstAttribute($this->foreignKey);
    }
}
