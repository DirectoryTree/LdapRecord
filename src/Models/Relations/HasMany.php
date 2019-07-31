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
        // If the foreign key is a distinguished name, we must
        // escape it to be able to properly locate models.
        if ($this->foreignKey == 'dn' || 'distinguishedname') {
            $foreign = $this->query->escape($this->parent->getDn(), '', LDAP_ESCAPE_DN);
        } else {
            $foreign = $this->parent->getFirstAttribute($this->foreignKey);
        }

        return $this->transformResults(
            $this->query->whereRaw($this->relationKey, '=', $foreign)->get()
        );
    }
}
