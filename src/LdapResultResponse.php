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
        public readonly int $errorCode,
        public readonly string|null $matchedDn,
        public readonly string|null $errorMessage,
        public readonly array $referrals = [],
        public readonly array $controls = []
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
