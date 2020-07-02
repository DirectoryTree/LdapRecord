<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Model;
use LdapRecord\Query\Collection;

class HasMany extends OneToMany
{
    /**
     * The model to use for attaching / detaching.
     *
     * @var Model
     */
    protected $using;

    /**
     * The attribute key to use for attaching / detaching.
     *
     * @var string
     */
    protected $usingKey;

    /**
     * The pagination page size.
     *
     * @var int
     */
    protected $pageSize = 1000;

    /**
     * Set the model and attribute to use for attaching / detaching.
     *
     * @param Model  $using
     * @param string $usingKey
     *
     * @return $this
     */
    public function using(Model $using, $usingKey)
    {
        $this->using = $using;
        $this->usingKey = $usingKey;

        return $this;
    }

    /**
     * Set the pagination page size of the relation query.
     *
     * @param int $pageSize
     *
     * @return $this
     */
    public function setPageSize($pageSize)
    {
        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * Paginate the relation using the given page size.
     *
     * @param int $pageSize
     *
     * @return Collection
     */
    public function paginate($pageSize = 1000)
    {
        return $this->paginateOnceUsing($pageSize);
    }

    /**
     * Paginate the relation using the page size once.
     *
     * @param int $pageSize
     *
     * @return Collection
     */
    protected function paginateOnceUsing($pageSize)
    {
        $size = $this->pageSize;

        $result = $this->setPageSize($pageSize)->get();

        $this->pageSize = $size;

        return $result;
    }

    /**
     * Get the relationships results.
     *
     * @return Collection
     */
    public function getRelationResults()
    {
        return $this->transformResults(
            $this->getRelationQuery()->paginate($this->pageSize)
        );
    }

    /**
     * Get the prepared relationship query.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    public function getRelationQuery()
    {
        $columns = $this->query->getSelects();

        // We need to select the proper key to be able to retrieve its
        // value from LDAP results. If we don't, we won't be able
        // to properly attach / detach models from relation
        // query results as the attribute will not exist.
        $key = $this->using ? $this->usingKey : $this->relationKey;

        // If the * character is missing from the attributes to select,
        // we will add the key to the attributes to select and also
        // validate that the key isn't already being selected
        // to prevent stacking on multiple relation calls.
        if (!in_array('*', $columns) && !in_array($key, $columns)) {
            $this->query->addSelect($key);
        }

        return $this->query->whereRaw(
            $this->relationKey,
            '=',
            $this->getEscapedForeignValueFromModel($this->parent)
        );
    }

    /**
     * Attach a model to the relation.
     *
     * @param Model $model
     *
     * @return Model|false
     */
    public function attach(Model $model)
    {
         $attached = $this->using
            ? $this->using->createAttribute($this->foreignKey, $model->getDn())
            : $this->parent->createAttribute($this->foreignKey, $model->getDn());

         return $attached ? $model : $attached;
    }

    /**
     * Attach a collection of models to the parent instance.
     *
     * @param iterable $models
     *
     * @return iterable
     */
    public function attachMany($models)
    {
        foreach ($models as $model) {
            $this->attach($model);
        }

        return $models;
    }

    /**
     * Detach the model from the relation.
     *
     * @param Model $model
     *
     * @return Model|false
     */
    public function detach(Model $model)
    {
        $detached = $this->using
            ? $this->using->deleteAttribute([$this->foreignKey => $model->getDn()])
            : $this->parent->deleteAttribute([$this->foreignKey => $model->getDn()]);

        return $detached ? $model : $detached;
    }

    /**
     * Detach all relation models.
     *
     * @return Collection
     */
    public function detachAll()
    {
        return $this->get()->each(function (Model $model) {
            $this->detach($model);
        });
    }
}
