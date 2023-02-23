<?php

namespace LdapRecord\Testing;

use Closure;
use Exception;
use LdapRecord\LdapRecordException;
use LdapRecord\LdapResultResponse;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\Constraint\IsEqual;
use UnexpectedValueException;

class LdapExpectation
{
    /**
     * The value to return from the expectation.
     */
    protected mixed $value = null;

    /**
     * The exception to throw from the expectation.
     */
    protected ?Exception $exception = null;

    /**
     * The amount of times the expectation should be called.
     *
     * @var int
     */
    protected int $count = 1;

    /**
     * The method that the expectation belongs to.
     *
     * @var string
     */
    protected string $method;

    /**
     * The methods argument's.
     *
     * @var array
     */
    protected array $args = [];

    /**
     * Whether the same expectation should be returned indefinitely.
     *
     * @var bool
     */
    protected bool $indefinitely = true;

    /**
     * Whether the expectation should return errors.
     *
     * @var bool
     */
    protected bool $errors = false;

    /**
     * The error number to return.
     *
     * @var int
     */
    protected ?string $errorCode = null;

    /**
     * The last error string to return.
     */
    protected ?string $errorMessage = null;

    /**
     * The diagnostic message string to return.
     */
    protected ?string $errorDiagnosticMessage = null;

    /**
     * Constructor.
     *
     * @param string $method
     */
    public function __construct(string $method)
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
    public function with(mixed $args): static
    {
        $this->args = array_map(function ($arg) {
            if ($arg instanceof Closure) {
                return new Callback($arg);
            }

            if (! $arg instanceof Constraint) {
                return new IsEqual($arg);
            }

            return $arg;
        }, is_array($args) ? $args : func_get_args());

        return $this;
    }

    /**
     * Set the expected value to return.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function andReturn(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    /**
     * The error message to return from the expectation.
     *
     * @param int    $errorCode
     * @param string $errorMessage
     * @param string $diagnosticMessage
     *
     * @return $this
     */
    public function andReturnError(int $errorCode = 1, string $errorMessage = '', string $diagnosticMessage = ''): static
    {
        $this->errors = true;

        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->errorDiagnosticMessage = $diagnosticMessage;

        return $this;
    }

    /**
     * Return an error LDAP result response.
     */
    public function andReturnErrorResponse($code = 1, string $errorMessage = null): static
    {
        return $this->andReturnResponse($code, $errorMessage);
    }

    /**
     * Return an LDAP result response.
     */
    public function andReturnResponse(
        int $errorCode = 0,
        string|null $matchedDn = null,
        string|null $errorMessage = null,
        array $referrals = [],
        array $controls = []
    ): static
    {
        return $this->andReturn(
            new LdapResultResponse($errorCode, $matchedDn, $errorMessage, $referrals, $controls)
        );
    }

    /**
     * Set the expected exception to throw.
     */
    public function andThrow(string|Exception $exception): static
    {
        if (is_string($exception)) {
            $exception = new LdapRecordException($exception);
        }

        $this->exception = $exception;

        return $this;
    }

    /**
     * Set the expectation to be only called once.
     *
     * @return $this
     */
    public function once(): static
    {
        return $this->times(1);
    }

    /**
     * Set the expectation to be only called twice.
     *
     * @return $this
     */
    public function twice(): static
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
    public function times(int $count = 1): static
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
    public function getMethod(): string
    {
        if (is_null($this->method)) {
            throw new UnexpectedValueException('An expectation must have a method.');
        }

        return $this->method;
    }

    /**
     * Get the expected call count.
     *
     * @return int
     */
    public function getExpectedCount(): int
    {
        return $this->count;
    }

    /**
     * Get the expected arguments.
     *
     * @return Constraint[]
     */
    public function getExpectedArgs(): array
    {
        return $this->args;
    }

    /**
     * Get the expected exception.
     *
     * @return null|\Exception|LdapRecordException
     */
    public function getExpectedException(): ?Exception
    {
        return $this->exception;
    }

    /**
     * Get the expected value.
     *
     * @return mixed
     */
    public function getExpectedValue(): mixed
    {
        return $this->value;
    }

    /**
     * Determine whether the expectation is returning an error.
     *
     * @return bool
     */
    public function isReturningError(): bool
    {
        return $this->errors;
    }

    /**
     * @return int
     */
    public function getExpectedErrorCode(): ?int
    {
        return $this->errorCode;
    }

    /**
     * @return string
     */
    public function getExpectedErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return string
     */
    public function getExpectedErrorDiagnosticMessage(): ?string
    {
        return $this->errorDiagnosticMessage;
    }

    /**
     * Decrement the call count of the expectation.
     *
     * @return $this
     */
    public function decrementCallCount(): static
    {
        if (! $this->indefinitely) {
            $this->count -= 1;
        }

        return $this;
    }
}
