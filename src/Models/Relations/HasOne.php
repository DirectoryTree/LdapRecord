<?php

namespace LdapRecord\Models\Relations;

use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;

class HasOne extends Relation
{
    /**
     * Get the results of the relationship.
     */
    public function getResults(): Collection
    {
        $relationValue = $this->getFirstAttributeValue($this->parent, $this->relationKey);

        $model = $relationValue ? $this->getForeignModelByValue($relationValue) : null;

        return $this->transformResults(
            $this->parent->newCollection($model ? [$model] : null)
        );
    }

    /**
     * Attach a model instance to the parent model.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public function attach(Model|string $model): Model|string
    {
        $foreign = $model instanceof Model
            ? $this->getForeignValueFromModel($model)
            : $model;

        $this->parent->setAttribute($this->relationKey, $foreign)->save();

        return $model;
    }

    /**
     * Detach the related model from the parent.
     *
     * @throws \LdapRecord\LdapRecordException
     */
    public function detach(): void
    {
        $this->parent->setAttribute($this->relationKey, null)->save();
    }
}
