<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\ActiveDirectory\Scopes\HasServerRoleAttribute;
use LdapRecord\Models\ActiveDirectory\Scopes\InConfigurationContext;

class ExchangeServer extends Entry
{
    /**
     * {@inheritdoc}
     */
    public static $objectClasses = ['msExchExchangeServer'];

    /**
     * {@inheritdoc}
     */
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(new HasServerRoleAttribute);
        static::addGlobalScope(new InConfigurationContext);
    }
}
