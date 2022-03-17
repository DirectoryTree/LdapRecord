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
        $rootDSE = Entry::query()->find('');
        $base = $rootDSE->getFirstAttribute('defaultNamingContext');
        $domain = static::query()->find($base);
        $duration = $domain->getFirstAttribute('lockoutDuration');

        return -1 * round((int) $duration / 10000000);
    }
}
