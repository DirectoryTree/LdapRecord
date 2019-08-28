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
    public function getRelationResults() : Collection
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
        return $this->query->whereRaw(
            $this->relationKey,
            '=',
            $this->getEscapedForeignValueFromModel($this->parent)
        );
    }
}
