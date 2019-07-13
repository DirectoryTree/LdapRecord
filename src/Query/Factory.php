<?php

namespace LdapRecord\Query;

use LdapRecord\Schemas\ActiveDirectory;
use LdapRecord\Schemas\SchemaInterface;
use LdapRecord\Connections\LdapInterface;

/**
 * LdapRecord Search Factory.
 *
 * Constructs new LDAP queries.
 *
 * @mixin Builder
 */
class Factory
{
    /**
     * @var LdapInterface
     */
    protected $connection;

    /**
     * Stores the current schema instance.
     *
     * @var SchemaInterface
     */
    protected $schema;

    /**
     * The base DN to use for the search.
     *
     * @var string|null
     */
    protected $base;

    /**
     * The query cache.
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Constructor.
     *
     * @param LdapInterface  $connection The connection to use when constructing a new query.
     * @param SchemaInterface|null $schema     The schema to use for the query and models located.
     * @param string               $baseDn     The base DN to use for all searches.
     */
    public function __construct(LdapInterface $connection, SchemaInterface $schema = null, $baseDn = '')
    {
        $this->setConnection($connection)
            ->setSchema($schema)
            ->setBaseDn($baseDn);
    }

    /**
     * Sets the connection property.
     *
     * @param LdapInterface $connection
     *
     * @return $this
     */
    public function setConnection(LdapInterface $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Sets the schema property.
     *
     * @param SchemaInterface|null $schema
     *
     * @return $this
     */
    public function setSchema(SchemaInterface $schema = null)
    {
        $this->schema = $schema ?: new ActiveDirectory();

        return $this;
    }

    /**
     * Sets the base distinguished name to perform searches upon.
     *
     * @param string $base
     *
     * @return $this
     */
    public function setBaseDn($base = '')
    {
        $this->base = $base;

        return $this;
    }

    /**
     * Sets the cache for storing query results.
     *
     * @param Cache $cache
     *
     * @return $this
     */
    public function setCache(Cache $cache)
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Returns a new query builder instance.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return $this->newBuilder()->in($this->base);
    }

    /**
     * Performs a global 'all' search query on the current
     * connection by performing a search for all entries
     * that contain a common name attribute.
     *
     * @return \LdapRecord\Query\Collection|array
     */
    public function get()
    {
        return $this->newQuery()->whereHas($this->schema->commonName())->get();
    }

    /**
     * Handle dynamic method calls on the query builder object.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->newQuery(), $method], $parameters);
    }

    /**
     * Returns a new query builder instance.
     *
     * @return Builder
     */
    protected function newBuilder()
    {
        $builder = new Builder($this->connection, $this->newGrammar(), $this->schema);

        $builder->setCache($this->cache);

        return $builder;
    }

    /**
     * Returns a new query grammar instance.
     *
     * @return Grammar
     */
    protected function newGrammar()
    {
        return new Grammar();
    }
}
