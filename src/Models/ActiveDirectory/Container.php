<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Concerns\HasDescription;
use LdapRecord\Models\Concerns\HasCriticalSystemObject;

/**
 * Class Container.
 *
 * Represents an LDAP container.
 */
class Container extends Entry
{
    use HasDescription,
        HasCriticalSystemObject;

    /**
     * The object classes of the LDAP model.
     * 
     * @var array
     */
    public static $objectClasses = [
        'top',
        'container',
    ];
    
    /**
     * Returns the containers system flags integer.
     *
     * An integer value that contains flags that define additional properties of the class.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms680022(v=vs.85).aspx
     *
     * @return string
     */
    public function getSystemFlags()
    {
        return $this->getFirstAttribute('systemflags');
    }
}
