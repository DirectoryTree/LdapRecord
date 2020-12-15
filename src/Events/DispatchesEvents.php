<?php

namespace LdapRecord\Events;

trait DispatchesEvents
{
    /**
     * The event dispatcher instance.
     *
     * @var DispatcherInterface|null
     */
    protected $dispatcher;

    /**
     * Get the event dispatcher instance.
     *
     * @return DispatcherInterface
     */
    public function getEventDispatcher()
    {
        if (! isset($this->dispatcher)) {
            $this->setEventDispatcher(new Dispatcher());
        }

        return $this->dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param DispatcherInterface $dispatcher
     *
     * @return void
     */
    public function setEventDispatcher(DispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher instance.
     *
     * @return void
     */
    public function unsetEventDispatcher()
    {
        $this->dispatcher = null;
    }
}
