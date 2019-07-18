<?php

namespace LdapRecord\Models;

use LdapRecord\Models\Model;
use LdapRecord\Models\Entry;
use LdapRecord\Query\Builder;
use LdapRecord\Query\Collection;

class Relation 
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
     * Constructor.
     * 
     * @var Builder $query
     * @var Model   $parent
     * @var array   $related
     * @var string  $relationKey
     */
    public function __construct(Builder $query, Model $parent, array $related = [], $relationKey)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $related;
        $this->relationKey = $relationKey;

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
    public function get()
    {
        $results = $this->query->getModel()->newCollection();

        foreach ($this->parent->getAttribute($this->relationKey) as $dn) {
            $results->push($this->query->findByDn($dn));
        }

        return $this->transformResults($results);
    }

    /**
     * Initializes the relation by setting the default model on the query.
     * 
     * @return static
     */
    public function initRelation()
    {
        $this->query->setModel(new Entry());

        return $this;
    }

    /**
     * Transforms the results by converting the models into their related.
     * 
     * @var Collection $results
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
        return array_search(
            array_map('strtolower', $model->objectclass),
            array_map('strtolower', $related)
        );
    }
}
