<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Model;

class BelongsToMany extends HasMany
{
    /**
     * Attach a model instance to the parent model.
     *
     * @param Model $model
     *
     * @return Model|false
     */
    public function attach(Model $model)
    {
        $current = $this->getRelatedValue($model);

        $foreign = $this->getForeignValueFromModel($this->parent);

        // We need to determine if the parent is already apart
        // of the given related model. If we don't, we'll
        // receive a 'type or value exists' error.
        if (!in_array($foreign, $current)) {
            $current[] = $foreign;

            return $this->setRelatedValue($model, $current)->save() ? $model : false;
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
     * Detatch the parent model from the given.
     *
     * @param Model|null $model
     *
     * @return \LdapRecord\Query\Collection|Model|false
     */
    public function detach(Model $model = null)
    {
        if ($model) {
            $updated = array_diff(
                $this->getRelatedValue($model),
                [$this->getForeignValueFromModel($this->parent)]
            );

            return $this->setRelatedValue($model, $updated)->save() ? $model : false;
        }

        return $this->get()->each(function (Model $model) {
            $this->detach($model);
        });
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
