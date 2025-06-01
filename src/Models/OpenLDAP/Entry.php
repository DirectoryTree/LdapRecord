<?php

namespace LdapRecord\Models\OpenLDAP;

use LdapRecord\Connection;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Models\Types\OpenLDAP;
use LdapRecord\Query\Model\OpenLdapBuilder;

/** @mixin OpenLdapBuilder */
class Entry extends BaseEntry implements OpenLDAP
{
    /**
     * The attribute key that contains the models object GUID.
     */
    protected string $guidKey = 'entryuuid';

    /**
     * Create a new query builder.
     */
    public function newQueryBuilder(Connection $connection): OpenLdapBuilder
    {
        return new OpenLdapBuilder($this, $connection->query());
    }
}
