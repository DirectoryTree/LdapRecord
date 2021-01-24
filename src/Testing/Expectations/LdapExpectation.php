<?php

namespace LdapRecord\Testing\Expectations;

abstract class LdapExpectation
{
    /**
     * Resolve the expectation.
     *
     * @return bool
     */
    abstract public function resolve();
}
