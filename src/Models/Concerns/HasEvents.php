<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Manager;
use LdapRecord\Models\Events\Event;

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
        return Manager::getEventDispatcher()->fire($event);
    }
}
