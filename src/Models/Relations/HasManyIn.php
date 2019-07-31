<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Query\Collection;

class HasManyIn extends Relation
{
    /**
     * Get the results of the relationship.
     *
     * @return Collection
     */
    public function get()
    {
        $results = $this->query->getModel()->newCollection();

        foreach ((array) $this->parent->getAttribute($this->relationKey) as $dn) {
            $related = $this->foreignKey == 'dn' || 'distinguishedname' ?
                $this->query->findByDn($dn) :
                $this->query->findBy($this->foreignKey, $dn);

            if ($related) {
                $results->push($related);
            }
        }

        return $this->transformResults($results);
    }
}
