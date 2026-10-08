<?php

namespace LdapRecord\Models\ActiveDirectory\Concerns;

use LdapRecord\LdapInterface;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\SecurityDescriptor;
use LdapRecord\Models\ModelDoesNotExistException;

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

    /**
     * Save selected descriptor sections without saving other pending attributes.
     *
     * @throws ModelDoesNotExistException
     */
    public function saveSecurityDescriptor(SecurityDescriptor $descriptor, int $parts = SecurityDescriptor::DACL_SECURITY_INFORMATION): void
    {
        $this->assertExists();

        $controls = $this->newQuery()->withSecurityDescriptor($parts)->toBase()->controls;
        $binary = $descriptor->toBinary();
        $merged = ($this->securityDescriptor() ?? new SecurityDescriptor)->merge($descriptor, $parts)->toBinary();
        $original = (new SecurityDescriptor($this->original['ntsecuritydescriptor'][0] ?? null))
            ->merge($descriptor, $parts)->toBinary();

        $this->dispatch(['saving', 'updating']);

        $this->getConnection()->run(function (LdapInterface $ldap) use ($controls, $binary) {
            $previous = $ldap->getOption(LDAP_OPT_SERVER_CONTROLS) ?: [];
            $controls = array_merge(array_values(array_filter(
                $previous,
                fn (array $control) => $control['oid'] !== LdapInterface::OID_SERVER_SD_FLAGS
            )), array_values($controls));

            try {
                if (! $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $controls)) {
                    throw new LdapRecordException('Unable to apply the security descriptor control.');
                }
                $ldap->modReplace($this->dn, ['ntsecuritydescriptor' => [$binary]]);
            } finally {
                $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $previous);
            }
        });

        $this->attributes['ntsecuritydescriptor'] = [$merged];
        $this->original['ntsecuritydescriptor'] = [$original];

        $this->dispatch(['updated', 'saved']);
    }
}
