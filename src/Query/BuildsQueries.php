<?php

namespace LdapRecord\Query;

use Closure;
use LdapRecord\Support\Arr;

trait BuildsQueries
{
    /**
     * Execute a callback over each item while chunking.
     */
    public function each(Closure $callback, int $pageSize = 1000, bool $isCritical = false, bool $isolate = false): bool
    {
        return $this->chunk($pageSize, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        }, $isCritical, $isolate);
    }

    /**
     * Get the first entry in a search result.
     */
    public function first(array|string $selects = ['*']): ?array
    {
        return Arr::first(
            $this->limit(1)->get($selects)
        );
    }

    /**
     * Get the first entry in a result, or execute the callback.
     */
    public function firstOr(Closure $callback, array|string $selects = ['*']): mixed
    {
        return $this->first($selects) ?: $callback();
    }

    /**
     * Get the first entry in a search result.
     *
     * If no entry is found, an exception is thrown.
     *
     * @throws ObjectNotFoundException
     */
    public function firstOrFail(array|string $selects = ['*']): array
    {
        if (! $record = $this->first($selects)) {
            $this->throwNotFoundException($this->getUnescapedQuery(), $this->dn);
        }

        return $record;
    }

    /**
     * Execute the query and get the first result if it's the sole matching record.
     *
     * @throws ObjectsNotFoundException
     * @throws MultipleObjectsFoundException
     */
    public function sole(array|string $selects = ['*']): array
    {
        $result = $this->limit(2)->get($selects);

        if (empty($result)) {
            throw new ObjectsNotFoundException;
        }

        if (count($result) > 1) {
            throw new MultipleObjectsFoundException;
        }

        return reset($result);
    }

    /**
     * Determine if any results exist for the current query.
     */
    public function exists(): bool
    {
        return ! is_null($this->first());
    }

    /**
     * Determine if no results exist for the current query.
     */
    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Execute the given callback if no rows exist for the current query.
     */
    public function existsOr(Closure $callback): mixed
    {
        return $this->exists() ? true : $callback();
    }

    /**
     * Throw a not found exception.
     *
     * @throws ObjectNotFoundException
     */
    abstract protected function throwNotFoundException(string $query, ?string $dn = null): void;

    /**
     * Add an array of where clauses to the query.
     */
    protected function addArrayOfWheres(array $wheres, string $boolean, bool $raw): static
    {
        foreach ($wheres as $key => $value) {
            if (is_numeric($key) && is_array($value)) {
                $this->where(...array_values($value), boolean: $boolean, raw: $raw);
            } else {
                $this->where($key, '=', $value, $boolean, $raw);
            }
        }

        return $this;
    }
}
