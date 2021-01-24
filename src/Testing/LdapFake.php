<?php

namespace LdapRecord\Testing;

use Closure;
use LdapRecord\LdapBase;
use LdapRecord\DetailedError;
use LdapRecord\Testing\Expectations\BindExpectation;
use LdapRecord\Testing\Expectations\LdapExpectation;

class LdapFake extends LdapBase
{
    /**
     * The expectations of the LDAP fake.
     *
     * @var array
     */
    protected $expectations = [
        'bind' => [],
    ];

    /**
     * The default fake error number.
     *
     * @var int
     */
    protected $errNo = 1;

    /**
     * The default fake last error string.
     *
     * @var int
     */
    protected $lastError = '';

    /**
     * The default fake diagnostic message string.
     *
     * @var int
     */
    protected $diagnosticMessage = '';

    /**
     * Whether the fake is set to be bound indefinitely.
     *
     * @var bool
     */
    protected $bindingIndefinitely = false;

    /**
     * Set the user that will pass binding.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function shouldAuthenticateWith($dn)
    {
        return $this->shouldBindIndefinitelyWith($dn);
    }

    /**
     * Set the user that will always successfully bind.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function shouldBindIndefinitelyWith($dn)
    {
        $this->bindingIndefinitely = true;

        $this->shouldBindOnceWith($dn);

        return $this;
    }

    /**
     * Add a user that will successfully bind once.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function shouldBindOnceWith($dn)
    {
        $expectation = new Expectations\BindExpectation($dn);

        $expectation->shouldPass();

        $this->addExpectation('bind', $expectation);

        return $this;
    }

    /**
     * Add a user that will fail binding once.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function shouldFailBindOnceWith($dn)
    {
        $expectation = new Expectations\BindExpectation($dn);

        $expectation->shouldFail();

        $this->addExpectation('bind', $expectation);

        return $this;
    }

    /**
     * Add an LDAP method expectation.
     *
     * @param string          $method
     * @param LdapExpectation $expectation
     *
     * @return void
     */
    public function addExpectation($method, LdapExpectation $expectation)
    {
        $this->expectations[$method][] = $expectation;
    }

    /**
     * Remove an expectation by method and key.
     *
     * @param string $method
     * @param int    $key
     *
     * @return void
     */
    protected function removeExpectation($method, $key)
    {
        unset($this->expectations[$method][$key]);
    }

    /**
     * Set the error number of a failed bind attempt.
     *
     * @param int $number
     *
     * @return $this
     */
    public function shouldReturnErrorNumber($number = 1)
    {
        $this->errNo = $number;

        return $this;
    }

    /**
     * Set the last error of a failed bind attempt.
     *
     * @param string $message
     *
     * @return $this
     */
    public function shouldReturnError($message = '')
    {
        $this->lastError = $message;

        return $this;
    }

    /**
     * Set the diagnostic message of a failed bind attempt.
     *
     * @param string $message
     *
     * @return $this
     */
    public function shouldReturnDiagnosticMessage($message = '')
    {
        $this->diagnosticMessage = $message;

        return $this;
    }

    /**
     * Fake a bind attempt.
     *
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function bind($username, $password)
    {
        if (($key = $this->findFailureBindExpectationKey($username)) !== false) {
            $this->removeExpectation('bind', $key);

            return $this->bound = false;
        }

        if (($key = $this->findSuccessfulBindExpectationKey($username)) === false) {
            return $this->bound = false;
        }

        if (! $this->bindingIndefinitely) {
            $this->removeExpectation('bind', $key);
        }

        return $this->bound = true;
    }

    /**
     * Determine if the user is expected to fail binding.
     *
     * @param string $username
     *
     * @return bool
     */
    protected function findFailureBindExpectationKey($username)
    {
        return $this->findBindExpectationKey($username, function (BindExpectation $expectation) {
            return $expectation->resolve() === false;
        });
    }

    /**
     * Determine if the user is expected to pass binding.
     *
     * @param string $username
     *
     * @return bool
     */
    protected function findSuccessfulBindExpectationKey($username)
    {
        return $this->findBindExpectationKey($username, function (BindExpectation $expectation) {
            return $expectation->resolve() === true;
        });
    }

    /**
     * Find a bind expectation by username and result.
     *
     * @param string  $username
     * @param Closure $result
     *
     * @return int|false
     */
    protected function findBindExpectationKey($username, Closure $result)
    {
        $callback = function (BindExpectation $expectation) use ($username, $result) {
            return strtolower($expectation->user()) === strtolower($username) && $result($expectation);
        };

        return $this->findExpectationKeyByCallback('bind', $callback);
    }

    /**
     * Attempt to find a method expectation by callback.
     *
     * @param string  $method
     * @param Closure $callback
     *
     * @return false|int|string
     */
    protected function findExpectationKeyByCallback($method, Closure $callback)
    {
        foreach ($this->expectations[$method] as $key => $expectation) {
            if ($callback($expectation)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Return a fake error number.
     *
     * @return int
     */
    public function errNo()
    {
        return $this->errNo;
    }

    /**
     * Return a fake error.
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * {@inheritDoc}
     */
    public function getDiagnosticMessage()
    {
        return $this->diagnosticMessage;
    }

    /**
     * Return a fake detailed error.
     *
     * @return DetailedError
     */
    public function getDetailedError()
    {
        return new DetailedError(
            $this->errNo(),
            $this->getLastError(),
            $this->getDiagnosticMessage()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getEntries($searchResults)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function setOption($option, $value)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function setOptions(array $options = [])
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function getOption($option, &$value = null)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function startTLS()
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function connect($hosts = [], $port = 389)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function search($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = null, $serverControls = [])
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function listing($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = null, $serverControls = [])
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function read($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = null, $serverControls = [])
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function parseResult($result, &$errorCode, &$dn, &$errorMessage, &$referrals, &$serverControls = [])
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function add($dn, array $entry)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function delete($dn)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function rename($dn, $newRdn, $newParent, $deleteOldRdn = false)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function modify($dn, array $entry)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function modifyBatch($dn, array $values)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function modAdd($dn, array $entry)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function modReplace($dn, array $entry)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function modDelete($dn, array $entry)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function controlPagedResult($pageSize = 1000, $isCritical = false, $cookie = '')
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function controlPagedResultResponse($result, &$cookie)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function freeResult($result)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function err2Str($number)
    {
        //
    }
}
