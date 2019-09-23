<?php

namespace LdapRecord\Models\OpenLDAP;

use LdapRecord\LdapInterface;
use LdapRecord\Models\Types\OpenLDAP;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Query\Model\OpenLdapBuilder;

/** @mixin OpenLdapBuilder */
class Entry extends BaseEntry implements OpenLDAP
{
    /**
     * The attribute key that contains the Object GUID.
     *
     * @var string
     */
    protected $guidKey = 'entryuuid';

    /**
     * Create a new query builder.
     *
     * @param LdapInterface $connection
     *
     * @return OpenLdapBuilder
     */
    public function newQueryBuilder(LdapInterface $connection)
    {
        return new OpenLdapBuilder($connection);
    }
}
