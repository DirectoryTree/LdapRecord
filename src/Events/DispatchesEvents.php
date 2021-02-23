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
    public static function getEventDispatcher()
    {
        $instance = static::getInstance();

        if (! ($dispatcher = $instance->dispatcher())) {
            $instance->setEventDispatcher($dispatcher = new Dispatcher());
        }

        return $dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param DispatcherInterface $dispatcher
     *
     * @return void
     */
    public static function setEventDispatcher(DispatcherInterface $dispatcher)
    {
        static::getInstance()->setDispatcher($dispatcher);
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return DispatcherInterface|null
     */
    public function dispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Set the event dispatcher.
     *
     * @param DispatcherInterface $dispatcher
     *
     * @return void
     */
    public function setDispatcher(DispatcherInterface $dispatcher)
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
