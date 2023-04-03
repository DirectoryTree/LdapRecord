<?php

namespace LdapRecord;

/** @mixin ConnectionManager */
class Container
{
    /**
     * The current container instance.
     */
    protected static Container $instance;

    /**
     * The connection manager instance.
     */
    protected ConnectionManager $manager;

    /**
     * Get or set the current instance of the container.
     */
    public static function getInstance(): static
    {
        return static::$instance ?? static::getNewInstance();
    }

    /**
     * Set the container instance.
     */
    public static function setInstance(self $container = null): ?static
    {
        return static::$instance = $container;
    }

    /**
     * Set and get a new instance of the container.
     */
    public static function getNewInstance(): static
    {
        return static::setInstance(new static());
    }

    /**
     * Forward missing static calls onto the current instance.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::getInstance()->{$method}(...$parameters);
    }

    /**
     * Constructor.
     */
    public function __construct(ConnectionManager $manager = new ConnectionManager())
    {
        $this->manager = $manager;
    }

    /**
     * Forward missing method calls onto the connection manager.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->manager()->{$method}(...$parameters);
    }

    /**
     * Get the connection manager.
     */
    public function manager(): ConnectionManager
    {
        return $this->manager;
    }
}
