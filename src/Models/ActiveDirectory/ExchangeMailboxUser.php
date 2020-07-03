<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\ActiveDirectory\Scopes\IncludeMailboxUsers;

class ExchangeMailboxUser extends User
{
    protected static function boot()
    {
        parent::boot();

        self::addGlobalScope(new IncludeMailboxUsers());
    }
}
