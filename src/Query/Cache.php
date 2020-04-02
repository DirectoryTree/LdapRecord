<?php

namespace LdapRecord\Query;

use Closure;
use DateInterval;
use Carbon\Carbon;
use DateTimeInterface;
use Psr\SimpleCache\CacheInterface;

class Cache
{
    /**
     * The cache driver.
     *
     * @var CacheInterface
     */
    protected $store;

    /**
     * Constructor.
     *
     * @param CacheInterface $store
     */
    public function __construct(CacheInterface $store)
    {
        $this->store = $store;
    }

    /**
     * Get an item from the cache.
     *
     * @param string $key
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return mixed
     */
    public function get($key)
    {
        return $this->store->get($key);
    }

    /**
     * Store an item in the cache.
     *
     * @param string                                  $key
     * @param mixed                                   $value
     * @param DateTimeInterface|DateInterval|int|null $ttl
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return bool
     */
    public function put($key, $value, $ttl = null)
    {
        return $this->store->set($key, $value, $this->getSeconds($ttl));
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result.
     *
     * @param string                                  $key
     * @param DateTimeInterface|DateInterval|int|null $ttl
     * @param Closure                                 $callback
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return mixed
     */
    public function remember($key, $ttl, Closure $callback)
    {
        $value = $this->get($key);

        if (!is_null($value)) {
            return $value;
        }

        $this->put($key, $value = $callback(), $this->getSeconds($ttl));

        return $value;
    }

    /**
     * Delete an item from the cache.
     *
     * @param string $key
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *
     * @return bool
     */
    public function delete($key)
    {
        return $this->store->delete($key);
    }

    /**
     * Calculate the number of seconds for the given TTL.
     *
     * @author Taylor Otwell
     *
     * @param DateTimeInterface|DateInterval|int $ttl
     *
     * @return int
     */
    protected function getSeconds($ttl)
    {
        $duration = $this->parseDateInterval($ttl);

        if ($duration instanceof DateTimeInterface) {
            $duration = Carbon::now()->diffInRealSeconds($duration, false);
        }

        return (int) $duration > 0 ? $duration : 0;
    }

    /**
     * If the given value is an interval, convert it to a DateTime instance.
     *
     * @author Taylor Otwell
     *
     * @param DateTimeInterface|DateInterval|int $delay
     *
     * @return DateTimeInterface|int
     */
    protected function parseDateInterval($delay)
    {
        if ($delay instanceof DateInterval) {
            $delay = Carbon::now()->add($delay);
        }

        return $delay;
    }
}
