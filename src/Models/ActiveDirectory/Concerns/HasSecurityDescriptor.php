<?php

namespace LdapRecord\Models\ActiveDirectory\Concerns;

use LdapRecord\Models\Attributes\SecurityDescriptor;

trait HasSecurityDescriptor
{
    /**
     * Get a fresh instance of the loaded security descriptor.
     */
    public function securityDescriptor(): ?SecurityDescriptor
    {
        $binary = $this->getFirstAttribute('ntsecuritydescriptor');

        return $binary === null ? null : new SecurityDescriptor($binary);
    }

    /**
     * Store a descriptor or raw LDAP value as a binary attribute.
     */
    public function setNtSecurityDescriptorAttribute(mixed $value): void
    {
        $this->attributes['ntsecuritydescriptor'] = $value instanceof SecurityDescriptor
            ? [$value->toBinary()]
            : (array) $value;
    }
}
