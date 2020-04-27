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
        // Here we will retrieve the current relationships value to be add
        // the given model into it. If a 'using' model is defined, we
        // will retrieve the value from it, otherwise we will
        // retrieve it from the model being attached.
        $current = $this->getCurrentRelationValue($model);

        // Now we will retrieve the foreign key value. If a 'using' model
        // is defined, it is retrieved from the given model. Otherwise,
        // it will be retrieved from the relationships parent model.
        $foreign = $this->getCurrentForeignValue($model);

        // We need to determine if the foreign key value is already inside
        // the relationships value. If we don't, we will receive a
        // 'type or value exists' error upon saving.
        if (!in_array($foreign, $current)) {
            $current[] = $foreign;

            // If we have a model that is being used, we will set its attribute
            // being used to the new current value. Otherwise we will use the
            // model being attached along with its relation attribute.
            $related = $this->setCurrentRelationValue($current, $model);

            // Finally, we will save the returned related model if successful.
            return $related->save() ? $model : false;
        }

        return $model;
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
        // Here we will retrieve the current relationships value to be remove
        // the given model from it. If a 'using' model is defined, we
        // will retrieve the value from it, otherwise we will
        // retrieve it from the model being attached.
        $current = $this->getCurrentRelationValue($model);

        // Now we will retrieve the foreign key value. If a 'using' model
        // is defined, it is retrieved from the given model. Otherwise,
        // it will be retrieved from the relationships parent model.
        $foreign = $this->getCurrentForeignValue($model);

        // Remove the foreign key value from the current attribute value.
        $current = array_diff($current, [$foreign]);

        // If we have a model that is being used, we will set its attribute
        // being used to the new current value. Otherwise we will use the
        // model being attached along with its relation attribute.
        $related = $this->setCurrentRelationValue($current, $model);

        // Finally, we will save the related model and return if successful.
        return $related->save() ? $model : false;
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

    /**
     * Get the current relation value. If the relation is set to
     * use another model, its value will be returned instead.
     *
     * @param Model $model
     *
     * @return array
     */
    protected function getCurrentRelationValue(Model $model)
    {
        return $this->using ? $this->getUsingValue() : $this->getRelatedValue($model);
    }

    /**
     * Set the current relation value. If the relation is set to
     * use another model, its attribute will be set instead.
     *
     * @param mixed $value
     * @param Model $model
     *
     * @return Model
     */
    protected function setCurrentRelationValue($value, Model $model)
    {
        return $this->using
            ? $this->setUsingValue($value)
            : $this->setRelatedValue($model, $value);
    }

    /**
     * Get the current foreign value. If the relation is set to
     * use another model, the given models foreign value will
     * be used. Otherwise, the parents will be used.
     *
     * @param Model $model
     *
     * @return string
     */
    protected function getCurrentForeignValue(Model $model)
    {
        return $this->using
            ? $this->getForeignValueFromModel($model)
            : $this->getForeignValueFromModel($this->parent);
    }

    /**
     * Get the attribute value from the model being used.
     *
     * @return array
     */
    protected function getUsingValue()
    {
        return $this->using->getAttribute($this->usingKey) ?? [];
    }

    /**
     * Set the attribute for the model being used.
     *
     * @param mixed $value
     *
     * @return Model
     */
    protected function setUsingValue($value)
    {
        return $this->using->setAttribute($this->usingKey, $value);
    }

    /**
     * Get the current related models relation value.
     *
     * @param Model $model
     *
     * @return array
     */
    protected function getRelatedValue(Model $model)
    {
        return $model->getAttribute($this->relationKey) ?? [];
    }

    /**
     * Set the related models relation value.
     *
     * @param Model $model
     * @param mixed $value
     *
     * @return Model
     */
    protected function setRelatedValue(Model $model, $value)
    {
        return $model->setAttribute($this->relationKey, $value);
    }
}
