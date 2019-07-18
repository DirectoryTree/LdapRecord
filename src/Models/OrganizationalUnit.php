<?php

namespace LdapRecord\Models;

use LdapRecord\Query\Builder;

/**
 * Class OrganizationalUnit.
 *
 * Represents an LDAP organizational unit.
 */
class OrganizationalUnit extends Entry
{
    use Concerns\HasDescription;

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
     * Retrieves the organization units OU attribute.
     *
     * @return string
     */
    public function getOu()
    {
        return $this->getFirstAttribute($this->schema->organizationalUnitShort());
    }

    /**
     * {@inheritdoc}
     */
    protected function getCreatableDn()
    {
        return $this->getDnBuilder()->addOU($this->getOu());
    }
}
