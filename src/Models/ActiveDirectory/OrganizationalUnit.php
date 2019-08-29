<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Attributes\DistinguishedName;

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
     * {@inheritdoc}
     */
    protected function getCreatableDn()
    {
        return (new DistinguishedName($this->getDn()))->addOu($this->getFirstAttribute('ou'));
    }
}
