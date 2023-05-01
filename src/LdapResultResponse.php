<?php

namespace LdapRecord;

class LdapResultResponse
{
    /**
     * Constructor.
     */
    public function __construct(
        public readonly int $errorCode = 0,
        public readonly string|null $matchedDn = null,
        public readonly string|null $errorMessage = null,
        public readonly ?array $referrals = null,
        public readonly ?array $controls = null,
    ) {
    }

    /**
     * Determine if the LDAP response indicates a successful status.
     */
    public function successful(): bool
    {
        return $this->errorCode === 0 && empty($this->errorMessage);
    }

    /**
     * Determine if the LDAP response indicates a failed status.
     */
    public function failed(): bool
    {
        return ! $this->successful();
    }
}
