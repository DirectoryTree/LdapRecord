<?php

namespace LdapRecord\Models\FreeIPA;

use LdapRecord\Connection;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Models\Types\FreeIPA;
use LdapRecord\Query\Model\FreeIpaBuilder;

/** @mixin FreeIpaBuilder */
class Entry extends BaseEntry implements FreeIPA
{
    /**
     * The attribute key that contains the models object GUID.
     */
    protected string $guidKey = 'ipauniqueid';

    /**
     * The default attributes that should be mutated to dates.
     */
    protected array $defaultDates = [
        'krblastpwdchange' => 'ldap',
        'krbpasswordexpiration' => 'ldap',
    ];

    /**
     * Create a new query builder.
     */
    public function newQueryBuilder(Connection $connection): FreeIpaBuilder
    {
        return new FreeIpaBuilder($this, $connection->query());
    }
}
