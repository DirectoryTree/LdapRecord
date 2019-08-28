<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Model;

class HasOne extends Relation
{
    /**
     * Get the results of the relationship.
     *
     * @return \LdapRecord\Query\Collection
     */
    public function getResults()
    {
        $model = $this->getForeignModelByValue(
            $this->parent->getFirstAttribute($this->relationKey)
        );

        return $this->transformResults(
            $this->parent->newCollection($model ? [$model] : null)
        );
    }

    /**
     * Save and attach the model.
     *
     * @param Model $model
     *
     * @return Model|false
     */
    public function save(Model $model)
    {
        if (! $model->exists) {
            $model->save();
        }

        return $this->attach($model);
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @param Model $model
     *
     * @return Model|false
     */
    public function attach(Model $model)
    {
        return $this->parent->setFirstAttribute(
            $this->relationKey, $model->getDn()
        )->save() ? $model : false;
    }
}
