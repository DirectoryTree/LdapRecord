<?php

namespace LdapRecord;

use Closure;
use Throwable;
use LdapRecord\Auth\Guard;
use LdapRecord\Query\Cache;
use LdapRecord\Query\Builder;
use Psr\SimpleCache\CacheInterface;
use LdapRecord\Configuration\DomainConfiguration;

class Connection implements ConnectionInterface
{
    /**
     * @var LdapInterface
     */
    protected $ldap;

    /**
     * @var DomainConfiguration
     */
    protected $configuration;

    /**
     * @var Cache|null
     */
    protected $cache;

    /**
     * Constructor.
     *
     * @param array              $config
     * @param LdapInterface|null $ldap
     */
    public function __construct($config = [], LdapInterface $ldap = null)
    {
        $this->configuration = new DomainConfiguration($config);

        $this->setLdapConnection($ldap ?? new Ldap());
    }

    /**
     * Disconnect the LDAP connection.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * {@inheritdoc}
     */
    public function setConfiguration($config = [])
    {
        $this->configuration = new DomainConfiguration($config);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setLdapConnection(LdapInterface $ldap)
    {
        $this->ldap = $ldap;

        $this->initialize();

        return $this;
    }

    /**
     * Initializes the LDAP connection.
     *
     * @return void
     */
    public function initialize()
    {
        $this->ldap->connect(
            $this->configuration->get('hosts'),
            $this->configuration->get('port')
        );

        $this->configure();
    }

    /**
     * Configure the LDAP connection.
     *
     * @return void
     */
    protected function configure()
    {
        if ($this->configuration->get('use_ssl')) {
            $this->ldap->ssl();
        } elseif ($this->configuration->get('use_tls')) {
            $this->ldap->tls();
        }

        $options = array_replace(
            $this->configuration->get('options'),
            [
                LDAP_OPT_PROTOCOL_VERSION => $this->configuration->get('version'),
                LDAP_OPT_NETWORK_TIMEOUT  => $this->configuration->get('timeout'),
                LDAP_OPT_REFERRALS        => $this->configuration->get('follow_referrals'),
            ]
        );

        $this->ldap->setOptions($options);
    }

    /**
     * {@inheritdoc}
     */
    public function setCache(CacheInterface $store)
    {
        $this->cache = new Cache($store);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getLdapConnection()
    {
        return $this->ldap;
    }

    /**
     * {@inheritdoc}
     */
    public function connect($username = null, $password = null)
    {
        if (is_null($username) && is_null($password)) {
            $this->auth()->bindAsConfiguredUser();
        } else {
            $this->auth()->bind($username, $password);
        }

        return $this;
    }

    /**
     * Reconnect to the LDAP server.
     *
     * @throws Auth\BindException
     * @throws ConnectionException
     */
    public function reconnect()
    {
        $this->disconnect();

        $this->initialize();

        $this->connect();
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $this->ldap->close();
    }

    /**
     * {@inheritdoc}
     */
    public function run(Closure $operation)
    {
        try {
            return $this->runOperationCallback($operation);
        } catch (LdapRecordException $e) {
            return $this->tryAgainIfCausedByLostConnection($e, $operation);
        }
    }

    /**
     * Run the operation callback on the current LDAP connection.
     *
     * @param Closure $operation
     *
     * @throws LdapRecordException
     *
     * @return mixed
     */
    protected function runOperationCallback(Closure $operation)
    {
        try {
            return $operation($this->ldap);
        } catch (Throwable $e) {
            throw new LdapRecordException($e, $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function auth()
    {
        $guard = new Guard($this->ldap, $this->configuration);

        $guard->setDispatcher(Container::getEventDispatcher());

        return $guard;
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        return (new Builder($this))->in($this->configuration->get('base_dn'));
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return $this->ldap->isBound();
    }

    /**
     * Attempt to retry an LDAP operation if due to a lost connection.
     *
     * @param LdapRecordException $e
     * @param Closure             $callback
     *
     * @throws LdapRecordException
     *
     * @return mixed
     */
    protected function tryAgainIfCausedByLostConnection(LdapRecordException $e, Closure $callback)
    {
        if ($this->causedByLostConnection($e)) {
            $this->reconnect();

            return $this->runOperationCallback($callback);
        }

        throw $e;
    }

    /**
     * Determine if the given exception was caused by a lost connection.
     *
     * @param LdapRecordException $e
     *
     * @return bool
     */
    protected function causedByLostConnection(LdapRecordException $e)
    {
        return strpos($e->getMessage(), 'contact LDAP server') !== false;
    }
}
