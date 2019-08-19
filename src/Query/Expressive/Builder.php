<?php

namespace LdapRecord\Query\Expressive;

use LdapRecord\Utilities;
use LdapRecord\Models\Model;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Query\Builder as BaseBuilder;
use LdapRecord\Models\ModelNotFoundException;

class Builder extends BaseBuilder
{
    /**
     * The model being queried.
     *
     * @var Model
     */
    protected $model;

    /**
     * The relationships that should be eager loaded.
     *
     * @var array
     */
    protected $eagerLoad = [];

    /**
     * Sets the model instance for the model being queried.
     *
     * @param Model $model
     *
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Returns the model being queried for.
     *
     * @return Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Finds a record using ambiguous name resolution.
     *
     * @param string|array $value
     * @param array|string $columns
     *
     * @return Model|\LdapRecord\Query\Collection|static|null
     */
    public function find($value, $columns = [])
    {
        if (is_array($value)) {
            return $this->findMany($value, $columns);
        }

        // If we're not using ActiveDirectory, we can't use ANR.
        // We will make our own equivalent query.
        if (! $this->model instanceof ActiveDirectory) {
            return $this->prepareAnrEquivalentQuery($value)->first($columns);
        }

        return $this->findBy('anr', $value, $columns);
    }

    /**
     * Finds multiple records using ambiguous name resolution.
     *
     * @param array $values
     * @param array $columns
     *
     * @return \LdapRecord\Query\Collection
     */
    public function findMany(array $values = [], $columns = [])
    {
        $this->select($columns);

        if (! $this->model instanceof ActiveDirectory) {
            $query = $this;

            foreach ($values as $value) {
                $query->prepareAnrEquivalentQuery($value);
            }

            return $query->get();
        }

        return $this->findManyBy('anr', $values);
    }

    /**
     * Creates an ANR equivalent query for LDAP distributions that do not support ANR.
     *
     * @param string $value
     *
     * @return $this
     */
    protected function prepareAnrEquivalentQuery($value)
    {
        return $this->orFilter(function (Builder $query) use ($value) {
            foreach ($this->model->getAnrAttributes() as $attribute) {
                $query->whereEquals($attribute, $value);
            }
        });
    }

    /**
     * Finds a record by its string GUID.
     *
     * @param string       $guid
     * @param array|string $columns
     *
     * @return Model|static|null
     */
    public function findByGuid($guid, $columns = [])
    {
        try {
            return $this->findByGuidOrFail($guid, $columns);
        } catch (ModelNotFoundException $e) {
            return;
        }
    }

    /**
     * Finds a record by its string GUID.
     *
     * Fails upon no records returned.
     *
     * @param string       $guid
     * @param array|string $columns
     *
     * @throws ModelNotFoundException
     *
     * @return Model|static
     */
    public function findByGuidOrFail($guid, $columns = [])
    {
        if ($this->model instanceof ActiveDirectory) {
            $guid = Utilities::stringGuidToHex($guid);
        }

        return $this->select($columns)->whereRaw([
            $this->model->getGuidKey() => $guid,
        ])->firstOrFail();
    }

    /**
     * Set the relationships that should be eager loaded.
     *
     * @param mixed $relations
     *
     * @return $this
     */
    public function with($relations)
    {
        $eagerLoad = $this->parseWithRelations(is_string($relations) ? func_get_args() : $relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        return $this;
    }

    /**
     * Prevent the specified relations from being eager loaded.
     *
     * @param mixed $relations
     *
     * @return $this
     */
    public function without($relations)
    {
        $this->eagerLoad = array_diff_key($this->eagerLoad, array_flip(
            is_string($relations) ? func_get_args() : $relations
        ));

        return $this;
    }

    /**
     * Processes and converts the given LDAP results into models.
     *
     * @param array $results
     *
     * @return \LdapRecord\Query\Collection
     */
    protected function process(array $results)
    {
        return $this->model->hydrate(parent::process($results));
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param array $relations
     *
     * @return array
     */
    protected function parseWithRelations(array $relations)
    {
        $results = [];

        foreach ($relations as $name) {
            $results[$name] = function () {};
        }

        return $results;
    }
}
