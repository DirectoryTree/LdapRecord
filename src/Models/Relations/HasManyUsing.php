<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Model;

class HasManyUsing extends HasMany
{
    /**
     * The attribute to use for attaching / detaching models.
     *
     * @var string
     */
    protected $using;

    /**
     * Set the attribute to use for attaching / detaching models.
     *
     * @param string $attribute
     *
     * @return $this
     */
    public function using($attribute)
    {
        $this->using = $attribute;

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
        $current = $this->parent->getAttribute($this->using) ?? [];

        $foreign = $this->getForeignValueFromModel($model);

        if (!in_array($this->getForeignValueFromModel($model), $current)) {
            $current[] = $foreign;

            return $this->parent->setAttribute($this->using, $current)->save() ? $model : false;
        }

        return $model;
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
            $updated = array_diff(
                $this->parent->getAttribute($this->using) ?? [],
                [$this->getForeignValueFromModel($model)]
            );

            return $this->parent->setAttribute($this->using, $updated)->save() ? $model : false;
        }

        return $this->get()->each(function (Model $model) {
            $this->detach($model);
        });
    }
}
