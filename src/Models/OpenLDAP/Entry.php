<?php

namespace LdapRecord\Models\OpenLDAP;

use LdapRecord\Models\Types\OpenLDAP;
use LdapRecord\Connections\LdapInterface;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Query\Expressive\OpenLdapBuilder;

/** @mixin OpenLdapBuilder */
class Entry extends BaseEntry implements OpenLDAP
{
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
