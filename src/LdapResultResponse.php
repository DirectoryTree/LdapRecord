<?php

namespace LdapRecord;

class LdapResultResponse
{
    /**
     * Constructor.
     *
     * @return void
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
     *
     * @return bool
     */
    public function successful()
    {
        return $this->errorCode === 0 && empty($this->errorMessage);
    }

    /**
     * Determine if the LDAP response indicates a failed status.
     *
     * @return bool
     */
    public function failed()
    {
        return ! $this->successful();
    }
}
