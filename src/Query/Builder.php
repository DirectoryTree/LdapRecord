<?php

namespace LdapRecord\Query;

use Closure;
use LdapRecord\Ldap;
use DateInterval;
use LdapRecord\Container;
use LdapRecord\Utilities;
use LdapRecord\Connection;
use BadMethodCallException;
use LdapRecord\Models\Model;
use InvalidArgumentException;
use Tightenco\Collect\Support\Arr;
use LdapRecord\LdapRecordException;
use LdapRecord\Query\Events\QueryExecuted;
use LdapRecord\Models\ModelNotFoundException;
use LdapRecord\Query\Model\Builder as ModelBuilder;

class Builder
{
    /**
     * The selected columns to retrieve on the query.
     *
     * @var array
     */
    public $columns;

    /**
     * The query filters.
     *
     * @var array
     */
    public $filters = [
        'and' => [],
        'or'  => [],
        'raw' => [],
    ];

    /**
     * The LDAP server controls to be sent.
     *
     * @var array
     */
    public $controls = [];

    /**
     * The size limit of the query.
     *
     * @var int
     */
    public $limit = 0;

    /**
     * Determines whether the current query is paginated.
     *
     * @var bool
     */
    public $paginated = false;

    /**
     * The distinguished name to perform searches upon.
     *
     * @var string|null
     */
    protected $dn;

    /**
     * The default query type.
     *
     * @var string
     */
    protected $type = 'search';

    /**
     * Determines whether the query is nested.
     *
     * @var bool
     */
    protected $nested = false;

    /**
     * Determines whether the query should be cached.
     *
     * @var bool
     */
    protected $caching = false;

    /**
     * How long the query should be cached until.
     *
     * @var DateInterval|null
     */
    protected $cacheUntil = null;

    /**
     * Determines whether the query cache must be flushed.
     *
     * @var bool
     */
    protected $flushCache = false;

    /**
     * The current connection instance.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * The current grammar instance.
     *
     * @var Grammar
     */
    protected $grammar;

    /**
     * The current cache instance.
     *
     * @var Cache|null
     */
    protected $cache;

    /**
     * Constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->grammar = new Grammar();
    }

    /**
     * Sets the current connection.
     *
     * @param Connection $connection
     *
     * @return $this
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Sets the current filter grammar.
     *
     * @param Grammar $grammar
     *
     * @return $this
     */
    public function setGrammar(Grammar $grammar)
    {
        $this->grammar = $grammar;

        return $this;
    }

    /**
     * Sets the cache to store query results.
     *
     * @param Cache|null $cache
     *
     * @return $this
     */
    public function setCache(Cache $cache = null)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Returns a new Query Builder instance.
     *
     * @param string $baseDn
     *
     * @return $this
     */
    public function newInstance($baseDn = null)
    {
        // We'll set the base DN of the new Builder so
        // developers don't need to do this manually.
        $dn = is_null($baseDn) ? $this->getDn() : $baseDn;

        return (new static($this->connection))->setDn($dn);
    }

    /**
     * Returns a new nested Query Builder instance.
     *
     * @param Closure|null $closure
     *
     * @return $this
     */
    public function newNestedInstance(Closure $closure = null)
    {
        $query = $this->newInstance()->nested();

        if ($closure) {
            $closure($query);
        }

        return $query;
    }

    /**
     * Executes the LDAP query.
     *
     * @param array $columns
     *
     * @return Collection|array
     */
    public function get($columns = ['*'])
    {
        return $this->onceWithColumns(Arr::wrap($columns), function () {
            return $this->query($this->getQuery());
        });
    }

    /**
     * Execute the given callback while selecting the given columns.
     *
     * After running the callback, the columns are reset to the original value.
     *
     * @param array    $columns
     * @param callable $callback
     *
     * @return mixed
     */
    protected function onceWithColumns($columns, $callback)
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $result = $callback();

        $this->columns = $original;

        return $result;
    }

    /**
     * Compiles and returns the current query string.
     *
     * @return string
     */
    public function getQuery()
    {
        // We need to ensure we have at least one filter, as
        // no query results will be returned otherwise.
        if (count(array_filter($this->filters)) === 0) {
            $this->whereHas('objectclass');
        }

        return $this->grammar->compile($this);
    }

    /**
     * Returns the unescaped query.
     *
     * @return string
     */
    public function getUnescapedQuery()
    {
        return Utilities::unescape($this->getQuery());
    }

    /**
     * Returns the current Grammar instance.
     *
     * @return Grammar
     */
    public function getGrammar()
    {
        return $this->grammar;
    }

    /**
     * Returns the current Connection instance.
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Returns the query type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns the builders DN to perform searches upon.
     *
     * @return string
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * Sets the DN to perform searches upon.
     *
     * @param string|Model|null $dn
     *
     * @return $this
     */
    public function setDn($dn = null)
    {
        $this->dn = $dn instanceof Model ? $dn->getDn() : $dn;

        return $this;
    }

    /**
     * Alias for setting the base DN of the query.
     *
     * @param string|Model|null $dn
     *
     * @return $this
     */
    public function in($dn = null)
    {
        return $this->setDn($dn);
    }

    /**
     * Sets the size limit of the current query.
     *
     * @param int $limit
     *
     * @return $this
     */
    public function limit($limit = 0)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Returns a new query for the given model.
     *
     * @param Model $model
     *
     * @return ModelBuilder
     */
    public function model(Model $model)
    {
        return $model->newQueryBuilder($this->connection)
            ->setModel($model)
            ->in($this->dn);
    }

    /**
     * Performs the specified query on the current LDAP connection.
     *
     * @param string $query
     *
     * @return Collection|array
     */
    public function query($query)
    {
        $start = microtime(true);

        // Here we will create the execution callback. This allows us
        // to only execute an LDAP request if caching is disabled
        // or if no cache of the given query exists yet.
        $callback = function () use ($query) {
            return $this->parse($this->run($query));
        };

        $results = $this->getCachedResponse($query, $callback);

        // Log the query.
        $this->logQuery($this, $this->type, $this->getElapsedTime($start));

        // Process & return the results.
        return $this->process($results);
    }

    /**
     * Paginates the current LDAP query.
     *
     * @param int  $pageSize
     * @param bool $isCritical
     *
     * @return Collection|array
     */
    public function paginate($pageSize = 1000, $isCritical = false)
    {
        $this->paginated = true;

        // Our limit must match our page size, otherwise we
        // will receive size limit exceeded errors.
        $this->limit = $pageSize;

        $start = microtime(true);

        $query = $this->getQuery();

        // Here we will create the pagination callback. This allows us
        // to only execute an LDAP request if caching is disabled
        // or if no cache of the given query exists yet.
        $callback = function () use ($query, $pageSize, $isCritical) {
            return $this->runPaginate($query, $pageSize, $isCritical);
        };

        $pages = $this->getCachedResponse($query, $callback);

        // Log the query.
        $this->logQuery($this, 'paginate', $this->getElapsedTime($start));

        // Process & return the results.
        return $this->process($pages);
    }

    /**
     * Processes and converts the given LDAP results into models.
     *
     * @param array $results
     *
     * @return array
     */
    protected function process(array $results)
    {
        unset($results['count']);

        return $this->paginated ? $this->flattenPages($results) : $results;
    }

    /**
     * Flattens LDAP paged results into a single array.
     *
     * @param array $pages
     *
     * @return array
     */
    protected function flattenPages(array $pages)
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
     *
     * @param string  $query
     * @param Closure $callback
     *
     * @return mixed
     */
    protected function getCachedResponse($query, Closure $callback)
    {
        // If caching is enabled and we have a cache instance available,
        // we will try to retrieve the cached results instead.
        if ($this->caching && $this->cache) {
            $key = $this->getCacheKey($query);

            if ($this->flushCache) {
                $this->cache->delete($key);
            }

            return $this->cache->remember($key, $this->cacheUntil, $callback);
        }

        // Otherwise, we will simply execute the callback.
        return $callback();
    }

    /**
     * Runs the query operation with the given filter.
     *
     * @param string $filter
     *
     * @return resource
     */
    protected function run($filter)
    {
        return $this->connection->run(function (Ldap $ldap) use ($filter) {
            // We will avoid setting the controls during any pagination
            // requests as it will clear the cookie we need to send
            // to the server upon retrieving every page.
            if (!$this->paginated) {
                // Before running the query, we will set the LDAP server controls. This
                // allows the controls to be automatically reset upon each new query
                // that is conducted on the same connection during each request.
                $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $this->controls);
            }

            return $ldap->{$this->type}(
                $this->getDn(),
                $filter,
                $this->getSelects(),
                $onlyAttributes = false,
                $this->limit
            );
        });
    }

    /**
     * Runs the paginate operation with the given filter.
     *
     * @param string $filter
     * @param int    $perPage
     * @param bool   $isCritical
     *
     * @return array
     */
    protected function runPaginate($filter, $perPage, $isCritical)
    {
        return $this->connection->run(function (Ldap $ldap) use ($filter, $perPage, $isCritical) {
            $callback = $ldap->supportsServerControlsInMethods() ?
                $this->compatiblePaginationCallback($filter, $perPage, $isCritical) :
                $this->deprecatedPaginationCallback($filter, $perPage, $isCritical);

            return $callback($ldap);
        });
    }

    /**
     * Create a deprecated pagination callback compatible with PHP 7.2.
     *
     * @param string $filter
     * @param int    $perPage
     * @param bool   $isCritical
     *
     * @return Closure
     */
    protected function deprecatedPaginationCallback($filter, $perPage, $isCritical)
    {
        return function (Ldap $ldap) use ($filter, $perPage, $isCritical) {
            $pages = [];

            $cookie = '';

            do {
                $ldap->controlPagedResult($perPage, $isCritical, $cookie);

                // Run the search.
                $resource = $this->run($filter);

                if ($resource) {
                    // If we have been given a valid resource, we will retrieve the next
                    // pagination cookie to send for our next pagination request.
                    $ldap->controlPagedResultResponse($resource, $cookie);

                    $pages[] = $this->parse($resource);
                }
            } while (!empty($cookie));

            // Reset paged result on the current connection. We won't pass in the current $perPage
            // parameter since we want to reset the page size to the default '1000'. Sending '0'
            // eliminates any further opportunity for running queries in the same request,
            // even though that is supposed to be the correct usage.
            $ldap->controlPagedResult();

            return $pages;
        };
    }

    /**
     * Create a compatible pagination callback compatible with PHP 7.3 and greater.
     *
     * @param string $filter
     * @param int    $perPage
     * @param bool   $isCritical
     *
     * @return Closure
     */
    protected function compatiblePaginationCallback($filter, $perPage, $isCritical)
    {
        return function (Ldap $ldap) use ($filter, $perPage, $isCritical) {
            $pages = [];

            // Add our paged results control.
            $this->addControl(LDAP_CONTROL_PAGEDRESULTS, $isCritical = false, [
                'size' => $perPage, 'cookie' => '',
            ]);

            do {
                // Update the server controls.
                $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $this->controls);

                // Run the search.
                $resource = $this->run($filter);

                if ($resource) {
                    $errorCode = $dn = $errorMessage = $refs = null;

                    // Update the server controls with the servers response.
                    $ldap->parseResult($resource, $errorCode, $dn, $errorMessage, $refs, $this->controls);

                    $pages[] = $this->parse($resource);

                    // Reset paged result on the current connection. We won't pass in the current $perPage
                    // parameter since we want to reset the page size to the default '1000'. Sending '0'
                    // eliminates any further opportunity for running queries in the same request,
                    // even though that is supposed to be the correct usage.
                    $this->controls[LDAP_CONTROL_PAGEDRESULTS]['value']['size'] = $perPage;
                }
            } while (!empty($this->controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie']));

            return $pages;
        };
    }

    /**
     * Parses the given LDAP resource by retrieving its entries.
     *
     * @param resource $resource
     *
     * @return array
     */
    protected function parse($resource)
    {
        if (!$resource) {
            return [];
        }

        return $this->connection->run(function (Ldap $ldap) use ($resource) {
            $entries = $ldap->getEntries($resource);

            // Free up memory.
            if (is_resource($resource)) {
                $ldap->freeResult($resource);
            }

            return $entries;
        });
    }

    /**
     * Returns the cache key.
     *
     * @param string $query
     *
     * @return string
     */
    protected function getCacheKey($query)
    {
        $host = $this->connection->getLdapConnection()->getHost();

        $key = $host
            .$this->type
            .$this->getDn()
            .$query
            .implode('', $this->getSelects())
            .$this->limit
            .$this->paginated;

        return md5($key);
    }

    /**
     * Returns the first entry in a search result.
     *
     * @param array|string $columns
     *
     * @return Model|null
     */
    public function first($columns = ['*'])
    {
        $results = $this->limit(1)->get($columns);

        return Arr::get($results, 0);
    }

    /**
     * Returns the first entry in a search result.
     *
     * If no entry is found, an exception is thrown.
     *
     * @param array|string $columns
     *
     * @throws ModelNotFoundException
     *
     * @return Model|static
     */
    public function firstOrFail($columns = ['*'])
    {
        $record = $this->first($columns);

        if (!$record) {
            throw ModelNotFoundException::forQuery($this->getUnescapedQuery(), $this->dn);
        }

        return $record;
    }

    /**
     * Finds a record by the specified attribute and value.
     *
     * @param string       $attribute
     * @param string       $value
     * @param array|string $columns
     *
     * @return Model|static|null
     */
    public function findBy($attribute, $value, $columns = ['*'])
    {
        try {
            return $this->findByOrFail($attribute, $value, $columns);
        } catch (ModelNotFoundException $e) {
            return;
        }
    }

    /**
     * Finds a record by the specified attribute and value.
     *
     * If no record is found an exception is thrown.
     *
     * @param string       $attribute
     * @param string       $value
     * @param array|string $columns
     *
     * @throws ModelNotFoundException
     *
     * @return Model
     */
    public function findByOrFail($attribute, $value, $columns = ['*'])
    {
        return $this->whereEquals($attribute, $value)->firstOrFail($columns);
    }

    /**
     * Finds many records by the specified attribute.
     *
     * @param string $attribute
     * @param array  $values
     * @param array  $columns
     *
     * @return Collection
     */
    public function findManyBy($attribute, array $values = [], $columns = ['*'])
    {
        $query = $this->select($columns);

        foreach ($values as $value) {
            $query->orWhere([$attribute => $value]);
        }

        return $query->get();
    }

    /**
     * Finds a record by its distinguished name.
     *
     * @param string       $dn
     * @param array|string $columns
     *
     * @return Model|static|null
     */
    public function find($dn, $columns = ['*'])
    {
        try {
            return $this->findOrFail($dn, $columns);
        } catch (ModelNotFoundException $e) {
            return;
        }
    }

    /**
     * Finds a record by its distinguished name.
     *
     * Fails upon no records returned.
     *
     * @param string       $dn
     * @param array|string $columns
     *
     * @throws ModelNotFoundException
     *
     * @return Model|static
     */
    public function findOrFail($dn, $columns = ['*'])
    {
        return $this->setDn($dn)
            ->read()
            ->whereHas('objectclass')
            ->firstOrFail($columns);
    }

    /**
     * Adds the inserted fields to query on the current LDAP connection.
     *
     * @param array|string $columns
     *
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        if (!empty($columns)) {
            $this->columns = $columns;
        }

        return $this;
    }

    /**
     * Add a new select column to the query.
     *
     * @param array|mixed $column
     *
     * @return $this
     */
    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }

    /**
     * Adds a raw filter to the current query.
     *
     * @param array|string $filters
     *
     * @return $this
     */
    public function rawFilter($filters = [])
    {
        $filters = is_array($filters) ? $filters : func_get_args();

        foreach ($filters as $filter) {
            $this->filters['raw'][] = $filter;
        }

        return $this;
    }

    /**
     * Adds a nested 'and' filter to the current query.
     *
     * @param Closure $closure
     *
     * @return $this
     */
    public function andFilter(Closure $closure)
    {
        $query = $this->newNestedInstance($closure);

        return $this->rawFilter(
            $this->grammar->compileAnd($query->getQuery())
        );
    }

    /**
     * Adds a nested 'or' filter to the current query.
     *
     * @param Closure $closure
     *
     * @return $this
     */
    public function orFilter(Closure $closure)
    {
        $query = $this->newNestedInstance($closure);

        return $this->rawFilter(
            $this->grammar->compileOr($query->getQuery())
        );
    }

    /**
     * Adds a nested 'not' filter to the current query.
     *
     * @param Closure $closure
     *
     * @return $this
     */
    public function notFilter(Closure $closure)
    {
        $query = $this->newNestedInstance($closure);

        return $this->rawFilter(
            $this->grammar->compileNot($query->getQuery())
        );
    }

    /**
     * Adds a where clause to the current query.
     *
     * @param string|array $field
     * @param string       $operator
     * @param string       $value
     * @param string       $boolean
     * @param bool         $raw
     *
     * @throws InvalidArgumentException
     *
     * @return $this
     */
    public function where($field, $operator = null, $value = null, $boolean = 'and', $raw = false)
    {
        if (is_array($field)) {
            // If the field is an array, we will assume it is an array of
            // key-value pairs and can add them each as a where clause.
            return $this->addArrayOfWheres($field, $boolean, $raw);
        }

        // We'll bypass the 'has' and 'notHas' operator since they
        // only require two arguments inside the where method.
        $bypass = ['*', '!*'];

        // Here we will make some assumptions about the operator. If only
        // 2 values are passed to the method, we will assume that
        // the operator is 'equals' and keep going.
        if (func_num_args() === 2 && in_array($operator, $bypass) === false) {
            list($value, $operator) = [$operator, '='];
        }

        if (!in_array($operator, $this->grammar->getOperators())) {
            throw new InvalidArgumentException("Invalid LDAP filter operator [$operator]");
        }

        // We'll escape the value if raw isn't requested.
        $value = $this->prepareWhereValue($field, $value, $raw);

        $field = $this->escape($field, $ignore = null, 3);

        $this->addFilter($boolean, compact('field', 'operator', 'value'));

        return $this;
    }

    /**
     * Prepare the value for being queried.
     *
     * @param string $field
     * @param string $value
     * @param bool   $raw
     *
     * @return string
     */
    protected function prepareWhereValue($field, $value, $raw = false)
    {
        return $raw ? $value : $this->escape($value);
    }

    /**
     * Adds a raw where clause to the current query.
     *
     * Values given to this method are not escaped.
     *
     * @param string|array $field
     * @param string       $operator
     * @param string       $value
     *
     * @return $this
     */
    public function whereRaw($field, $operator = null, $value = null)
    {
        return $this->where($field, $operator, $value, 'and', true);
    }

    /**
     * Adds a 'where equals' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereEquals($field, $value)
    {
        return $this->where($field, '=', $value);
    }

    /**
     * Adds a 'where not equals' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereNotEquals($field, $value)
    {
        return $this->where($field, '!', $value);
    }

    /**
     * Adds a 'where approximately equals' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereApproximatelyEquals($field, $value)
    {
        return $this->where($field, '~=', $value);
    }

    /**
     * Adds a 'where has' clause to the current query.
     *
     * @param string $field
     *
     * @return $this
     */
    public function whereHas($field)
    {
        return $this->where($field, '*');
    }

    /**
     * Adds a 'where not has' clause to the current query.
     *
     * @param string $field
     *
     * @return $this
     */
    public function whereNotHas($field)
    {
        return $this->where($field, '!*');
    }

    /**
     * Adds a 'where contains' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereContains($field, $value)
    {
        return $this->where($field, 'contains', $value);
    }

    /**
     * Adds a 'where contains' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereNotContains($field, $value)
    {
        return $this->where($field, 'not_contains', $value);
    }

    /**
     * Query for entries that match any of the values provided for the given field.
     *
     * @param string $field
     * @param array  $values
     *
     * @return $this
     */
    public function whereIn($field, array $values)
    {
        return $this->orFilter(function (self $query) use ($field, $values) {
            foreach ($values as $value) {
                $query->whereEquals($field, $value);
            }
        });
    }

    /**
     * Adds a 'between' clause to the current query.
     *
     * @param string $field
     * @param array  $values
     *
     * @return $this
     */
    public function whereBetween($field, array $values)
    {
        return $this->where([
            [$field, '>=', $values[0]],
            [$field, '<=', $values[1]],
        ]);
    }

    /**
     * Adds a 'where starts with' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereStartsWith($field, $value)
    {
        return $this->where($field, 'starts_with', $value);
    }

    /**
     * Adds a 'where *not* starts with' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereNotStartsWith($field, $value)
    {
        return $this->where($field, 'not_starts_with', $value);
    }

    /**
     * Adds a 'where ends with' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereEndsWith($field, $value)
    {
        return $this->where($field, 'ends_with', $value);
    }

    /**
     * Adds a 'where *not* ends with' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function whereNotEndsWith($field, $value)
    {
        return $this->where($field, 'not_ends_with', $value);
    }

    /**
     * Only include deleted models in the results.
     *
     * @return $this
     */
    public function whereDeleted()
    {
        return $this->withDeleted()->whereEquals('isDeleted', 'TRUE');
    }

    /**
     * Set the LDAP control option to include deleted LDAP models.
     *
     * @return $this
     */
    public function withDeleted()
    {
        return $this->addControl(Ldap::OID_SERVER_SHOW_DELETED, $isCritical = true);
    }

    /**
     * Add a server control to the query.
     *
     * @param string $oid
     * @param bool   $isCritical
     * @param mixed  $value
     *
     * @return $this
     */
    public function addControl($oid, $isCritical = false, $value = null)
    {
        $this->controls[$oid] = compact('oid', 'isCritical', 'value');

        return $this;
    }

    /**
     * Determine if the server control exists on the query.
     *
     * @param string $oid
     *
     * @return bool
     */
    public function hasControl($oid)
    {
        return array_key_exists($oid, $this->controls);
    }

    /**
     * Adds an 'or where' clause to the current query.
     *
     * @param array|string $field
     * @param string|null  $operator
     * @param string|null  $value
     *
     * @return $this
     */
    public function orWhere($field, $operator = null, $value = null)
    {
        return $this->where($field, $operator, $value, 'or');
    }

    /**
     * Adds a raw or where clause to the current query.
     *
     * Values given to this method are not escaped.
     *
     * @param string $field
     * @param string $operator
     * @param string $value
     *
     * @return $this
     */
    public function orWhereRaw($field, $operator = null, $value = null)
    {
        return $this->where($field, $operator, $value, 'or', true);
    }

    /**
     * Adds an 'or where has' clause to the current query.
     *
     * @param string $field
     *
     * @return $this
     */
    public function orWhereHas($field)
    {
        return $this->orWhere($field, '*');
    }

    /**
     * Adds a 'where not has' clause to the current query.
     *
     * @param string $field
     *
     * @return $this
     */
    public function orWhereNotHas($field)
    {
        return $this->orWhere($field, '!*');
    }

    /**
     * Adds an 'or where equals' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereEquals($field, $value)
    {
        return $this->orWhere($field, '=', $value);
    }

    /**
     * Adds an 'or where not equals' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereNotEquals($field, $value)
    {
        return $this->orWhere($field, '!', $value);
    }

    /**
     * Adds a 'or where approximately equals' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereApproximatelyEquals($field, $value)
    {
        return $this->orWhere($field, '~=', $value);
    }

    /**
     * Adds an 'or where contains' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereContains($field, $value)
    {
        return $this->orWhere($field, 'contains', $value);
    }

    /**
     * Adds an 'or where *not* contains' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereNotContains($field, $value)
    {
        return $this->orWhere($field, 'not_contains', $value);
    }

    /**
     * Adds an 'or where starts with' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereStartsWith($field, $value)
    {
        return $this->orWhere($field, 'starts_with', $value);
    }

    /**
     * Adds an 'or where *not* starts with' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereNotStartsWith($field, $value)
    {
        return $this->orWhere($field, 'not_starts_with', $value);
    }

    /**
     * Adds an 'or where ends with' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereEndsWith($field, $value)
    {
        return $this->orWhere($field, 'ends_with', $value);
    }

    /**
     * Adds an 'or where *not* ends with' clause to the current query.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    public function orWhereNotEndsWith($field, $value)
    {
        return $this->orWhere($field, 'not_ends_with', $value);
    }

    /**
     * Adds a filter onto the current query.
     *
     * @param string $type     The type of filter to add.
     * @param array  $bindings The bindings of the filter.
     *
     * @throws InvalidArgumentException
     *
     * @return $this
     */
    public function addFilter($type, array $bindings)
    {
        // Here we will ensure we have been given a proper filter type.
        if (!array_key_exists($type, $this->filters)) {
            throw new InvalidArgumentException("Invalid filter type: {$type}.");
        }

        // The required filter key bindings.
        $required = ['field', 'operator', 'value'];

        // Here we will ensure the proper key bindings are given.
        if (count(array_intersect_key(array_flip($required), $bindings)) !== count($required)) {
            // Retrieve the keys that are missing in the bindings array.
            $missing = implode(', ', array_diff($required, array_flip($bindings)));

            throw new InvalidArgumentException("Invalid filter bindings. Missing: [{$missing}] keys.");
        }

        $this->filters[$type][] = $bindings;

        return $this;
    }

    /**
     * Get all the filters on the query.
     *
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * Clear the query filters.
     *
     * @return $this
     */
    public function clearFilters()
    {
        foreach ($this->filters as $type => $filters) {
            $this->filters[$type] = [];
        }

        return $this;
    }

    /**
     * Determine if the query has attributes selected.
     *
     * @return bool
     */
    public function hasSelects()
    {
        return count($this->columns) > 0;
    }

    /**
     * Get the attributes to select on the search.
     *
     * @return array
     */
    public function getSelects()
    {
        $selects = $this->columns ?? [];

        // If the * character is not provided in the selected columns,
        // we need to ensure we always select the object class, as
        // this is used for constructing models properly.
        if (
            !in_array('*', $selects) &&
            !in_array('objectclass', $selects)
        ) {
            $selects[] = 'objectclass';
        }

        return $selects;
    }

    /**
     * Set the query to search on the base distinguished name.
     *
     * This will result in one record being returned.
     *
     * @return $this
     */
    public function read()
    {
        $this->type = 'read';

        return $this;
    }

    /**
     * Set the query to search one level on the base distinguished name.
     *
     * @return $this
     */
    public function listing()
    {
        $this->type = 'listing';

        return $this;
    }

    /**
     * Sets the query to search the entire directory on the base distinguished name.
     *
     * @return $this
     */
    public function recursive()
    {
        $this->type = 'search';

        return $this;
    }

    /**
     * Whether the current query is nested.
     *
     * @param bool $nested
     *
     * @return $this
     */
    public function nested($nested = true)
    {
        $this->nested = (bool) $nested;

        return $this;
    }

    /**
     * Enables caching on the current query until the given date.
     *
     * If flushing is enabled, the query cache will be flushed and then re-cached.
     *
     * @param DateInterval $until When to expire the query cache.
     * @param bool              $flush Whether to force-flush the query cache.
     *
     * @return $this
     */
    public function cache(DateInterval $until = null, $flush = false)
    {
        $this->caching = true;
        $this->cacheUntil = $until;
        $this->flushCache = $flush;

        return $this;
    }

    /**
     * Returns an escaped string for use in an LDAP filter.
     *
     * @param string $value
     * @param string $ignore
     * @param int    $flags
     *
     * @return string
     */
    public function escape($value, $ignore = '', $flags = 0)
    {
        return ldap_escape($value, $ignore, $flags);
    }

    /**
     * Returns true / false if the current query is nested.
     *
     * @return bool
     */
    public function isNested()
    {
        return $this->nested === true;
    }

    /**
     * Returns bool that determines whether the current
     * query builder will return paginated results.
     *
     * @return bool
     */
    public function isPaginated()
    {
        return $this->paginated;
    }

    /**
     * Insert the entry in the directory.
     *
     * @param string $dn
     * @param array  $attributes
     *
     * @throws LdapRecordException
     *
     * @return bool
     */
    public function insert($dn, array $attributes)
    {
        if (empty($dn)) {
            throw new LdapRecordException('A new LDAP object must have a distinguished name (dn).');
        }

        if (!array_key_exists('objectclass', $attributes)) {
            throw new LdapRecordException(
                'A new LDAP object must contain at least one object class (objectclass) to be created.'
            );
        }

        return $this->connection->run(function (Ldap $ldap) use ($dn, $attributes) {
            return $ldap->add($dn, $attributes);
        });
    }

    /**
     * Create attributes on the entry in the directory.
     *
     * @param string $dn
     * @param array  $attributes
     *
     * @return bool
     */
    public function insertAttributes($dn, array $attributes)
    {
        return $this->connection->run(function (Ldap $ldap) use ($dn, $attributes) {
            return $ldap->modAdd($dn, $attributes);
        });
    }

    /**
     * Update the entry with the given modifications.
     *
     * @param string $dn
     * @param array  $modifications
     *
     * @return bool
     */
    public function update($dn, array $modifications)
    {
        return $this->connection->run(function (Ldap $ldap) use ($dn, $modifications) {
            return $ldap->modifyBatch($dn, $modifications);
        });
    }

    /**
     * Update an entries attribute in the directory.
     *
     * @param string $dn
     * @param array  $attributes
     *
     * @return bool
     */
    public function updateAttributes($dn, array $attributes)
    {
        return $this->connection->run(function (Ldap $ldap) use ($dn, $attributes) {
            return $ldap->modReplace($dn, $attributes);
        });
    }

    /**
     * Delete an entry from the directory.
     *
     * @param string $dn
     *
     * @return bool
     */
    public function delete($dn)
    {
        return $this->connection->run(function (Ldap $ldap) use ($dn) {
            return $ldap->delete($dn);
        });
    }

    /**
     * Delete attributes on the entry in the directory.
     *
     * @param string $dn
     * @param array  $attributes
     *
     * @return bool
     */
    public function deleteAttributes($dn, array $attributes)
    {
        return $this->connection->run(function (Ldap $ldap) use ($dn, $attributes) {
            return $ldap->modDelete($dn, $attributes);
        });
    }

    /**
     * Rename an entry in the directory.
     *
     * @param string $dn
     * @param string $rdn
     * @param string $newParentDn
     * @param bool   $deleteOldRdn
     *
     * @return bool
     */
    public function rename($dn, $rdn, $newParentDn, $deleteOldRdn = true)
    {
        return $this->connection->run(function (Ldap $ldap) use ($dn, $rdn, $newParentDn, $deleteOldRdn) {
            return $ldap->rename($dn, $rdn, $newParentDn, $deleteOldRdn);
        });
    }

    /**
     * Handle dynamic method calls on the query builder.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @throws BadMethodCallException
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        // We will check if the beginning of the method being called contains
        // 'where'. If so, we'll assume it's a dynamic 'where' clause.
        if (substr($method, 0, 5) === 'where') {
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
     * @param string $method
     * @param array  $parameters
     *
     * @return $this
     */
    public function dynamicWhere($method, $parameters)
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
     * Adds an array of wheres to the current query.
     *
     * @param array  $wheres
     * @param string $boolean
     * @param bool   $raw
     *
     * @return $this
     */
    protected function addArrayOfWheres($wheres, $boolean, $raw)
    {
        foreach ($wheres as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                // If the key is numeric and the value is an array, we'll
                // assume we've been given an array with conditionals.
                list($field, $condition) = $value;

                // Since a value is optional for some conditionals, we will
                // try and retrieve the third parameter from the array,
                // but is entirely optional.
                $value = Arr::get($value, 2);

                $this->where($field, $condition, $value, $boolean);
            } else {
                // If the value is not an array, we will assume an equals clause.
                $this->where($key, '=', $value, $boolean, $raw);
            }
        }

        return $this;
    }

    /**
     * Add a single dynamic where clause statement to the query.
     *
     * @param string $segment
     * @param string $connector
     * @param array  $parameters
     * @param int    $index
     *
     * @return void
     */
    protected function addDynamic($segment, $connector, $parameters, $index)
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
     *
     * @param Builder    $query
     * @param string     $type
     * @param null|float $time
     */
    protected function logQuery($query, $type, $time = null)
    {
        $args = [$query, $time];

        switch ($type) {
            case 'listing':
                $event = new Events\Listing(...$args);
                break;
            case 'read':
                $event = new Events\Read(...$args);
                break;
            case 'paginate':
                $event = new Events\Paginate(...$args);
                break;
            default:
                $event = new Events\Search(...$args);
                break;
        }

        $this->fireQueryEvent($event);
    }

    /**
     * Fires the given query event.
     *
     * @param QueryExecuted $event
     *
     * @return void
     */
    protected function fireQueryEvent(QueryExecuted $event)
    {
        Container::getEventDispatcher()->fire($event);
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param int $start
     *
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }
}
