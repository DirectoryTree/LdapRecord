<?php

namespace LdapRecord\Models\ActiveDirectory;

class ExchangeMailboxUser extends User
{
    protected static function boot()
    {
        parent::boot();

        self::$objectClasses['msExchMailboxGuid'] = '*';
    }
}
