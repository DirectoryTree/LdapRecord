<?php

namespace LdapRecord;

use LdapRecord\Log\HasLogger;
use LdapRecord\Log\EventLogger;
use LdapRecord\Events\DispatchesEvents;

class Container
{
    use HasLogger;
    use DispatchesEvents;

    /**
     * The container instance.
     *
     * @var Container
     */
    protected static $instance;

    /**
     * The LDAP connections.
     *
     * @var Connection[]
     */
    protected $connections = [];

    /**
     * The name of the default LDAP connection.
     *
     * @var string
     */
    protected $default = 'default';

    /**
     * Get or set the current instance of the container.
     *
     * @return Container
     */
    public static function getInstance()
    {
        return static::$instance ?? static::getNewInstance();
    }

    /**
     * Set the container instance.
     *
     * @param Container|null $container
     *
     * @return Container|null
     */
    public static function setInstance(Container $container = null)
    {
        return static::$instance = $container;
    }

    /**
     * Set and get a new instance of the container.
     *
     * @return Container
     */
    public static function getNewInstance()
    {
        return static::setInstance(new static);
    }

    /**
     * Add a connection to the container.
     *
     * @param Connection  $connection
     * @param string|null $name
     *
     * @return static
     */
    public static function addConnection(Connection $connection, $name = null)
    {
        return static::getInstance()->add($connection, $name);
    }

    /**
     * Remove a connection from the container.
     *
     * @param string $name
     *
     * @return void
     */
    public static function removeConnection($name)
    {
        static::getInstance()->remove($name);
    }

    /**
     * Get a connection by name or return the default.
     *
     * @param string|null $name
     *
     * @throws ContainerException If the given connection does not exist.
     *
     * @return Connection
     */
    public static function getConnection($name = null)
    {
        return static::getInstance()->get($name);
    }

    /**
     * Set the default connection name.
     *
     * @param string|null $name
     *
     * @return static
     */
    public static function setDefaultConnection($name = null)
    {
        return static::getInstance()->setDefault($name);
    }

    /**
     * Get the default connection.
     *
     * @return Connection
     */
    public static function getDefaultConnection()
    {
        return static::getInstance()->getDefault();
    }

    /**
     * Reset the container.
     *
     * @return void
     */
    public static function reset()
    {
        static::getInstance()->flush();
    }

    /**
     * Returns a new event logger instance.
     *
     * @return EventLogger
     */
    protected function newEventLogger()
    {
        return new EventLogger($this->logger);
    }

    /**
     * Add a new connection into the container.
     *
     * @param Connection $connection
     * @param string     $name
     *
     * @return $this
     */
    public function add(Connection $connection, $name = null)
    {
        $this->connections[$name ?? $this->default] = $connection;

        return $this;
    }

    /**
     * Remove a connection from the container.
     *
     * @param $name
     *
     * @return $this
     */
    public function remove($name)
    {
        if ($this->exists($name)) {
            unset($this->connections[$name]);
        }

        return $this;
    }

    /**
     * Return all of the connections from the container.
     *
     * @return Connection[]
     */
    public function all()
    {
        return $this->connections;
    }

    /**
     * Get a connection by name or return the default.
     *
     * @param string|null $name
     *
     * @throws ContainerException If the given connection does not exist.
     *
     * @return Connection
     */
    public function get($name = null)
    {
        if ($this->exists($name = $name ?? $this->default)) {
            return $this->connections[$name];
        }

        throw new ContainerException("The LDAP connection [$name] does not exist.");
    }

    /**
     * Return the default connection.
     *
     * @return Connection
     */
    public function getDefault()
    {
        return $this->get($this->default);
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getDefaultConnectionName()
    {
        return $this->default;
    }

    /**
     * Checks if the connection exists.
     *
     * @param $name
     *
     * @return bool
     */
    public function exists($name)
    {
        return array_key_exists($name, $this->connections);
    }

    /**
     * Set the default connection name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setDefault($name = null)
    {
        $this->default = $name;

        return $this;
    }

    /**
     * Flush the container of all instances and connections.
     *
     * @return void
     */
    public function flush()
    {
        $this->connections = [];
        $this->dispatcher = null;
        $this->logger = null;
    }
}
