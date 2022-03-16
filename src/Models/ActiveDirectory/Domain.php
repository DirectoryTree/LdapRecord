<?php

namespace LdapRecord\Models\ActiveDirectory;

class Domain extends Entry
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [
        'top',
        'domain',
        'domainDNS',
    ];

    /**
     * @return int
     */
    public static function getLockoutDuration(): int
    {
        $query = static::query();
        $base = $query->getBaseDn();
        $domain = $query->find($base);
        $duration = $domain->getFirstAttribute('lockoutduration');

        return -1 * round((int) $duration / 10000000);
    }
}
