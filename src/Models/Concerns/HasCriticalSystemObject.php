<?php

namespace LdapRecord\Models\Concerns;

trait HasCriticalSystemObject
{
    /**
     * Returns true / false if the entry is a critical system object.
     *
     * @return null|bool
     */
    public function isCriticalSystemObject()
    {
        return $this->convertStringToBool(
            $this->getFirstAttribute('iscriticalsystemobject')
        );
    }
}
