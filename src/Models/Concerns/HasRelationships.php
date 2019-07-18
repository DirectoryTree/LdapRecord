<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Models\Relation;

trait HasRelationships
{
    /**
     * Returns a hasMember relationship.
     * 
     * @return Relation
     */
    public function hasMember(array $related, $relationKey = 'member')
    {
        return $this->newRelation($related, $relationKey);
    }

    /**
     * Returns a hasMemberOf relationship.
     * 
     * @return Relation
     */
    public function hasMemberOf(array $related, $relationKey = 'memberof')
    {
        return $this->newRelation($related, $relationKey);
    }

    /**
     * Creates a new relationship.
     * 
     * @return Relation
     */
    protected function newRelation(array $related, $relationKey)
    {
        return new Relation($this->query()->newInstance(), $this, $related, $relationKey);
    }
}
