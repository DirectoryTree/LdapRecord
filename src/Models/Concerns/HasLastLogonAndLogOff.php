<?php

namespace LdapRecord\Models\Concerns;

trait HasLastLogonAndLogOff
{
    /**
     * Returns the models's last log off date.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676822(v=vs.85).aspx
     *
     * @return string
     */
    public function getLastLogOff()
    {
        return $this->getFirstAttribute('lastlogoff');
    }

    /**
     * Returns the models's last log on date.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676823(v=vs.85).aspx
     *
     * @return string
     */
    public function getLastLogon()
    {
        return $this->getFirstAttribute('lastlogon');
    }

    /**
     * Returns the models's last log on timestamp.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676824(v=vs.85).aspx
     *
     * @return string
     */
    public function getLastLogonTimestamp()
    {
        return $this->getFirstAttribute('lastlogontimestamp');
    }
}
