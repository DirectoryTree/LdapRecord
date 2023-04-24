<?php

namespace LdapRecord\Models\Relations;

use Closure;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelNotFoundException;
use LdapRecord\Query\Model\Builder;

abstract class OneToMany extends Relation
{
    /**
     * The model to use for attaching / detaching.
     */
    protected ?Model $using = null;

    /**
     * The relation to merge results with.
     */
    protected ?Relation $with = null;

    /**
     * The attribute key to use for attaching / detaching.
     */
    protected ?string $usingKey = null;

    /**
     * The name of the relationship.
     */
    protected ?string $relationName = null;

    /**
     * Whether to include recursive results.
     */
    protected bool $recursive = false;

    /**
     * The exceptions to bypass for each relation operation.
     */
    protected array $bypass = [
        'attach' => [
            'Already exists', 'Type or value exists',
        ],
        'detach' => [
            'No such attribute', 'Server is unwilling to perform',
        ],
    ];

    /**
     * Constructor.
     */
    public function __construct(Builder $query, Model $parent, array|string $related, string $relationKey, string $foreignKey, string $relationName)
    {
        $this->relationName = $relationName;

        parent::__construct($query, $parent, $related, $relationKey, $foreignKey);
    }

    /**
     * Set the model and attribute to use for attaching / detaching.
     */
    public function using(Model $using, string $usingKey): static
    {
        $this->using = $using;
        $this->usingKey = $usingKey;

        return $this;
    }

    /**
     * Set the relation to load with its parent.
     */
    public function with(Relation $relation): static
    {
        $this->with = $relation;

        return $this;
    }

    /**
     * Whether to include recursive results.
     */
    public function recursive(bool $recursive = true): static
    {
        $this->recursive = $recursive;

        return $this;
    }

    /**
     * Get the immediate relationships results.
     */
    abstract public function getRelationResults(): Collection;

    /**
     * Get the results of the relationship.
     */
    public function getResults(): Collection
    {
        $results = $this->recursive
            ? $this->getRecursiveResults()
            : $this->getRelationResults();

        return $results->merge(
            $this->getMergingRelationResults()
        );
    }

    /**
     * Execute the callback excluding the merged query result.
     */
    protected function onceWithoutMerging(Closure $callback): mixed
    {
        $merging = $this->with;

        $this->with = null;

        $result = $callback();

        $this->with = $merging;

        return $result;
    }

    /**
     * Get the relation name.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Attach a model to the relation.
     */
    public function attach(Model|string $model): Model|string|false
    {
        return $this->attemptFailableOperation(
            $this->buildAttachCallback($model),
            $this->bypass['attach'],
            $model
        );
    }

    /**
     * Build the attach callback.
     */
    protected function buildAttachCallback(Model|string $model): Closure
    {
        return function () use ($model) {
            $foreign = $this->getAttachableForeignValue($model);

            if ($this->using) {
                return $this->using->addAttribute($this->usingKey, $foreign);
            }

            if (! $model instanceof Model) {
                $model = $this->getForeignModelByValueOrFail($model);
            }

            return $model->addAttribute($this->relationKey, $foreign);
        };
    }

    /**
     * Get the foreign model by the given value, or fail.
     *
     * @throws ModelNotFoundException
     */
    protected function getForeignModelByValueOrFail(string $model): Model
    {
        if (! is_null($model = $this->getForeignModelByValue($model))) {
            return $model;
        }

        throw ModelNotFoundException::forQuery(
            $this->query->getUnescapedQuery(),
            $this->query->getDn()
        );
    }

    /**
     * Attach a collection of models to the parent instance.
     */
    public function attachMany(iterable $models): void
    {
        foreach ($models as $model) {
            $this->attach($model);
        }

    }

    /**
     * Detach the model from the relation.
     */
    public function detach(Model|string $model): Model|string|false
    {
        return $this->attemptFailableOperation(
            $this->buildDetachCallback($model),
            $this->bypass['detach'],
            $model
        );
    }

    /**
     * Detach a collection of models from the parent instance.
     */
    public function detachMany(iterable $models): void
    {
        foreach ($models as $model) {
            $this->detach($model);
        }
    }

    /**
     * Detach the model or delete the parent if the relation is empty.
     */
    public function detachOrDeleteParent(Model|string $model): void
    {
        $count = $this->onceWithoutMerging(function () {
            return $this->count();
        });

        if ($count <= 1) {
            $this->getParent()->delete();

            return;
        }

        $this->detach($model);
    }

    /**
     * Build the detach callback.
     */
    protected function buildDetachCallback(Model|string $model): Closure
    {
        return function () use ($model) {
            $foreign = $this->getAttachableForeignValue($model);

            if ($this->using) {
                return $this->using->removeAttribute($this->usingKey, $foreign);
            }

            if (! $model instanceof Model) {
                $model = $this->getForeignModelByValueOrFail($model);
            }

            $model->removeAttribute($this->relationKey, $foreign);
        };
    }

    /**
     * Get the attachable foreign value from the model.
     */
    protected function getAttachableForeignValue(Model|string $model): string
    {
        if ($model instanceof Model) {
            return $this->using
                ? $this->getForeignValueFromModel($model)
                : $this->getParentForeignValue();
        }

        return $this->using ? $model : $this->getParentForeignValue();
    }

    /**
     * Attempt a failable operation and return the value if successful.
     *
     * If a bypassable exception is encountered, the value will be returned.
     *
     * @throws LdapRecordException
     */
    protected function attemptFailableOperation(Closure $operation, string|array $bypass, mixed $value): mixed
    {
        try {
            $operation();

            return $value;
        } catch (LdapRecordException $e) {
            if ($this->errorContainsMessage($e->getMessage(), $bypass)) {
                return $value;
            }

            throw $e;
        }
    }

    /**
     * Detach all relation models.
     */
    public function detachAll(): Collection
    {
        return $this->onceWithoutMerging(function () {
            return $this->get()->each(function (Model $model) {
                $this->detach($model);
            });
        });
    }

    /**
     * Detach all relation models or delete the model if its relation is empty.
     */
    public function detachAllOrDelete(): Collection
    {
        return $this->onceWithoutMerging(function () {
            return $this->get()->each(function (Model $model) {
                $relation = $model->getRelation($this->relationName);

                if ($relation && $relation->count() >= 1) {
                    $model->delete();
                } else {
                    $this->detach($model);
                }
            });
        });
    }

    /**
     * Get the results of the merging 'with' relation.
     */
    protected function getMergingRelationResults(): Collection
    {
        return $this->with
            ? $this->with->recursive($this->recursive)->get()
            : $this->parent->newCollection();
    }

    /**
     * Get the results for the models relation recursively.
     *
     * @param  string[]  $loaded  The distinguished names of models already loaded
     */
    protected function getRecursiveResults(array $loaded = []): Collection
    {
        $results = $this->getRelationResults()->reject(function (Model $model) use ($loaded) {
            // Here we will exclude the models that we have already
            // loaded the recursive results for so we don't run
            // into issues with circular relations in LDAP.
            return in_array($model->getDn(), $loaded);
        });

        foreach ($results as $model) {
            $loaded[] = $model->getDn();

            // Finally, we will fetch the related models relations,
            // passing along our loaded models, to ensure we do
            // not attempt fetching already loaded relations.
            $results = $results->merge(
                $this->getRecursiveRelationResults($model, $loaded)
            );
        }

        return $results;
    }

    /**
     * Get the recursive relation results for given model.
     */
    protected function getRecursiveRelationResults(Model $model, array $loaded): Collection
    {
        return ($relation = $model->getRelation($this->relationName))
            ? $relation->getRecursiveResults($loaded)
            : $model->newCollection();
    }
}
