<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Models\Relations\HasManyIn;
use LdapRecord\Models\Relations\HasOne;
use LdapRecord\Models\Relations\Relation;
use LdapRecord\Support\Arr;

trait HasRelationships
{
    /**
     * Returns a new has one relationship.
     */
    public function hasOne(string $related, string $relationKey, string $foreignKey = 'dn'): HasOne
    {
        return new HasOne($this->newQuery(), $this, $related, $relationKey, $foreignKey);
    }

    /**
     * Returns a new has many relationship.
     */
    public function hasMany(string $related, string $relationKey, string $foreignKey = 'dn'): HasMany
    {
        return new HasMany($this->newQuery(), $this, $related, $relationKey, $foreignKey, $this->guessRelationshipName());
    }

    /**
     * Returns a new has many in relationship.
     */
    public function hasManyIn(string $related, string $relationKey, string $foreignKey = 'dn'): HasManyIn
    {
        return new HasManyIn($this->newQuery(), $this, $related, $relationKey, $foreignKey, $this->guessRelationshipName());
    }

    /**
     * Get a relationship by its name.
     */
    public function getRelation(string $relationName): ?Relation
    {
        if (! method_exists($this, $relationName)) {
            return null;
        }

        if (! $relation = $this->{$relationName}()) {
            return null;
        }

        if (! $relation instanceof Relation) {
            return null;
        }

        return $relation;
    }

    /**
     * Get the relationships name.
     */
    protected function guessRelationshipName(): ?string
    {
        return Arr::last(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3))['function'];
    }
}
