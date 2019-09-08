<?php

namespace LdapRecord\Models\ActiveDirectory\Relations;

use LdapRecord\Models\Relations\HasOne;

class HasOnePrimaryGroup extends HasOne
{
    /**
     * Get the foreign model by the given value.
     *
     * @param string $value
     *
     * @return \LdapRecord\Models\Model|null
     */
    protected function getForeignModelByValue($value)
    {
        return $this->query->findBySid(
            $this->getParentModelObjectSid()
        );
    }

    /**
     * Get the parent relationship models converted object sid.
     *
     * @return string
     */
    protected function getParentModelObjectSid()
    {
        return preg_replace(
            '/\d+$/',
            $this->parent->getFirstAttribute($this->relationKey),
            $this->parent->getConvertedSid()
        );
    }
}
