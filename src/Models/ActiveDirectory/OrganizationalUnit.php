<?php

namespace LdapRecord\Models\ActiveDirectory;

class OrganizationalUnit extends Entry
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'organizationalunit',
    ];

    /**
     * Get a creatable RDN for the model.
     *
     * @return string
     */
    public function getCreatableRdn()
    {
        return "ou={$this->getFirstAttribute('ou')}";
    }
}
