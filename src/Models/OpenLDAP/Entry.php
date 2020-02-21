<?php

namespace LdapRecord\Models\OpenLDAP;

use LdapRecord\Connection;
use LdapRecord\Models\Types\OpenLDAP;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Query\Model\OpenLdapBuilder;

/** @mixin OpenLdapBuilder */
class Entry extends BaseEntry implements OpenLDAP
{
    /**
     * The attribute key that contains the models object GUID.
     *
     * @var string
     */
    protected $guidKey = 'entryuuid';

    /**
     * Create a new query builder.
     *
     * @param Connection $connection
     *
     * @return OpenLdapBuilder
     */
    public function newQueryBuilder(Connection $connection)
    {
        return new OpenLdapBuilder($connection);
    }
}
