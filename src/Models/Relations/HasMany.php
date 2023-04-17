<?php

namespace LdapRecord\Models\Relations;

use Closure;
use LdapRecord\DetectsErrors;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelNotFoundException;
use LdapRecord\Query\Model\Builder;

class HasMany extends OneToMany
{
    use DetectsErrors;

    /**
     * The model to use for attaching / detaching.
     */
    protected ?Model $using = null;

    /**
     * The attribute key to use for attaching / detaching.
     */
    protected ?string $usingKey = null;

    /**
     * The pagination page size.
     */
    protected int $pageSize = 1000;

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
     * Set the model and attribute to use for attaching / detaching.
     */
    public function using(Model $using, string $usingKey): static
    {
        $this->using = $using;
        $this->usingKey = $usingKey;

        return $this;
    }

    /**
     * Set the pagination page size of the relation query.
     */
    public function setPageSize(int $pageSize): static
    {
        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * Paginate the relation using the given page size.
     */
    public function paginate(int $pageSize = 1000): Collection
    {
        return $this->paginateOnceUsing($pageSize);
    }

    /**
     * Paginate the relation using the page size once.
     */
    protected function paginateOnceUsing(int $pageSize): Collection
    {
        $size = $this->pageSize;

        $result = $this->setPageSize($pageSize)->get();

        $this->pageSize = $size;

        return $result;
    }

    /**
     * Execute a callback over each result while chunking.
     */
    public function each(Closure $callback, int $pageSize = 1000): bool
    {
        return $this->chunk($pageSize, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Chunk the relation results using the given callback.
     */
    public function chunk(int $pageSize, Closure $callback): bool
    {
        return $this->chunkRelation($pageSize, $callback);
    }

    /**
     * Execute the callback over chunks of relation results.
     */
    protected function chunkRelation(int $pageSize, Closure $callback, array $loaded = []): bool
    {
        return $this->getRelationQuery()->chunk($pageSize, function (Collection $results) use ($pageSize, $callback, $loaded) {
            $models = $this->transformResults($results)->when($this->recursive, function (Collection $models) use ($loaded) {
                return $models->reject(function (Model $model) use ($loaded) {
                    return in_array($model->getDn(), $loaded);
                });
            });

            if ($callback($models) === false) {
                return false;
            }

            $models->when($this->recursive, function (Collection $models) use ($pageSize, $callback, $loaded) {
                $models->each(function (Model $model) use ($pageSize, $callback, $loaded) {
                    if ($relation = $model->getRelation($this->relationName)) {
                        $loaded[] = $model->getDn();

                        return $relation->recursive()->chunkRelation($pageSize, $callback, $loaded);
                    }
                });
            });
        });
    }

    /**
     * Get the relationships results.
     */
    public function getRelationResults(): Collection
    {
        return $this->transformResults(
            $this->getRelationQuery()->paginate($this->pageSize)
        );
    }

    /**
     * Get the prepared relationship query.
     */
    public function getRelationQuery(): Builder
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
        if (! in_array('*', $columns) && ! in_array($key, $columns)) {
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
                return $this->using->createAttribute($this->usingKey, $foreign);
            }

            if (! $model instanceof Model) {
                $model = $this->getForeignModelByValueOrFail($model);
            }

            return $model->createAttribute($this->relationKey, $foreign);
        };
    }

    /**
     * Attach a collection of models to the parent instance.
     */
    public function attachMany(iterable $models): iterable
    {
        foreach ($models as $model) {
            $this->attach($model);
        }

        return $models;
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
                return $this->using->deleteAttribute([$this->usingKey => $foreign]);
            }

            if (! $model instanceof Model) {
                $model = $this->getForeignModelByValueOrFail($model);
            }

            $model->deleteAttribute([$this->relationKey => $foreign]);
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
}
