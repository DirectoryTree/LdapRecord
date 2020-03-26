<?php

namespace LdapRecord\Tests\Models\Attributes;

use DateTime;
use LdapRecord\LdapRecordException;
use LdapRecord\Tests\TestCase;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Utilities;

class TimestampTest extends TestCase
{
    public function test_exception_is_thrown_when_invalid_type_given()
    {
        $this->expectException(LdapRecordException::class);

        new Timestamp('invalid');
    }

    public function test_converting_to_date_returns_date_objects()
    {
        $timestamp = new Timestamp('ldap');

        $date = new DateTime();

        $this->assertEquals($date, $timestamp->toDateTime($date));
    }

    public function test_dates_can_be_converted_to_ldap_type()
    {
        $timestamp = new Timestamp('ldap');

        $date = new DateTime();

        $this->assertEquals($date->format('YmdHis\Z'), $timestamp->fromDateTime($date));
    }

    public function test_dates_can_be_converted_to_windows_type()
    {
        $timestamp = new Timestamp('windows');

        $date = new DateTime();

        $this->assertEquals($date->format('YmdHis.0\Z'), $timestamp->fromDateTime($date));
    }

    public function test_dates_can_be_converted_to_windows_integer_type()
    {
        $timestamp = new Timestamp('windows-int');

        $date = new DateTime();

        $this->assertEquals(Utilities::convertUnixTimeToWindowsTime($date->getTimestamp()), $timestamp->fromDateTime($date));
    }

    public function test_ldap_type_can_be_converted_to_date()
    {
        $timestamp = new Timestamp('ldap');

        $date = (new DateTime)->format('YmdHis\Z');

        $this->assertInstanceOf(DateTime::class, $timestamp->toDateTime($date));
    }

    public function test_windows_type_can_be_converted_to_date()
    {
        $timestamp = new Timestamp('windows');

        $date = (new DateTime)->format('YmdHis.0\Z');

        $this->assertInstanceOf(DateTime::class, $timestamp->toDateTime($date));
    }

    public function test_windows_integer_type_can_be_converted_to_date()
    {
        $timestamp = new Timestamp('windows-int');

        $date = Utilities::convertUnixTimeToWindowsTime((new DateTime)->getTimestamp());

        $this->assertInstanceOf(DateTime::class, $timestamp->toDateTime($date));
    }
}

