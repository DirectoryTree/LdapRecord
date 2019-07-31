<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Models\Relations\HasManyIn;

trait HasRelationships
{
    /**
     * Returns a new has many relationship.
     *
     * @param mixed  $related
     * @param string $relationKey
     * @param string $foreignKey
     *
     * @return HasMany
     */
    public function hasMany($related, $relationKey, $foreignKey = 'member')
    {
        return new HasMany($this->query(), $this, $related, $relationKey, $foreignKey);
    }

    /**
     * Returns a new has many in relationship.
     *
     * @param mixed  $related
     * @param string $relationKey
     * @param string $foreignKey
     *
     * @return HasManyIn
     */
    public function hasManyIn($related, $relationKey, $foreignKey = 'dn')
    {
        return new HasManyIn($this->query(), $this, $related, $relationKey, $foreignKey);
    }
}
