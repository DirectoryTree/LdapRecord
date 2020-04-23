<?php

namespace LdapRecord\Models\OpenLDAP;

class Group extends Entry
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'groupofuniquenames',
    ];

    /**
     * Get a creatable RDN for the model.
     *
     * @return string
     */
    public function getCreatableRdn()
    {
        $name = $this->escape($this->getFirstAttribute('ou'))->dn();

        return "ou=$name";
    }
}
