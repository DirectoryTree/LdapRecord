<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasMemberOf;
use LdapRecord\Models\Concerns\HasDescription;
use LdapRecord\Models\Concerns\HasLastLogonAndLogOff;
use LdapRecord\Models\Concerns\HasUserAccountControl;
use LdapRecord\Models\Concerns\HasCriticalSystemObject;

/**
 * Class Computer.
 *
 * Represents an LDAP computer / server.
 */
class Computer extends Entry
{
    use HasMemberOf,
        HasDescription,
        HasLastLogonAndLogOff,
        HasUserAccountControl,
        HasCriticalSystemObject;

    /**
     * The object classes of the LDAP model.
     * 
     * @var array
     */
    public static $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'user',
        'computer',
    ];

    /**
     * Returns the computers operating system.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679076(v=vs.85).aspx
     *
     * @return string
     */
    public function getOperatingSystem()
    {
        return $this->getFirstAttribute('operatingsystem');
    }

    /**
     * Returns the computers operating system version.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679079(v=vs.85).aspx
     *
     * @return string
     */
    public function getOperatingSystemVersion()
    {
        return $this->getFirstAttribute('operatingsystemversion');
    }

    /**
     * Returns the computers operating system service pack.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679078(v=vs.85).aspx
     *
     * @return string
     */
    public function getOperatingSystemServicePack()
    {
        return $this->getFirstAttribute('operatingsystemservicepack');
    }

    /**
     * Returns the computers DNS host name.
     *
     * @return string
     */
    public function getDnsHostName()
    {
        return $this->getFirstAttribute('dnshostname');
    }

    /**
     * Returns the computers bad password time.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675243(v=vs.85).aspx
     *
     * @return int
     */
    public function getBadPasswordTime()
    {
        return $this->getFirstAttribute('badpasswordtime');
    }

    /**
     * Returns the computers account expiry date.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675098(v=vs.85).aspx
     *
     * @return int
     */
    public function getAccountExpiry()
    {
        return $this->getFirstAttribute('accountexpires');
    }
}
