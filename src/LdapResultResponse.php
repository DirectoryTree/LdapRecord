<?php

namespace LdapRecord;

class LdapResultResponse
{
    /**
     * Constructor.
     *
     * @param int         $errorCode
     * @param string|null $matchedDn
     * @param string      $errorMessage
     * @param array       $referrals
     * @param array       $controls
     *
     * @return void
     */
    public function __construct(
        protected $errorCode,
        protected $matchedDn,
        protected $errorMessage,
        protected $referrals,
        protected $controls
    ) {
    }

    /**
     * Get the LDAP error code. "0" if no error occurred.
     *
     * @return int
     */
    public function getErrorCode()
    {
        return $this->errorCode;
    }

    /**
     * Get the matched DN if one was recognised within the request, otherwise it will be set to null.
     *
     * @return string|null
     */
    public function getMatchedDn()
    {
        return $this->matchedDn;
    }

    /**
     * Get the LDAP error message in the result, or an empty string if no error occurred.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * Get the array of the referral strings in the result, or an empty array if no referrals were returned.
     *
     * @return array
     */
    public function getReferrals()
    {
        return $this->referrals;
    }

    /**
     * Get the array of LDAP Controls which have been sent with the response.
     *
     * @return array
     */
    public function getControls()
    {
        return $this->controls;
    }

    /**
     * Determine if the LDAP response indicates a successful status.
     *
     * @return bool
     */
    public function successful()
    {
        return (int) $this->errorCode === 0 && empty($this->errorMessage);
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
