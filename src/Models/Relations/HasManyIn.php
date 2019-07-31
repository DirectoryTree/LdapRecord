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

        foreach ((array) $this->parent->getAttribute($this->relationKey) as $value) {
            if ($foreign = $this->getForeignModelByValue($value)) {
                $results->push($foreign);
            }
        }

        return $this->transformResults($results);
    }

    /**
     * Get the foreign model by the given value.
     *
     * @param string $value
     *
     * @return \LdapRecord\Models\Model|false
     */
    protected function getForeignModelByValue($value)
    {
        return $this->foreignKey == 'dn' || 'distinguishedname' ?
            $this->query->findByDn($value) :
            $this->query->findBy($this->foreignKey, $value);
    }
}
