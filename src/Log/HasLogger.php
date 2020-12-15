<?php

namespace LdapRecord\Log;

use Psr\Log\LoggerInterface;

trait HasLogger
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * The events to register listeners for during initialization.
     *
     * @var array
     */
    protected $listen = [
        'LdapRecord\Auth\Events\*',
        'LdapRecord\Query\Events\*',
        'LdapRecord\Models\Events\*',
    ];

    /**
     * Get the logger instance.
     *
     * @return LoggerInterface|null
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set the logger instance.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->initEventLogger();
    }

    /**
     * Initializes the event logger.
     *
     * @return void
     */
    public function initEventLogger()
    {
        $dispatcher = $this->getEventDispatcher();

        $logger = $this->newEventLogger();

        foreach ($this->listen as $event) {
            $dispatcher->listen($event, function ($eventName, $events) use ($logger) {
                foreach ($events as $event) {
                    $logger->log($event);
                }
            });
        }
    }

    /**
     * Unset the logger instance.
     *
     * @return void
     */
    public function unsetLogger()
    {
        $this->logger = null;
    }
}
