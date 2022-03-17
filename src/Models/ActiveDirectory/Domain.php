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
     * @return int in seconds
     */
    public static function getLockoutDuration(): int
    {
        $rootDSE = Entry::query()->find('')->first(['defaultNamingContext']);
        $base = $rootDSE->getFirstAttribute('defaultNamingContext');
        $domain = static::query()->find($base)->first(['lockoutDuration']);
        $duration = (int) $domain->getFirstAttribute('lockoutDuration');

        return -1 * (int) round($duration / 10000000);
    }
}
