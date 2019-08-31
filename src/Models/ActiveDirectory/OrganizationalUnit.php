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
     * {@inheritdoc}
     */
    public function getCreatableDn()
    {
        return $this->getNewDnBuilder($this->newQuery()->getDn())->addOu($this->getFirstAttribute('ou'));
    }
}
