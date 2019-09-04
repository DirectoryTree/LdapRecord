<?php

namespace LdapRecord\Models\OpenLDAP;

use LdapRecord\Models\Types\OpenLDAP;
use LdapRecord\LdapInterface;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Query\Model\OpenLdapBuilder;

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
