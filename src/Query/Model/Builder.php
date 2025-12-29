<?php

namespace LdapRecord\Query\Model;

use Closure;
use DateTime;
use Exception;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelNotFoundException;
use LdapRecord\Models\Scope;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Query\Builder as QueryBuilder;
use LdapRecord\Query\BuildsQueries;
use LdapRecord\Query\ExtractsNestedFilters;
use LdapRecord\Query\Filter\AndGroup;
use LdapRecord\Query\Filter\Filter;
use LdapRecord\Query\Filter\Not;
use LdapRecord\Query\Filter\OrGroup;
use LdapRecord\Query\MultipleObjectsFoundException;
use LdapRecord\Query\Slice;
use LdapRecord\Support\ForwardsCalls;
use UnexpectedValueException;

/**
 * @mixin \LdapRecord\Query\Builder
 */
class Builder
{
    use BuildsQueries;
    use ExtractsNestedFilters;
    use ForwardsCalls;

    /**
     * The model instance being queried.
     */
    protected Model $model;

    /**
     * The base query builder instance.
     */
    protected QueryBuilder $query;

    /**
     * The global scopes to be applied.
     */
    protected array $scopes = [];

    /**
     * The removed global scopes.
     */
    protected array $removedScopes = [];

    /**
     * The applied global scopes.
     */
    protected array $appliedScopes = [];

    /**
     * The methods that should be returned from query builder.
     *
     * @var string[]
     */
    protected $passthru = [
        'escape',
        'getbasedn',
        'getcache',
        'getconnection',
        'getdn',
        'getfilters',
        'getselects',
        'gettype',
        'getunescapedquery',
        'hascontrol',
        'hasorderby',
        'hasselects',
        'insert',
        'insertandgetdn',
        'newinstance',
        'newnestedinstance',
        'query',
        'rename',
        'renameandgetdn',
        'substitutebasedn',
    ];

    /**
     * Constructor.
     */
    public function __construct(Model $model, QueryBuilder $query)
    {
        $this->model = $model;

        // In some LDAP distros, the GUID key is virtual. This means they must be
        // present in the selected attributes to be returned in search results.
        // We will preselect it to ensure it is returned in all searches.
        $this->query = $query->select([
            $this->model->getGuidKey(), '*',
        ]);
    }

    /**
     * Dynamically handle calls into the query instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->model, $scope = 'scope'.ucfirst($method))) {
            return $this->callScope([$this->model, $scope], $parameters);
        }

        if (in_array(strtolower($method), $this->passthru)) {
            return $this->toBase()->{$method}(...$parameters);
        }

        $this->forwardCallTo($this->query, $method, $parameters);

        return $this;
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     */
    public function __clone(): void
    {
        $this->query = clone $this->query;
    }

    /**
     * Apply the given scope on the current builder instance.
     */
    protected function callScope(callable $scope, array $parameters = []): static
    {
        array_unshift($parameters, $this);

        return $scope(...array_values($parameters)) ?? $this;
    }

    /**
     * Chunk the results of a paginated LDAP query.
     */
    public function chunk(int $pageSize, Closure $callback, bool $isCritical = false, bool $isolate = false): bool
    {
        return $this->toBase()->chunk($pageSize, function (array $records) use ($callback) {
            return $callback($this->model->hydrate($records));
        }, $isCritical, $isolate);
    }

    /**
     * Execute a callback over each result while chunking.
     */
    public function paginate(int $pageSize = 1000, bool $isCritical = false): Collection
    {
        return $this->model->hydrate(
            $this->toBase()->paginate(...func_get_args())
        );
    }

    /**
     * Get a slice of the results from the query.
     */
    public function slice(int $page = 1, int $perPage = 100, string $orderBy = 'cn', string $orderByDir = 'asc'): Slice
    {
        $slice = $this->toBase()->slice(...func_get_args());

        $models = $this->model->hydrate($slice->items());

        return new Slice(
            $models,
            $slice->total(),
            $slice->perPage(),
            $slice->currentPage()
        );
    }

    /**
     * Get the results of a query for a given page.
     */
    public function forPage(int $page = 1, int $perPage = 100, string $orderBy = 'cn', string $orderByDir = 'asc'): Collection
    {
        return $this->model->hydrate(
            $this->toBase()->forPage(...func_get_args())
        );
    }

    /**
     * Get the first record from the query.
     */
    public function first(array|string $selects = ['*']): ?Model
    {
        return $this->limit(1)->get($selects)->first();
    }

    /**
     * Get the first record from the query or throw an exception if none is found.
     *
     * @throws ModelNotFoundException
     */
    public function firstOrFail(array|string $selects = ['*']): Model
    {
        if (! is_null($model = $this->first($selects))) {
            return $model;
        }

        $this->throwNotFoundException(
            $this->query->getUnescapedQuery(),
            $this->query->getDn()
        );
    }

    /**
     * Get the first record from the query or throw if none is found, or if more than one is found.
     *
     * @throws ModelNotFoundException
     * @throws MultipleObjectsFoundException
     */
    public function sole(array|string $selects = ['*']): Model
    {
        $result = $this->limit(2)->get($selects);

        if ($result->isEmpty()) {
            throw new ModelNotFoundException;
        }

        if ($result->count() > 1) {
            throw new MultipleObjectsFoundException;
        }

        return $result->first();
    }

    /**
     * Find a record by its DN or an array of DNs.
     */
    public function find(array|string $dn, array|string $selects = ['*']): Model|Collection|null
    {
        if (is_array($dn)) {
            return $this->findMany($dn, $selects);
        }

        return $this->setDn($dn)->read()->first($selects);
    }

    /**
     * Find a record by DN or throw an exception if not found.
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(string $dn, array|string $selects = ['*']): Model
    {
        $entry = $this->find($dn, $selects);

        if (! $entry instanceof Model) {
            $this->throwNotFoundException(
                $this->query->getUnescapedQuery(),
                $this->query->getDn()
            );
        }

        return $entry;
    }

    /**
     * Find a record by the given attribute and value or throw if not found.
     *
     * @throws ModelNotFoundException
     */
    public function findByOrFail(string $attribute, string $value, array|string $selects = ['*']): Model
    {
        $entry = $this->findBy($attribute, $value, $selects);

        if (! $entry) {
            $this->throwNotFoundException(
                $this->query->getUnescapedQuery(),
                $this->query->getDn()
            );
        }

        return $entry;
    }

    /**
     * Find a record by the given attribute and value.
     */
    public function findBy(string $attribute, string $value, array|string $selects = ['*']): ?Model
    {
        return $this->whereEquals($attribute, $value)->first($selects);
    }

    /**
     * Find multiple records by the given DN or array of DNs.
     */
    public function findMany(array|string $dns, array|string $selects = ['*']): Collection
    {
        $dns = (array) $dns;

        $collection = $this->model->newCollection();

        foreach ($dns as $dn) {
            if ($entry = $this->find($dn, $selects)) {
                $collection->push($entry);
            }
        }

        return $collection;
    }

    /**
     * Find multiple records by the given attribute and array of values.
     */
    public function findManyBy(string $attribute, array $values = [], array|string $selects = ['*']): Collection
    {
        $this->select($selects);

        if (empty($values)) {
            return $this->model->newCollection();
        }

        $this->orFilter(function (self $query) use ($attribute, $values) {
            foreach ($values as $value) {
                $query->whereEquals($attribute, $value);
            }
        });

        return $this->get($selects);
    }

    /**
     * Finds a record using ambiguous name resolution.
     */
    public function findByAnr(array|string $value, array|string $selects = ['*']): Model|Collection|null
    {
        if (is_array($value)) {
            return $this->findManyByAnr($value, $selects);
        }

        // If the model is not compatible with ANR filters,
        // we must construct an equivalent filter that
        // the current LDAP server does support.
        if (! $this->modelIsCompatibleWithAnr()) {
            return $this->prepareAnrEquivalentQuery($value)->first($selects);
        }

        return $this->findBy('anr', $value, $selects);
    }

    /**
     * Determine if the current model is compatible with ANR filters.
     */
    protected function modelIsCompatibleWithAnr(): bool
    {
        return $this->model instanceof ActiveDirectory;
    }

    /**
     * Find a record using ambiguous name resolution.
     *
     * @throws ModelNotFoundException
     */
    public function findByAnrOrFail(string $value, array|string $selects = ['*']): Model
    {
        if (! $entry = $this->findByAnr($value, $selects)) {
            $this->throwNotFoundException($this->getUnescapedQuery(), $this->query->getDn());
        }

        return $entry;
    }

    /**
     * Find multiple records using ambiguous name resolution.
     */
    public function findManyByAnr(array $values = [], array|string $selects = ['*']): Collection
    {
        $this->select($selects);

        if (! $this->modelIsCompatibleWithAnr()) {
            foreach ($values as $value) {
                $this->prepareAnrEquivalentQuery($value);
            }

            return $this->get($selects);
        }

        return $this->findManyBy('anr', $values);
    }

    /**
     * Creates an ANR equivalent query for LDAP distributions that do not support ANR.
     */
    protected function prepareAnrEquivalentQuery(string $value): static
    {
        return $this->orFilter(function (self $query) use ($value) {
            foreach ($this->model->getAnrAttributes() as $attribute) {
                $query->whereEquals($attribute, $value);
            }
        });
    }

    /**
     * Find a record by its string GUID.
     */
    public function findByGuid(string $guid, array|string $selects = ['*']): ?Model
    {
        try {
            return $this->findByGuidOrFail($guid, $selects);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Find a record by its string GUID or throw an exception.
     *
     * @throws ModelNotFoundException
     */
    public function findByGuidOrFail(string $guid, array|string $selects = ['*']): Model
    {
        if ($this->model instanceof ActiveDirectory) {
            $guid = (new Guid($guid))->getEncodedHex();
        }

        return $this->whereRaw([
            $this->model->getGuidKey() => $guid,
        ])->firstOrFail($selects);
    }

    /**
     * Get the base query builder instance.
     */
    public function toBase(): QueryBuilder
    {
        return $this->applyScopes()->getQuery();
    }

    /**
     * Get the underlying query builder instance.
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     */
    public function setQuery(QueryBuilder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get the underlying model instance.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Set the underlying model instance.
     */
    public function setModel(Model $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Apply the global query scopes.
     */
    public function applyScopes(): static
    {
        if (empty($this->scopes)) {
            return $this;
        }

        // Scopes should not be escapable, so we will wrap the
        // application of the scopes within a nested query.
        $this->where(function (self $query) {
            foreach ($this->scopes as $identifier => $scope) {
                if (isset($this->appliedScopes[$identifier])) {
                    continue;
                }

                if ($scope instanceof Scope) {
                    $scope->apply($query, $this->getModel());
                } else {
                    $scope($this);
                }

                $this->appliedScopes[$identifier] = $scope;
            }
        });

        return $this;
    }

    /**
     * Register a new global scope.
     */
    public function withGlobalScope(string $identifier, Scope|Closure $scope): static
    {
        $this->scopes[$identifier] = $scope;

        return $this;
    }

    /**
     * Remove a registered global scope.
     */
    public function withoutGlobalScope(Scope|string $scope): static
    {
        if (! is_string($scope)) {
            $scope = get_class($scope);
        }

        unset($this->scopes[$scope]);

        $this->removedScopes[] = $scope;

        return $this;
    }

    /**
     * Remove all or passed registered global scopes.
     */
    public function withoutGlobalScopes(?array $scopes = null): static
    {
        if (! is_array($scopes)) {
            $scopes = array_keys($this->scopes);
        }

        foreach ($scopes as $scope) {
            $this->withoutGlobalScope($scope);
        }

        return $this;
    }

    /**
     * Get an array of global scopes that were removed from the query.
     */
    public function removedScopes(): array
    {
        return $this->removedScopes;
    }

    /**
     * Get an array of the global scopes that were applied to the query.
     */
    public function appliedScopes(): array
    {
        return $this->appliedScopes;
    }

    /**
     * Execute the query and get the results.
     */
    public function get(array|string $selects = ['*']): Collection
    {
        $builder = $this->applyScopes();

        $models = $builder->getModels($selects);

        return $builder->getModel()->newCollection($models);
    }

    /**
     * Select the given attributes to retrieve.
     */
    public function select(array|string $selects = ['*']): static
    {
        $selects = is_array($selects) ? $selects : func_get_args();

        // Default to all attributes if empty.
        if (empty($selects)) {
            $selects = ['*'];
        }

        // If selects are being overridden, then we need to ensure
        // the GUID key is always selected so that it may be
        // returned in the results for model hydration.
        $selects = array_values(array_unique(
            array_merge([$this->model->getGuidKey()], $selects)
        ));

        $this->query->select($selects);

        return $this;
    }

    /**
     * Add a new select to the query.
     */
    public function addSelect(array|string $select): static
    {
        $select = is_array($select) ? $select : func_get_args();

        $this->query->addSelect($select);

        return $this;
    }

    /**
     * Add a where clause to the query with proper value preparation.
     */
    public function where(Closure|array|string $attribute, mixed $operator = null, mixed $value = null, string $boolean = 'and', bool $raw = false): static
    {
        if ($attribute instanceof Closure) {
            return $this->andFilter($attribute);
        }

        if (is_array($attribute)) {
            return $this->addArrayOfWheres($attribute, $boolean, $raw);
        }

        [$value, $operator] = $this->query->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2 && ! $this->operatorRequiresValue($operator)
        );

        if (! $raw && $value !== null) {
            $value = $this->prepareWhereValue($attribute, $value);
        }

        $this->query->where($attribute, $operator, $value, $boolean, $raw);

        return $this;
    }

    /**
     * Determine if the operator requires a value to be present.
     */
    protected function operatorRequiresValue(mixed $operator): bool
    {
        return in_array($operator, ['*', '!*']);
    }

    /**
     * Add a raw where clause to the query.
     */
    public function whereRaw(array|string $attribute, ?string $operator = null, mixed $value = null): static
    {
        if (is_array($attribute)) {
            return $this->addArrayOfWheres($attribute, 'and', true);
        }

        if ($value !== null) {
            $value = $this->prepareWhereValue($attribute, $value);
        }

        $this->query->whereRaw($attribute, $operator, $value);

        return $this;
    }

    /**
     * Add an or where clause to the query.
     */
    public function orWhere(Closure|array|string $attribute, ?string $operator = null, ?string $value = null): static
    {
        if ($attribute instanceof Closure) {
            return $this->orFilter($attribute);
        }

        [$value, $operator] = $this->query->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where($attribute, $operator, $value, 'or');
    }

    /**
     * Add a raw or where clause to the query.
     */
    public function orWhereRaw(array|string $attribute, ?string $operator = null, ?string $value = null): static
    {
        if (is_array($attribute)) {
            return $this->addArrayOfWheres($attribute, 'or', true);
        }

        if ($value !== null) {
            $value = $this->prepareWhereValue($attribute, $value);
        }

        $this->query->orWhereRaw($attribute, $operator, $value);

        return $this;
    }

    /**
     * Add a where equals clause to the query.
     */
    public function whereEquals(string $attribute, string $value): static
    {
        return $this->where($attribute, '=', $value);
    }

    /**
     * Add a where not equals clause to the query.
     */
    public function whereNotEquals(string $attribute, string $value): static
    {
        return $this->where($attribute, '!', $value);
    }

    /**
     * Add a where has clause to the query.
     */
    public function whereHas(string $attribute): static
    {
        return $this->where($attribute, '*');
    }

    /**
     * Add a where not has clause to the query.
     */
    public function whereNotHas(string $attribute): static
    {
        return $this->where($attribute, '!*');
    }

    /**
     * Adds a nested 'and' filter to the current query.
     */
    public function andFilter(Closure $closure): static
    {
        $query = $this->newNestedModelInstance($closure);

        if ($filter = $query->getQuery()->getFilter()) {
            $this->query->addFilter(new AndGroup(
                ...$this->extractNestedFilters($filter)
            ), wrap: false);
        }

        return $this;
    }

    /**
     * Adds a nested 'or' filter to the current query.
     */
    public function orFilter(Closure $closure): static
    {
        $query = $this->newNestedModelInstance($closure);

        if ($filter = $query->getQuery()->getFilter()) {
            $this->query->addFilter(new OrGroup(
                ...$this->extractNestedFilters($filter)
            ), wrap: false);
        }

        return $this;
    }

    /**
     * Adds a nested 'not' filter to the current query.
     */
    public function notFilter(Closure $closure): static
    {
        $query = $this->newNestedModelInstance($closure);

        if ($filter = $query->getQuery()->getFilter()) {
            $this->query->addFilter(new Not($filter));
        }

        return $this;
    }

    /**
     * Adds a raw filter to the current query.
     */
    public function rawFilter(Filter|array|string $filters = []): static
    {
        $this->query->rawFilter($filters);

        return $this;
    }

    /**
     * Get the hydrated models from the query.
     */
    public function getModels(array|string $selects = ['*']): array
    {
        return $this->model->hydrate(
            $this->query->get($selects)
        )->all();
    }

    /**
     * Prepare the given field and value for usage in a where filter.
     *
     * @throws UnexpectedValueException
     */
    protected function prepareWhereValue(string $attribute, mixed $value = null): mixed
    {
        if (! $value instanceof DateTime) {
            return $value;
        }

        $attribute = $this->model->normalizeAttributeKey($attribute);

        if (! $this->model->isDateAttribute($attribute)) {
            throw new UnexpectedValueException(
                "Cannot convert attribute [$attribute] to an LDAP timestamp. You must add this field as a model date."
                .' Refer to https://ldaprecord.com/docs/core/v3/model-mutators/#date-mutators'
            );
        }

        return (string) $this->model->fromDateTime($value, $this->model->getDates()[$attribute]);
    }

    /**
     * Clone the model query builder.
     */
    public function clone(): static
    {
        return clone $this;
    }

    /**
     * Create a new instance of the model query builder.
     */
    public function newModelQuery(): static
    {
        return $this->model->newQuery();
    }

    /**
     * Create a new query builder instance without any model constraints.
     */
    public function newQueryWithoutScopes(): static
    {
        return $this->model->newQueryWithoutScopes();
    }

    /**
     * Returns a new nested Model Builder instance.
     */
    protected function newNestedModelInstance(Closure $closure): static
    {
        $query = (new static($this->model, $this->query->newInstance()))->nested();

        $closure($query);

        return $query;
    }

    /**
     * Throw a not found exception.
     *
     * @throws ModelNotFoundException
     */
    protected function throwNotFoundException(string $query, ?string $dn = null): void
    {
        throw ModelNotFoundException::forQuery($query, $dn);
    }
}
