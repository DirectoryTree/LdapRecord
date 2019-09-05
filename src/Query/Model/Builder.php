<?php

namespace LdapRecord\Query\Model;

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
    public function findByAnr($value, $columns = [])
    {
        if (is_array($value)) {
            return $this->findManyByAnr($value, $columns);
        }

        // If we're not using ActiveDirectory, we can't use ANR.
        // We will make our own equivalent query.
        if (!$this->model instanceof ActiveDirectory) {
            return $this->prepareAnrEquivalentQuery($value)->first($columns);
        }

        return $this->findBy('anr', $value, $columns);
    }

    /**
     * Finds a record using ambiguous name resolution.
     *
     * If a record is not found, an exception is thrown.
     *
     * @param string       $value
     * @param array|string $columns
     *
     * @throws ModelNotFoundException
     *
     * @return Model
     */
    public function findByAnrOrFail($value, $columns = [])
    {
        if ($entry = $this->findByAnr($value, $columns)) {
            return $entry;
        }

        throw (new ModelNotFoundException())
            ->setQuery($this->getUnescapedQuery(), $this->dn);
    }

    /**
     * Finds multiple records using ambiguous name resolution.
     *
     * @param array $values
     * @param array $columns
     *
     * @return \LdapRecord\Query\Collection
     */
    public function findManyByAnr(array $values = [], $columns = [])
    {
        $this->select($columns);

        if (!$this->model instanceof ActiveDirectory) {
            foreach ($values as $value) {
                $this->prepareAnrEquivalentQuery($value);
            }

            return $this->get();
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
        return $this->orFilter(function (self $query) use ($value) {
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
            $results[$name] = function () {
            };
        }

        return $results;
    }
}
