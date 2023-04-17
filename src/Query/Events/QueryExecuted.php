<?php

namespace LdapRecord\Query\Events;

use LdapRecord\Query\Builder;

class QueryExecuted
{
    /**
     * The LDAP filter that was used for the query.
     */
    protected Builder $query;

    /**
     * The number of milliseconds it took to execute the query.
     *
     * @var ?float
     */
    protected ?float $time;

    /**
     * Constructor.
     */
    public function __construct(Builder $query, float $time = null)
    {
        $this->query = $query;
        $this->time = $time;
    }

    /**
     * Returns the LDAP filter that was used for the query.
     */
    public function getQuery(): Builder
    {
        return $this->query;
    }

    /**
     * Returns the number of milliseconds it took to execute the query.
     */
    public function getTime(): ?float
    {
        return $this->time;
    }
}
