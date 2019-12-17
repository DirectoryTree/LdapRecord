<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Model;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;

abstract class OneToMany extends Relation
{
    /**
     * The name of the relationship.
     *
     * @var string
     */
    protected $relationName;

    /**
     * Whether to include recursive results.
     *
     * @var bool
     */
    protected $recursive = false;

    /**
     * Constructor.
     *
     * @param Builder $query
     * @param Model   $parent
     * @param string  $related
     * @param string  $relationKey
     * @param string  $foreignKey
     * @param string  $relationName
     */
    public function __construct(Builder $query, Model $parent, $related, $relationKey, $foreignKey, $relationName)
    {
        $this->relationName = $relationName;

        parent::__construct($query, $parent, $related, $relationKey, $foreignKey);
    }

    /**
     * Whether to include recursive results.
     *
     * @param bool $enable
     *
     * @return $this
     */
    public function recursive($enable = true)
    {
        $this->recursive = $enable;

        return $this;
    }

    /**
     * Get the immediate relationships results.
     *
     * @return Collection
     */
    abstract public function getRelationResults();

    /**
     * Get the results of the relationship.
     *
     * @return Collection
     */
    public function getResults()
    {
        return $this->recursive ?
            $this->getRecursiveResults($this->getRelationResults()) :
            $this->getRelationResults();
    }

    /**
     * Get the relation name.
     *
     * @return string
     */
    public function getRelationName()
    {
        return $this->relationName;
    }

    /**
     * Get the results for the models relation recursively.
     *
     * @param Collection     $models The models to retrieve nested relation results from
     * @param array|string[] $except The distinguished names to exclude
     *
     * @return Collection
     */
    protected function getRecursiveResults(Collection $models, array $except = [])
    {
        $models->filter(function (Model $model) use ($except) {
            // Here we will exclude the models that we have already
            // gathered the recursive results for so we don't run
            // into issues with circular relations in LDAP.
            return !in_array($model->getDn(), $except);
        })->each(function (Model $model) use ($except, $models) {
            $except[] = $model->getDn();

            $model->{$this->relationName}()->get()->each(function (Model $related) use ($models) {
                $models->add($related);
            });
        });

        return $models;
    }
}
