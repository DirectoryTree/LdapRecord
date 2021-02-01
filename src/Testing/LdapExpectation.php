<?php

namespace LdapRecord\Testing;

use UnexpectedValueException;
use PHPUnit\Framework\Constraint\IsEqual;
use PHPUnit\Framework\Constraint\Constraint;

class LdapExpectation
{
    /**
     * The value to return from the expectation.
     *
     * @var mixed
     */
    protected $value;

    /**
     * The amount of times the expectation should be called.
     *
     * @var int
     */
    protected $count = 1;

    /**
     * The method that the expectation belongs to.
     *
     * @var string
     */
    protected $method;

    /**
     * The methods argument's.
     *
     * @var array
     */
    protected $args = [];

    /**
     * Return the same expectation indefinitely.
     *
     * @var bool
     */
    protected $indefinitely = true;

    /**
     * Constructor.
     *
     * @param string $method
     */
    public function __construct($method)
    {
        $this->method = $method;
    }

    /**
     * Set the arguments that the operation should receive.
     *
     * @param mixed $args
     *
     * @return $this
     */
    public function with($args)
    {
        $args = is_array($args) ? $args : func_get_args();

        foreach ($args as $key => $arg) {
            if (! $arg instanceof Constraint) {
                $args[$key] = new IsEqual($arg);
            }
        }

        $this->args = $args;

        return $this;
    }

    /**
     * Set the expected value to return.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function andReturn($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Set the expectation to be only called once.
     *
     * @return $this
     */
    public function once()
    {
        return $this->times(1);
    }

    /**
     * Set the expectation to be only called twice.
     *
     * @return $this
     */
    public function twice()
    {
        return $this->times(2);
    }

    /**
     * Set the expectation to be called the given number of times.
     *
     * @param int $count
     *
     * @return $this
     */
    public function times($count = 1)
    {
        $this->indefinitely = false;

        $this->count = $count;

        return $this;
    }

    /**
     * Get the method the expectation belongs to.
     *
     * @return string
     */
    public function getMethod()
    {
        if (is_null($this->method)) {
            throw new UnexpectedValueException('The [$method] property cannot be null.');
        }

        return $this->method;
    }

    /**
     * Get the expected call count.
     *
     * @return int
     */
    public function getExpectedCount()
    {
        return $this->count;
    }

    /**
     * Get the expected arguments.
     *
     * @return array
     */
    public function getExpectedArgs()
    {
        return $this->args;
    }

    /**
     * Get the expected value.
     *
     * @return mixed
     */
    public function getExpectedValue()
    {
        return $this->value;
    }

    /**
     * Decrement the call count of the expectation.
     *
     * @return $this
     */
    public function decrementCallCount()
    {
        if (! $this->indefinitely) {
            $this->count -= 1;
        }

        return $this;
    }
}
