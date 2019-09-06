<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Models\Relations\HasMany;

trait HasGroups
{
    /**
     * The groups relationship.
     *
     * @return HasMany
     */
    abstract public function groups() : HasMany;
}
