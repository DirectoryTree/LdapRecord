<?php

namespace LdapRecord\Models\OpenLDAP;

use LdapRecord\Models\Types\OpenLDAP;
use LdapRecord\Connections\LdapInterface;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Query\Types\OpenLdapQuery;

/**
 * @method OpenLdapQuery query()
 */
class Entry extends BaseEntry implements OpenLDAP
{
    /**
     * Create a new query builder.
     *
     * @param LdapInterface $connection
     *
     * @return OpenLdapQuery
     */
    public function newQueryBuilder(LdapInterface $connection)
    {
        return new OpenLdapQuery($connection);
    }
}
