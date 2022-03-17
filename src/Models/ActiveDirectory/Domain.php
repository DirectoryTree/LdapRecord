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
        /** @var Entry $rootDSE */
        $rootDSE = Entry::query()->find('');
        /** @var string $base */
        $base = $rootDSE->getFirstAttribute('defaultNamingContext');
        /** @var Domain $domain */
        $domain = static::query()->find($base);
        $duration = $domain->getFirstAttribute('lockoutDuration');

        return -1 * (int) round($duration / 10000000);
    }
}
