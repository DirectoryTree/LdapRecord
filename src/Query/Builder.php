<?php

namespace LdapRecord\Query;

use BadMethodCallException;
use Closure;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;
use LDAP\Result;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\EscapesValues;
use LdapRecord\LdapInterface;
use LdapRecord\LdapRecordException;
use LdapRecord\Query\Events\QueryExecuted;
use LdapRecord\Query\Filter\AndGroup;
use LdapRecord\Query\Filter\Factory;
use LdapRecord\Query\Filter\Filter;
use LdapRecord\Query\Filter\GroupFilter;
use LdapRecord\Query\Filter\Not;
use LdapRecord\Query\Filter\OrGroup;
use LdapRecord\Query\Filter\Raw;
use LdapRecord\Query\Pagination\LazyPaginator;
use LdapRecord\Query\Pagination\Paginator;
use LdapRecord\Support\Arr;
use Stringable;

class Builder
{
    use BuildsQueries;
    use EscapesValues;

    public const TYPE_SEARCH = 'search';

    public const TYPE_READ = 'read';

    public const TYPE_CHUNK = 'chunk';

    public const TYPE_LIST = 'list';

    public const TYPE_PAGINATE = 'paginate';

    public const BASE_DN_PLACEHOLDER = '{base}';

    /**
     * The selected attributes to retrieve on the query.
     */
    public ?array $selects = null;

    /**
     * The query filter.
     */
    public ?Filter $filter = null;

    /**
     * The LDAP server controls to be sent.
     */
    public array $controls = [];

    /**
     * The LDAP server controls that were processed.
     */
    public array $controlsResponse = [];

    /**
     * The size limit of the query.
     */
    public int $limit = 0;

    /**
     * Determine whether the query is paginated.
     */
    public bool $paginated = false;

    /**
     * The distinguished name to perform searches upon.
     */
    protected ?string $dn = null;

    /**
     * The base distinguished name to perform searches inside.
     */
    protected ?string $baseDn = null;

    /**
     * The default query type.
     */
    protected string $type = self::TYPE_SEARCH;

    /**
     * Determine whether the query is nested.
     */
    protected bool $nested = false;

    /**
     * Determine whether the query should be cached.
     */
    protected bool $caching = false;

    /**
     * The custom cache key to use when caching results.
     */
    protected ?string $cacheKey = null;

    /**
     * How long the query should be cached until.
     */
    protected ?DateTimeInterface $cacheUntil = null;

    /**
     * Determine whether the query cache must be flushed.
     */
    protected bool $flushCache = false;

    /**
     * The current cache instance.
     */
    protected ?Cache $cache = null;

    /**
     * Constructor.
     */
    public function __construct(
        protected Connection $connection
    ) {}

    /**
     * Set the current connection.
     */
    public function setConnection(Connection $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Set the cache to store query results.
     */
    public function setCache(?Cache $cache = null): static
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Get a new Query Builder instance.
     */
    public function newInstance(?string $baseDn = null): Builder
    {
        return (new static($this->connection))->setDn(
            is_null($baseDn) ? $this->getDn() : $baseDn
        );
    }

    /**
     * Get a new nested Query Builder instance.
     */
    public function newNestedInstance(?Closure $closure = null): Builder
    {
        $query = $this->newInstance()->nested();

        if ($closure) {
            $closure($query);
        }

        return $query;
    }

    /**
     * Execute the LDAP query and return the results.
     */
    public function get(array|string $selects = ['*']): array
    {
        return $this->onceWithSelects(
            Arr::wrap($selects), fn () => $this->query($this->getQuery())
        );
    }

    /**
     * Execute the given callback while selecting the given selects.
     *
     * After running the callback, the selects are reset to the original value.
     */
    protected function onceWithSelects(array $selects, Closure $callback): mixed
    {
        $original = $this->selects;

        if (is_null($original)) {
            $this->selects = $selects;
        }

        $result = $callback();

        $this->selects = $original;

        return $result;
    }

    /**
     * Compile the query into an LDAP filter string.
     */
    public function getQuery(): string
    {
        // We need to ensure we have at least one filter, as
        // no query results will be returned otherwise.
        if (is_null($this->filter)) {
            $this->whereHas('objectclass');
        }

        return (string) $this->filter;
    }

    /**
     * Get the query filter.
     */
    public function getFilter(): ?Filter
    {
        return $this->filter;
    }

    /**
     * Get the unescaped query.
     */
    public function getUnescapedQuery(): string
    {
        return EscapedValue::unescape($this->getQuery());
    }

    /**
     * Get the current cache instance.
     */
    public function getCache(): ?Cache
    {
        return $this->cache;
    }

    /**
     * Get the current connection instance.
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get the query type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the base distinguished name of the query.
     */
    public function setBaseDn(Stringable|string|null $dn = null): static
    {
        $this->baseDn = $this->substituteBaseDn($dn);

        return $this;
    }

    /**
     * Get the base distinguished name of the query.
     */
    public function getBaseDn(): ?string
    {
        return $this->baseDn;
    }

    /**
     * Get the distinguished name of the query.
     */
    public function getDn(): ?string
    {
        return $this->dn;
    }

    /**
     * Set the distinguished name for the query.
     */
    public function setDn(Stringable|string|null $dn = null): static
    {
        $this->dn = $this->substituteBaseDn($dn);

        return $this;
    }

    /**
     * Substitute the base DN string template for the current base.
     */
    public function substituteBaseDn(Stringable|string|null $dn = null): string
    {
        return str_replace(static::BASE_DN_PLACEHOLDER, $this->baseDn ?? '', (string) $dn);
    }

    /**
     * Alias for setting the distinguished name for the query.
     */
    public function in(Stringable|string|null $dn = null): static
    {
        return $this->setDn($dn);
    }

    /**
     * Set the size limit of the query.
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Execute the given query on the LDAP connection.
     */
    public function query(string $query): array
    {
        $start = microtime(true);

        // Here we will create the execution callback. This allows us
        // to only execute an LDAP request if caching is disabled
        // or if no cache of the given query exists yet.
        $callback = fn () => $this->parse($this->run($query));

        $results = $this->getCachedResponse($query, $callback);

        $this->logQuery($this, $this->type, $this->getElapsedTime($start));

        return $this->process($results);
    }

    /**
     * Execute a pagination request on the LDAP connection.
     */
    public function paginate(int $pageSize = 1000, bool $isCritical = false): array
    {
        $this->paginated = true;

        $start = microtime(true);

        $query = $this->getQuery();

        // Here we will create the pagination callback. This allows us
        // to only execute an LDAP request if caching is disabled
        // or if no cache of the given query exists yet.
        $callback = fn () => $this->runPaginate($query, $pageSize, $isCritical);

        $pages = $this->getCachedResponse($query, $callback);

        $this->logQuery($this, self::TYPE_PAGINATE, $this->getElapsedTime($start));

        return $this->process($pages);
    }

    /**
     * Runs the paginate operation with the given filter.
     */
    protected function runPaginate(string $filter, int $perPage, bool $isCritical): array
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $this->newPaginator($filter, $perPage, $isCritical)->execute($ldap)
        );
    }

    /**
     * Make a new paginator instance.
     */
    protected function newPaginator(string $filter, int $perPage, bool $isCritical): Paginator
    {
        return new Paginator($this, $filter, $perPage, $isCritical);
    }

    /**
     * Chunk the results of a paginated LDAP query.
     */
    public function chunk(int $pageSize, Closure $callback, bool $isCritical = false, bool $isolate = false): bool
    {
        $this->limit(0);

        $start = microtime(true);

        $chunk = function (Builder $query) use ($pageSize, $callback, $isCritical) {
            $page = 1;

            foreach ($query->runChunk($this->getQuery(), $pageSize, $isCritical) as $chunk) {
                if ($callback($this->process($chunk), $page) === false) {
                    return false;
                }

                $page++;
            }

            return true;
        };

        // Connection isolation creates a new, temporary connection for the pagination
        // request to occur on. This allows connections that do not support executing
        // other queries during a pagination request, to do so without interruption.
        $result = $isolate ? $this->connection->isolate(
            fn (Connection $replicate) => $chunk($this->clone()->setConnection($replicate))
        ) : $chunk($this);

        $this->logQuery($this, self::TYPE_CHUNK, $this->getElapsedTime($start));

        return $result;
    }

    /**
     * Runs the chunk operation with the given filter.
     */
    protected function runChunk(string $filter, int $perPage, bool $isCritical): Generator
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $this->newLazyPaginator($filter, $perPage, $isCritical)->execute($ldap)
        );
    }

    /**
     * Make a new lazy paginator instance.
     */
    protected function newLazyPaginator(string $filter, int $perPage, bool $isCritical): LazyPaginator
    {
        return new LazyPaginator($this, $filter, $perPage, $isCritical);
    }

    /**
     * Get a slice of the results from the query.
     */
    public function slice(int $page = 1, int $perPage = 100, string $orderBy = 'cn', string $orderByDir = 'asc'): Slice
    {
        $results = $this->forPage($page, $perPage, $orderBy, $orderByDir);

        $total = $this->controlsResponse[LDAP_CONTROL_VLVRESPONSE]['value']['count'] ?? 0;

        // Some LDAP servers seem to have an issue where the last result in a virtual
        // list view will always be returned, regardless of the offset being larger
        // than the result itself. In this case, we will manually return an empty
        // response so that no objects are deceivingly included in the slice.
        $objects = $page > max((int) ceil($total / $perPage), 1) ? [] : $results;

        return new Slice($objects, $total, $perPage, $page);
    }

    /**
     * Get the results of a query for a given page.
     */
    public function forPage(int $page = 1, int $perPage = 100, string $orderBy = 'cn', string $orderByDir = 'asc'): array
    {
        if (! $this->hasOrderBy()) {
            $this->orderBy($orderBy, $orderByDir);
        }

        $this->addControl(LDAP_CONTROL_VLVREQUEST, true, [
            'before' => 0,
            'after' => $perPage - 1,
            'offset' => ($page * $perPage) - $perPage + 1,
            'count' => 0,
        ]);

        return $this->get();
    }

    /**
     * Processes the results of the query.
     */
    protected function process(array $results): array
    {
        unset($results['count']);

        if ($this->paginated) {
            return $this->flattenPages($results);
        }

        return $results;
    }

    /**
     * Flattens LDAP paged results into a single array.
     */
    protected function flattenPages(array $pages): array
    {
        $records = [];

        foreach ($pages as $page) {
            unset($page['count']);

            $records = array_merge($records, $page);
        }

        return $records;
    }

    /**
     * Get the cached response or execute and cache the callback value.
     */
    protected function getCachedResponse(string $query, Closure $callback): mixed
    {
        if ($this->cache && $this->caching) {
            $key = $this->cacheKey ?? $this->getCacheKey($query);

            if ($this->flushCache) {
                $this->cache->delete($key);
            }

            return $this->cache->remember($key, $this->cacheUntil, $callback);
        }

        try {
            return $callback();
        } finally {
            $this->caching = false;
            $this->cacheKey = null;
            $this->cacheUntil = null;
            $this->flushCache = false;
        }
    }

    /**
     * Runs the query operation with the given filter.
     */
    public function run(string $filter): mixed
    {
        return $this->connection->run(function (LdapInterface $ldap) use ($filter) {
            // We will avoid setting the controls during any pagination
            // requests as it will clear the cookie we need to send
            // to the server upon retrieving every page.
            if (! $this->paginated) {
                // Before running the query, we will set the LDAP server controls. This
                // allows the controls to be automatically reset upon each new query
                // that is conducted on the same connection during each request.
                $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $this->controls);
            }

            return $ldap->{$this->type}(
                (string) ($this->dn ?? $this->baseDn),
                $filter,
                $this->getSelects(),
                $onlyAttributes = false,
                $this->limit
            );
        });
    }

    /**
     * Parses the given LDAP resource by retrieving its entries.
     */
    public function parse(mixed $resource): array
    {
        if (! $resource) {
            return [];
        }

        return $this->connection->run(function (LdapInterface $ldap) use ($resource) {
            $this->controlsResponse = $this->controls;

            // Process the server controls response.
            $ldap->parseResult(
                result: $resource,
                controls: $this->controlsResponse
            );

            $entries = $ldap->getEntries($resource);

            // Free up memory.
            if (is_resource($resource) || $resource instanceof Result) {
                $ldap->freeResult($resource);
            }

            return $entries;
        });
    }

    /**
     * Get the cache key.
     */
    protected function getCacheKey(string $query): string
    {
        $host = $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->getHost()
        );

        $key = $host
            .$this->type
            .$this->getDn()
            .$query
            .implode($this->getSelects())
            .$this->limit
            .$this->paginated;

        return md5($key);
    }

    /**
     * Find a record by the specified attribute and value.
     */
    public function findBy(string $attribute, string $value, array|string $selects = ['*']): ?array
    {
        try {
            return $this->findByOrFail($attribute, $value, $selects);
        } catch (ObjectNotFoundException) {
            return null;
        }
    }

    /**
     * Find a record by the specified attribute and value.
     *
     * If no record is found an exception is thrown.
     *
     * @throws ObjectNotFoundException
     */
    public function findByOrFail(string $attribute, string $value, array|string $selects = ['*']): array
    {
        return $this->whereEquals($attribute, $value)->firstOrFail($selects);
    }

    /**
     * Find many records by distinguished name.
     */
    public function findMany(array|string $dns, array|string $selects = ['*']): array
    {
        if (empty($dns)) {
            return $this->process([]);
        }

        $objects = [];

        foreach ((array) $dns as $dn) {
            if (! is_null($object = $this->find($dn, $selects))) {
                $objects[] = $object;
            }
        }

        return $this->process($objects);
    }

    /**
     * Find many records by the specified attribute.
     */
    public function findManyBy(string $attribute, array $values = [], array|string $selects = ['*']): array
    {
        $query = $this->select($selects);

        foreach ($values as $value) {
            $query->orWhere([$attribute => $value]);
        }

        return $query->get();
    }

    /**
     * Find a record by its distinguished name.
     */
    public function find(array|string $dn, array|string $selects = ['*']): ?array
    {
        if (is_array($dn)) {
            return $this->findMany($dn, $selects);
        }

        try {
            return $this->findOrFail($dn, $selects);
        } catch (ObjectNotFoundException) {
            return null;
        }
    }

    /**
     * Find a record by its distinguished name.
     *
     * Fails upon no records returned.
     *
     * @throws ObjectNotFoundException
     */
    public function findOrFail(string $dn, array|string $selects = ['*']): array
    {
        return $this->setDn($dn)
            ->read()
            ->whereHas('objectclass')
            ->firstOrFail($selects);
    }

    /**
     * Select the given attributes to retrieve.
     */
    public function select(array|string $selects = ['*']): static
    {
        $selects = is_array($selects) ? $selects : func_get_args();

        if (! empty($selects)) {
            $this->selects = $selects;
        }

        return $this;
    }

    /**
     * Add a selected attribute to the query.
     */
    public function addSelect(array|string $select): static
    {
        $select = is_array($select) ? $select : func_get_args();

        $this->selects = array_merge((array) $this->selects, $select);

        return $this;
    }

    /**
     * Add an order by control to the query.
     */
    public function orderBy(string $attribute, string $direction = 'asc', array $options = []): static
    {
        return $this->addControl(LDAP_CONTROL_SORTREQUEST, true, [
            [
                ...$options,
                'attr' => $attribute,
                'reverse' => $direction === 'desc',
            ],
        ]);
    }

    /**
     * Add an order by descending control to the query.
     */
    public function orderByDesc(string $attribute, array $options = []): static
    {
        return $this->orderBy($attribute, 'desc', $options);
    }

    /**
     * Determine if the query has a sotr request control header.
     */
    public function hasOrderBy(): bool
    {
        return $this->hasControl(LDAP_CONTROL_SORTREQUEST);
    }

    /**
     * Add a raw filter to the query.
     */
    public function rawFilter(Filter|array|string $filters = []): static
    {
        $filters = is_array($filters) ? $filters : func_get_args();

        foreach ($filters as $filter) {
            $this->addFilter(
                $filter instanceof Filter ? $filter : new Raw($filter)
            );
        }

        return $this;
    }

    /**
     * Add a nested 'and' filter to the query.
     */
    public function andFilter(Closure $closure): static
    {
        $query = $this->newNestedInstance($closure);

        if ($filter = $query->getFilter()) {
            $this->addFilter(new AndGroup(
                ...$this->extractNestedFilters($filter)
            ), wrap: false);
        }

        return $this;
    }

    /**
     * Add a nested 'or' filter to the query.
     */
    public function orFilter(Closure $closure): static
    {
        $query = $this->newNestedInstance($closure);

        if ($filter = $query->getFilter()) {
            $this->addFilter(new OrGroup(
                ...$this->extractNestedFilters($filter)
            ), wrap: false);
        }

        return $this;
    }

    /**
     * Extract filters from a nested group filter for re-wrapping, preserving nested groups.
     *
     * @return array<Filter>
     */
    protected function extractNestedFilters(Filter $filter): array
    {
        if (! $filter instanceof GroupFilter) {
            return [$filter];
        }

        $children = $filter->getFilters();

        // If any child is a group, preserve the structure
        foreach ($children as $child) {
            if ($child instanceof GroupFilter) {
                return $children;
            }
        }

        // All children are non-groups, it's safe to unwrap.
        return $children;
    }

    /**
     * Add a nested 'not' filter to the query.
     */
    public function notFilter(Closure $closure): static
    {
        $query = $this->newNestedInstance($closure);

        if ($filter = $query->getFilter()) {
            $this->addFilter(new Not($filter));
        }

        return $this;
    }

    /**
     * Add a where clause to the query.
     *
     * @throws InvalidArgumentException
     */
    public function where(Closure|array|string $attribute, mixed $operator = null, mixed $value = null, string $boolean = 'and', bool $raw = false): static
    {
        if ($attribute instanceof Closure) {
            return $this->andFilter($attribute);
        }

        if (is_array($attribute)) {
            return $this->addArrayOfWheres($attribute, $boolean, $raw);
        }

        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2 && ! $this->operatorRequiresValue($operator)
        );

        if (! in_array($operator, Factory::operators())) {
            throw new InvalidArgumentException("Invalid LDAP filter operator [$operator]");
        }

        $value = $this->prepareWhereValue($attribute, $value, $raw);

        $attribute = $this->escape($attribute)->forDnAndFilter()->get();

        $filter = Factory::make($operator, $attribute, $value);

        $this->addFilter($filter, $boolean);

        return $this;
    }

    /**
     * Prepare the value and operator for a where clause.
     */
    public function prepareValueAndOperator(mixed $value, mixed $operator, bool $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        }

        return [$value, $operator];
    }

    /**
     * Determine if the operator requires a value to be present.
     */
    protected function operatorRequiresValue(mixed $operator): bool
    {
        return in_array($operator, ['*', '!*']);
    }

    /**
     * Prepare the value for being queried.
     */
    protected function prepareWhereValue(string $attribute, mixed $value = null, bool $raw = false): string
    {
        return $raw ? $value : $this->escape($value)->get();
    }

    /**
     * Add a raw where clause to the query.
     *
     * Values given to this method are not escaped.
     */
    public function whereRaw(array|string $attribute, ?string $operator = null, mixed $value = null): static
    {
        return $this->where($attribute, $operator, $value, 'and', true);
    }

    /**
     * Add a 'where equals' clause to the query.
     */
    public function whereEquals(string $attribute, string $value): static
    {
        return $this->where($attribute, '=', $value);
    }

    /**
     * Add a 'where not equals' clause to the query.
     */
    public function whereNotEquals(string $attribute, string $value): static
    {
        return $this->where($attribute, '!', $value);
    }

    /**
     * Add a 'where approximately equals' clause to the query.
     */
    public function whereApproximatelyEquals(string $attribute, string $value): static
    {
        return $this->where($attribute, '~=', $value);
    }

    /**
     * Add a 'where has' clause to the query.
     */
    public function whereHas(string $attribute): static
    {
        return $this->where($attribute, '*');
    }

    /**
     * Add a 'where not has' clause to the query.
     */
    public function whereNotHas(string $attribute): static
    {
        return $this->where($attribute, '!*');
    }

    /**
     * Add a 'where contains' clause to the query.
     */
    public function whereContains(string $attribute, string $value): static
    {
        return $this->where($attribute, 'contains', $value);
    }

    /**
     * Add a 'where contains' clause to the query.
     */
    public function whereNotContains(string $attribute, string $value): static
    {
        return $this->where($attribute, 'not_contains', $value);
    }

    /**
     * Query for entries that match any of the values provided for the given field.
     */
    public function whereIn(string $attribute, array $values): static
    {
        if (empty($values)) {
            // If the array of values is empty, we will
            // add an empty OR filter to the query to
            // ensure that no results are returned.
            $this->addFilter(new OrGroup);

            return $this;
        }

        $query = $this->newNestedInstance(function (Builder $query) use ($attribute, $values) {
            foreach ($values as $value) {
                $query->orWhereEquals($attribute, $value);
            }
        });

        if ($filter = $query->getFilter()) {
            $this->addFilter($filter);
        }

        return $this;
    }

    /**
     * Add a 'between' clause to the query.
     */
    public function whereBetween(string $attribute, array $values): static
    {
        return $this->where([
            [$attribute, '>=', $values[0]],
            [$attribute, '<=', $values[1]],
        ]);
    }

    /**
     * Add a 'where starts with' clause to the query.
     */
    public function whereStartsWith(string $attribute, string $value): static
    {
        return $this->where($attribute, 'starts_with', $value);
    }

    /**
     * Add a 'where *not* starts with' clause to the query.
     */
    public function whereNotStartsWith(string $attribute, string $value): static
    {
        return $this->where($attribute, 'not_starts_with', $value);
    }

    /**
     * Add a 'where ends with' clause to the query.
     */
    public function whereEndsWith(string $attribute, string $value): static
    {
        return $this->where($attribute, 'ends_with', $value);
    }

    /**
     * Add a 'where *not* ends with' clause to the query.
     */
    public function whereNotEndsWith(string $attribute, string $value): static
    {
        return $this->where($attribute, 'not_ends_with', $value);
    }

    /**
     * Only include deleted entries in the results.
     */
    public function whereDeleted(): static
    {
        return $this->withDeleted()->whereEquals('isDeleted', 'TRUE');
    }

    /**
     * Set the LDAP control option to include deleted LDAP entries.
     */
    public function withDeleted(): static
    {
        return $this->addControl(LdapInterface::OID_SERVER_SHOW_DELETED, $isCritical = true);
    }

    /**
     * Add a server control to the query.
     */
    public function addControl(string $oid, bool $isCritical = false, mixed $value = null): static
    {
        $this->controls[$oid] = compact('oid', 'isCritical', 'value');

        return $this;
    }

    /**
     * Determine if the server control exists on the query.
     */
    public function hasControl(string $oid): bool
    {
        return array_key_exists($oid, $this->controls);
    }

    /**
     * Add an 'or where' clause to the query.
     */
    public function orWhere(Closure|array|string $attribute, ?string $operator = null, ?string $value = null): static
    {
        if ($attribute instanceof Closure) {
            return $this->orFilter($attribute);
        }

        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2 && ! $this->operatorRequiresValue($operator)
        );

        return $this->where($attribute, $operator, $value, 'or');
    }

    /**
     * Add a raw or where clause to the query.
     *
     * Values given to this method are not escaped.
     */
    public function orWhereRaw(array|string $attribute, ?string $operator = null, ?string $value = null): static
    {
        return $this->where($attribute, $operator, $value, 'or', true);
    }

    /**
     * Add an 'or where has' clause to the query.
     */
    public function orWhereHas(string $attribute): static
    {
        return $this->orWhere($attribute, '*');
    }

    /**
     * Add a 'where not has' clause to the query.
     */
    public function orWhereNotHas(string $attribute): static
    {
        return $this->orWhere($attribute, '!*');
    }

    /**
     * Add an 'or where equals' clause to the query.
     */
    public function orWhereEquals(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, '=', $value);
    }

    /**
     * Add an 'or where not equals' clause to the query.
     */
    public function orWhereNotEquals(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, '!', $value);
    }

    /**
     * Add a 'or where approximately equals' clause to the query.
     */
    public function orWhereApproximatelyEquals(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, '~=', $value);
    }

    /**
     * Add an 'or where contains' clause to the query.
     */
    public function orWhereContains(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, 'contains', $value);
    }

    /**
     * Add an 'or where *not* contains' clause to the query.
     */
    public function orWhereNotContains(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, 'not_contains', $value);
    }

    /**
     * Add an 'or where starts with' clause to the query.
     */
    public function orWhereStartsWith(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, 'starts_with', $value);
    }

    /**
     * Add an 'or where *not* starts with' clause to the query.
     */
    public function orWhereNotStartsWith(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, 'not_starts_with', $value);
    }

    /**
     * Add an 'or where ends with' clause to the query.
     */
    public function orWhereEndsWith(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, 'ends_with', $value);
    }

    /**
     * Add an 'or where *not* ends with' clause to the query.
     */
    public function orWhereNotEndsWith(string $attribute, string $value): static
    {
        return $this->orWhere($attribute, 'not_ends_with', $value);
    }

    /**
     * Add a filter to the query.
     */
    public function addFilter(Filter $filter, string $boolean = 'and', bool $wrap = true): static
    {
        if (is_null($this->filter)) {
            $this->filter = $filter;

            return $this;
        }

        // Flatten same-type groups to avoid deeply nested structures.
        // Ex: AndGroup(AndGroup(a, b), c) becomes AndGroup(a, b, c)
        if ($boolean === 'or') {
            $this->filter = $this->filter instanceof OrGroup && $wrap
                ? new OrGroup(...[...$this->filter->getFilters(), $filter])
                : new OrGroup($this->filter, $filter);
        } else {
            $this->filter = $this->filter instanceof AndGroup && $wrap
                ? new AndGroup(...[...$this->filter->getFilters(), $filter])
                : new AndGroup($this->filter, $filter);
        }

        return $this;
    }

    /**
     * Clear the query filters.
     */
    public function clearFilters(): static
    {
        $this->filter = null;

        return $this;
    }

    /**
     * Determine if the query has attributes selected.
     */
    public function hasSelects(): bool
    {
        return count($this->selects ?? []) > 0;
    }

    /**
     * Get the attributes to select on the search.
     */
    public function getSelects(): array
    {
        $selects = $this->selects ?? ['*'];

        if (in_array('*', $selects)) {
            return $selects;
        }

        if (in_array('objectclass', $selects)) {
            return $selects;
        }

        // If the * character is not provided in the selected attributes,
        // we need to ensure we always select the object class, as
        // this is used for constructing entries properly.
        $selects[] = 'objectclass';

        return $selects;
    }

    /**
     * Set the query to search on the base distinguished name.
     *
     * This will result in one record being returned.
     */
    public function read(): static
    {
        $this->type = self::TYPE_READ;

        return $this;
    }

    /**
     * Set the query to search one level on the base distinguished name.
     */
    public function list(): static
    {
        $this->type = self::TYPE_LIST;

        return $this;
    }

    /**
     * Alias for the "search" method.
     */
    public function recursive(): static
    {
        return $this->search();
    }

    /**
     * Set the query to search the entire directory on the base distinguished name.
     */
    public function search(): static
    {
        $this->type = self::TYPE_SEARCH;

        return $this;
    }

    /**
     * Whether to mark the query as nested.
     */
    public function nested(bool $nested = true): static
    {
        $this->nested = $nested;

        return $this;
    }

    /**
     * Enables caching on the query until the given date.
     *
     * If flushing is enabled, the query cache will be flushed and then re-cached.
     */
    public function cache(?DateTimeInterface $until = null, bool $flush = false, ?string $key = null): static
    {
        $this->caching = true;
        $this->cacheKey = $key;
        $this->cacheUntil = $until;
        $this->flushCache = $flush;

        return $this;
    }

    /**
     * Determine if the query is nested.
     */
    public function isNested(): bool
    {
        return $this->nested === true;
    }

    /**
     * Determine whether the query is paginated.
     */
    public function isPaginated(): bool
    {
        return $this->paginated;
    }

    /**
     * Insert an entry into the directory.
     *
     * @throws LdapRecordException
     */
    public function insert(string $dn, array $attributes): bool
    {
        return (bool) $this->insertAndGetDn($dn, $attributes);
    }

    /**
     * Insert an entry into the directory and get the inserted distinguished name.
     *
     * @throws LdapRecordException
     */
    public function insertAndGetDn(string $dn, array $attributes): string|false
    {
        $dn = $this->substituteBaseDn($dn);

        if (empty($dn)) {
            throw new LdapRecordException('A new LDAP object must have a distinguished name (dn).');
        }

        if (! array_key_exists('objectclass', $attributes)) {
            throw new LdapRecordException(
                'A new LDAP object must contain at least one object class (objectclass) to be created.'
            );
        }

        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->add($dn, $attributes)
        ) ? $dn : false;
    }

    /**
     * Add attributes to an entry in the directory.
     */
    public function add(string $dn, array $attributes): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->modAdd($dn, $attributes)
        );
    }

    /**
     * Update the entry with the given modifications.
     */
    public function update(string $dn, array $modifications): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->modifyBatch($dn, $modifications)
        );
    }

    /**
     * Replace an entry's attributes in the directory.
     */
    public function replace(string $dn, array $attributes): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->modReplace($dn, $attributes)
        );
    }

    /**
     * Delete an entry from the directory.
     */
    public function delete(string $dn): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->delete($dn)
        );
    }

    /**
     * Remove attributes on the entry in the directory.
     */
    public function remove(string $dn, array $attributes): bool
    {
        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->modDelete($dn, $attributes)
        );
    }

    /**
     * Rename an entry in the directory.
     */
    public function rename(string $dn, string $rdn, string $newParentDn, bool $deleteOldRdn = true): bool
    {
        return (bool) $this->renameAndGetDn($dn, $rdn, $newParentDn, $deleteOldRdn);
    }

    /**
     * Rename an entry in the directory and get the new distinguished name.
     */
    public function renameAndGetDn(string $dn, string $rdn, string $newParentDn, bool $deleteOldRdn = true): string|false
    {
        $newParentDn = $this->substituteBaseDn($newParentDn);

        return $this->connection->run(
            fn (LdapInterface $ldap) => $ldap->rename($dn, $rdn, $newParentDn, $deleteOldRdn)
        ) ? implode(',', [$rdn, $newParentDn]) : false;
    }

    /**
     * Clone the query.
     */
    public function clone(): static
    {
        return clone $this;
    }

    /**
     * Handle dynamic method calls on the query builder.
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters): static
    {
        // If the beginning of the method being called contains
        // 'where', we will assume a dynamic 'where' clause is
        // being performed and pass the parameters to it.
        if (str_starts_with($method, 'where')) {
            return $this->dynamicWhere($method, $parameters);
        }

        throw new BadMethodCallException(sprintf(
            'Call to undefined method %s::%s()',
            static::class,
            $method
        ));
    }

    /**
     * Handles dynamic "where" clauses to the query.
     *
     * @return $this
     */
    public function dynamicWhere(string $method, array $parameters): static
    {
        $finder = substr($method, 5);

        $segments = preg_split('/(And|Or)(?=[A-Z])/', $finder, -1, PREG_SPLIT_DELIM_CAPTURE);

        // The connector variable will determine which connector will be used for the
        // query condition. We will change it as we come across new boolean values
        // in the dynamic method strings, which could contain a number of these.
        $connector = 'and';

        $index = 0;

        foreach ($segments as $segment) {
            // If the segment is not a boolean connector, we can assume it is a column's name
            // and we will add it to the query as a new constraint as a where clause, then
            // we can keep iterating through the dynamic method string's segments again.
            if ($segment != 'And' && $segment != 'Or') {
                $this->addDynamic($segment, $connector, $parameters, $index);

                $index++;
            }

            // Otherwise, we will store the connector so we know how the next where clause we
            // find in the query should be connected to the previous ones, meaning we will
            // have the proper boolean connector to connect the next where clause found.
            else {
                $connector = $segment;
            }
        }

        return $this;
    }

    /**
     * Add a single dynamic where clause statement to the query.
     */
    protected function addDynamic(string $segment, string $connector, array $parameters, int $index): void
    {
        // If no parameters were given to the dynamic where clause,
        // we can assume a "has" attribute filter is being added.
        if (count($parameters) === 0) {
            $this->where(strtolower($segment), '*', null, strtolower($connector));
        } else {
            $this->where(strtolower($segment), '=', $parameters[$index], strtolower($connector));
        }
    }

    /**
     * Logs the given executed query information by firing its query event.
     */
    protected function logQuery(Builder $query, string $type, ?float $time = null): void
    {
        $args = [$query, $time];

        $this->fireQueryEvent(
            match ($type) {
                self::TYPE_READ => new Events\Read(...$args),
                self::TYPE_CHUNK => new Events\Chunk(...$args),
                self::TYPE_LIST => new Events\Listing(...$args),
                self::TYPE_PAGINATE => new Events\Paginate(...$args),
                default => new Events\Search(...$args),
            }
        );
    }

    /**
     * Throw a not found exception.
     *
     * @throws ObjectNotFoundException
     */
    protected function throwNotFoundException(string $query, ?string $dn = null): void
    {
        throw ObjectNotFoundException::forQuery($query, $dn);
    }

    /**
     * Fires the given query event.
     */
    protected function fireQueryEvent(QueryExecuted $event): void
    {
        Container::getInstance()->getDispatcher()->fire($event);
    }

    /**
     * Get the elapsed time since a given starting point.
     */
    protected function getElapsedTime(float $start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }
}
