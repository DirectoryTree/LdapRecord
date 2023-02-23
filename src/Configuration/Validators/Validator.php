<?php

namespace LdapRecord\Configuration\Validators;

use LdapRecord\Configuration\ConfigurationException;

abstract class Validator
{
    /**
     * The configuration key under validation.
     *
     * @var string
     */
    protected $key;

    /**
     * The configuration value under validation.
     */
    protected $value;

    /**
     * The validation exception message.
     *
     * @var string
     */
    protected $message;

    /**
     * Constructor.
     *
     * @param  string  $key
     */
    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @return bool
     */
    abstract public function passes();

    /**
     * Validate the configuration value.
     *
     * @return bool
     *
     * @throws ConfigurationException
     */
    public function validate()
    {
        if (! $this->passes()) {
            $this->fail();
        }

        return true;
    }

    /**
     * Throw a configuration exception.
     *
     * @return void
     *
     * @throws ConfigurationException
     */
    protected function fail()
    {
        throw new ConfigurationException(
            str_replace(':option', $this->key, $this->message)
        );
    }
}
