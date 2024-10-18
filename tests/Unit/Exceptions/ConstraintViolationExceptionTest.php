<?php

namespace LdapRecord\Tests\Unit\Exceptions;

use LdapRecord\DetailedError;
use LdapRecord\Exceptions\ConstraintViolationException;
use LdapRecord\Tests\TestCase;

class ConstraintViolationExceptionTest extends TestCase
{
    public function test_caused_by_password_policy()
    {
        $e = new ConstraintViolationException;

        $this->assertFalse($e->causedByPasswordPolicy());

        $error = new DetailedError(
            0,
            'Constraint violation',
            '0000052D: AtrErr: DSID-03190FD6'
        );

        $e->setDetailedError($error);

        $this->assertTrue($e->causedByPasswordPolicy());
    }

    public function test_caused_by_incorrect_password()
    {
        $e = new ConstraintViolationException;

        $this->assertFalse($e->causedByIncorrectPassword());

        $error = new DetailedError(
            0,
            'Constraint violation',
            '00000056: AtrErr: DSID-03190FD6'
        );

        $e->setDetailedError($error);

        $this->assertTrue($e->causedByIncorrectPassword());
    }
}
