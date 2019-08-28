<?php

namespace LdapRecord\Models\Relations;

use Exception;
use LdapRecord\Models\Model;

class BelongsToMany extends HasMany
{
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
        $current = $this->getRelatedValue($model);

        // We need to determine if the parent is already apart
        // of the given related model. If we don't, we'll
        // receive a 'type or value exists' error.
        if (! in_array($this->parent->getDn(), $current)) {
            $current[] = $this->parent->getDn();

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
     * @throws Exception
     *
     * @return \LdapRecord\Query\Collection|Model|false
     */
    public function detach(Model $model = null)
    {
        if ($model) {
            $updated = array_diff($this->getRelatedValue($model), [$this->parent->getDn()]);

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
     * @throws Exception
     *
     * @return Model
     */
    protected function setRelatedValue(Model $model, $value)
    {
        if (! $model->hasAttribute($this->relationKey)) {
            $class = get_class($model);

            throw new Exception("Invalid model given. Attribute '{$this->relationKey}' does not exist on {$class}");
        }

        return $model->setAttribute($this->relationKey, $value);
    }
}
