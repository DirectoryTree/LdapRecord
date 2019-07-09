<?php

namespace LdapRecord\Query;

use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use InvalidArgumentException;
use LdapRecord\Schemas\SchemaInterface;

class Processor
{
    /**
     * @var Builder
     */
    protected $builder;

    /**
     * @var SchemaInterface
     */
    protected $schema;

    /**
     * Constructor.
     *
     * @param Builder $builder
     */
    public function __construct(Builder $builder)
    {
        $this->builder = $builder;
        $this->schema = $builder->getSchema();
    }

    /**
     * Processes LDAP search results and constructs their model instances.
     *
     * @param array $entries The LDAP entries to process.
     *
     * @return Collection|array
     */
    public function process($entries)
    {
        if ($this->builder->isRaw()) {
            // If the builder is asking for a raw
            // LDAP result, we can return here.
            return $entries;
        }

        $models = [];

        foreach ($entries as $entry) {
            // We'll go through each entry and construct a new
            // model instance with the raw LDAP attributes.
            $models[] = $this->newLdapEntry($entry);
        }

        // If the query is being sorted, we will sort all the
        // models and return the resulting collection.
        if ($this->builder->isSorted()) {
            return $this->sort($models);
        }

        return $this->newCollection($models);
    }

    /**
     * Returns a new LDAP Entry instance.
     *
     * @param array $attributes
     *
     * @return Entry
     */
    public function newLdapEntry(array $attributes = [])
    {
        $attribute = $this->schema->objectClass();

        // We need to ensure the record contains an object class to be able to
        // determine its type. Otherwise, we create a default Entry model.
        if (array_key_exists($attribute, $attributes) && array_key_exists(0, $attributes[$attribute])) {
            if ($model = $this->determineModel($attributes[$attribute])) {
                // Construct and return a new model.
                return $this->newModel([], $model)
                    ->setRawAttributes($attributes);
            }
        }

        // A default entry model if the object class isn't found.
        return $this->newModel()->setRawAttributes($attributes);
    }

    /**
     * Determine the model class to use for the given object class.
     *
     * @param array $objectClasses
     *
     * @return string|null
     */
    protected function determineModel($objectClasses)
    {
        // Retrieve all of the object classes from the LDAP
        // entry and lowercase them for comparisons.
        $classes = array_map('strtolower', $objectClasses);

        // Retrieve the model mapping.
        $models = $this->schema->objectClassModelMap();

        // Retrieve the object class mappings (with strtolower keys).
        $mappings = array_map('strtolower', array_keys($models));

        // Retrieve the model from the map using the entry's object class.
        $map = array_intersect($mappings, $classes);

        return count($map) > 0 ? $models[current($map)] : null;
    }

    /**
     * Creates a new model instance.
     *
     * @param array       $attributes
     * @param string|null $model
     *
     * @throws InvalidArgumentException
     *
     * @return mixed|Entry
     */
    public function newModel($attributes = [], $model = null)
    {
        $model = (class_exists($model) ? $model : $this->schema->entryModel());

        if (!is_subclass_of($model, $base = Model::class)) {
            throw new InvalidArgumentException("The given model class '{$model}' must extend the base model class '{$base}'");
        }

        return new $model($attributes);
    }

    /**
     * Returns a new collection instance.
     *
     * @param array $items
     *
     * @return Collection
     */
    public function newCollection(array $items = [])
    {
        return new Collection($items);
    }

    /**
     * Sorts LDAP search results.
     *
     * @param array $models
     *
     * @return Collection
     */
    protected function sort(array $models = [])
    {
        $field = $this->builder->getSortByField();

        $flags = $this->builder->getSortByFlags();

        $direction = $this->builder->getSortByDirection();

        $desc = ($direction === 'desc' ? true : false);

        return $this->newCollection($models)->sortBy($field, $flags, $desc);
    }
}
