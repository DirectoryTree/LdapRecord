<?php

namespace LdapRecord\Models\Relations;

use Closure;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;

abstract class OneToMany extends Relation
{
    /**
     * The relation to merge results with.
     */
    protected ?Relation $with = null;

    /**
     * The name of the relationship.
     */
    protected ?string $relationName = null;

    /**
     * Whether to include recursive results.
     */
    protected bool $recursive = false;

    /**
     * Constructor.
     */
    public function __construct(Builder $query, Model $parent, array|string $related, string $relationKey, string $foreignKey, string $relationName)
    {
        $this->relationName = $relationName;

        parent::__construct($query, $parent, $related, $relationKey, $foreignKey);
    }

    /**
     * Set the relation to load with its parent.
     */
    public function with(Relation $relation): static
    {
        $this->with = $relation;

        return $this;
    }

    /**
     * Whether to include recursive results.
     */
    public function recursive(bool $recursive = true): static
    {
        $this->recursive = $recursive;

        return $this;
    }

    /**
     * Get the immediate relationships results.
     */
    abstract public function getRelationResults(): Collection;

    /**
     * Get the results of the relationship.
     */
    public function getResults(): Collection
    {
        $results = $this->recursive
            ? $this->getRecursiveResults()
            : $this->getRelationResults();

        return $results->merge(
            $this->getMergingRelationResults()
        );
    }

    /**
     * Execute the callback excluding the merged query result.
     */
    protected function onceWithoutMerging(Closure $callback): mixed
    {
        $merging = $this->with;

        $this->with = null;

        $result = $callback();

        $this->with = $merging;

        return $result;
    }

    /**
     * Get the relation name.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Get the results of the merging 'with' relation.
     */
    protected function getMergingRelationResults(): Collection
    {
        return $this->with
            ? $this->with->recursive($this->recursive)->get()
            : $this->parent->newCollection();
    }

    /**
     * Get the results for the models relation recursively.
     *
     * @param string[] $loaded The distinguished names of models already loaded
     */
    protected function getRecursiveResults(array $loaded = []): Collection
    {
        $results = $this->getRelationResults()->reject(function (Model $model) use ($loaded) {
            // Here we will exclude the models that we have already
            // loaded the recursive results for so we don't run
            // into issues with circular relations in LDAP.
            return in_array($model->getDn(), $loaded);
        });

        foreach ($results as $model) {
            $loaded[] = $model->getDn();

            // Finally, we will fetch the related models relations,
            // passing along our loaded models, to ensure we do
            // not attempt fetching already loaded relations.
            $results = $results->merge(
                $this->getRecursiveRelationResults($model, $loaded)
            );
        }

        return $results;
    }

    /**
     * Get the recursive relation results for given model.
     */
    protected function getRecursiveRelationResults(Model $model, array $loaded): Collection
    {
        return ($relation = $model->getRelation($this->relationName))
            ? $relation->getRecursiveResults($loaded)
            : $model->newCollection();
    }
}
