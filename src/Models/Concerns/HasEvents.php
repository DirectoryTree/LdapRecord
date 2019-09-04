<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Models\Events\Event;
use LdapRecord\Container;

trait HasEvents
{
    /**
     * Fires the specified model event.
     *
     * @param Event $event
     *
     * @return mixed
     */
    protected function fireModelEvent(Event $event)
    {
        return Container::getEventDispatcher()->fire($event);
    }
}
