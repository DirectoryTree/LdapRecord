<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Model;

class HasManyUsing extends HasMany
{
    /**
     * The relationship to use for attaching / detaching models.
     *
     * @var Relation
     */
    protected $using;

    /**
     * Set the relationship to use for attaching / detaching models.
     *
     * @param Relation $relation
     *
     * @return $this
     */
    public function using(Relation $relation)
    {
        $this->using = $relation;

        return $this;
    }

    /**
     * Attach a model instance to the used relation.
     *
     * @param Model $model
     *
     * @return Model|false
     */
    public function attach(Model $model)
    {
        $key = $this->using->getRelationKey();

        $current = $this->parent->getAttribute($key) ?? [];

        $foreign = $this->getForeignValueFromModel($model);

        if (!in_array($this->getForeignValueFromModel($model), $current)) {
            $current[] = $foreign;

            return $this->parent->setAttribute($key, $current)->save() ? $model : false;
        }

        return $model;
    }

    /**
     * Attach a collection of models to the used relation.
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
     * Detach the model from the used relation.
     *
     * @param Model|null $model
     *
     * @return \LdapRecord\Query\Collection|Model|false
     */
    public function detach(Model $model = null)
    {
        if ($model) {
            $key = $this->using->getRelationKey();

            $updated = array_diff(
                $this->parent->getAttribute($key) ?? [],
                [$this->getForeignValueFromModel($model)]
            );

            return $this->parent->setAttribute($key, $updated)->save() ? $model : false;
        }

        return $this->get()->each(function (Model $model) {
            $this->detach($model);
        });
    }
}
