<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Query\Builder;
use LdapRecord\Query\Collection;

abstract class Relation
{
    /**
     * @var Builder
     */
    protected $query;

    /**
     * The parent model instance. 
     * 
     * @var Model
     */
    protected $parent;

    /**
     * The related models.
     * 
     * @var array
     */
    protected $related;

    /**
     * The relation key.
     * 
     * @var string
     */
    protected $relationKey;

    /**
     * The foreign key.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * Constructor.
     * 
     * @var Builder $query
     * @var Model   $parent
     * @var mixed   $related
     * @var string  $relationKey
     * @var string  $foreignKey
     */
    public function __construct(Builder $query, Model $parent, $related, $relationKey, $foreignKey = 'dn')
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = (array) $related;
        $this->relationKey = $relationKey;
        $this->foreignKey = $foreignKey;

        $this->initRelation();
    }

    /**
     * Handle dynamic method calls to the relationship.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        $result = $this->query->$method(...$parameters);

        if ($result === $this->query) {
            return $this;
        }

        return $result;
    }

    /**
     * Get the results of the relationship.
     * 
     * @return Collection
     */
    abstract public function get();

    /**
     * Initializes the relation by setting the default model on the query.
     * 
     * @return static
     */
    public function initRelation()
    {
        $this->query->clearFilters()->setModel(new Entry());

        return $this;
    }

    /**
     * Transforms the results by converting the models into their related.
     *
     * @param Collection $results
     *
     * @return Collection
     */
    protected function transformResults(Collection $results)
    {
        $related = [];

        foreach ($this->related as $relation) {
            $related[$relation] = $relation::$objectClasses;
        }

        return $results->transform(function (Model $entry) use ($related) {
            $relatedModel = $this->determineModelFromRelated($entry, $related);

            return $relatedModel ? $entry->convert(new $relatedModel()) : $entry;
        });
    }

    /**
     * Determines the model from the given relations.
     * 
     * @var Model $model
     * @var array $related
     * 
     * @return string|bool
     */
    protected function determineModelFromRelated(Model $model, array $related)
    {
        return array_search(array_map('strtolower', $model->getAttribute('objectclass')), $related);
    }
}
