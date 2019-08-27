<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Models\Relations\BelongsToMany;

trait HasGroups
{
    /**
     * The groups relationship.
     *
     * @return BelongsToMany
     */
    abstract public function groups() : BelongsToMany;
}
