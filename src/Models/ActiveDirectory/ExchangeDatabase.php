<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\ActiveDirectory\Scopes\InConfigurationContext;

class ExchangeDatabase extends Entry
{
    /**
     * {@inheritdoc}
     */
    public static $objectClasses = ['msExchMDB'];

    /**
     * {@inheritdoc}
     */
    public static function boot()
    {
        parent::boot();

        static::addGlobalScope(new InConfigurationContext);
    }
}
