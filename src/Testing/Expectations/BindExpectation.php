<?php

namespace LdapRecord\Testing\Expectations;

class BindExpectation extends LdapExpectation
{
    /**
     * The user that meets the bind expectation.
     *
     * @var string
     */
    protected $user;

    /**
     * Whether the bind is successful.
     *
     * @var bool
     */
    protected $successful = false;

    /**
     * Constructor.
     *
     * @param string $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Get the user that meets the bind expectation.
     *
     * @return string
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * Set the bind expectation to pass/
     *
     * @return void
     */
    public function shouldPass()
    {
        $this->successful = true;
    }

    /**
     * Set the bind expectation to fail.
     *
     * @return void
     */
    public function shouldFail()
    {
        $this->successful = false;
    }

    /**
     * Resolve the expectation.
     *
     * @return bool
     */
    public function resolve()
    {
        return $this->successful;
    }
}
