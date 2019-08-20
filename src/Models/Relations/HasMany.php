<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Query\Collection;

class HasMany extends Relation
{
    /**
     * Get the results of the relationship.
     *
     * @return Collection
     */
    public function get()
    {
        return $this->transformResults(
            $this->query->whereRaw($this->relationKey, '=', $this->getForeignValue())->get()
        );
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
        // Otherwise, we won't receive any results.
        if ($this->foreignKey == 'dn' || $this->foreignKey == 'distinguishedname') {
            return $this->query->escape($this->parent->getDn(), '', LDAP_ESCAPE_DN);
        }

        return $this->parent->getFirstAttribute($this->foreignKey);
    }
}
