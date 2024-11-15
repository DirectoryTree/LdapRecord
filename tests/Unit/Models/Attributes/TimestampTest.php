<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use DateTime;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Tests\TestCase;
use LdapRecord\Utilities;

class TimestampTest extends TestCase
{
    protected $utcLdapTimestamp = '20201002021244Z';
    protected $offsetLdapTimestamp = '20201002021244-0500';

    protected $utcindowsTimestamp = '20201002021618.0Z';
    protected $utcindowsMillisecTimestamp = '20231106080944.000Z';
    protected $offsetWindowsTimestamp = '20201002021618.0-0500';

    protected $windowsIntegerTime = '132460789290000000';

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

        $date = (new DateTime())->setTimezone(new \DateTimeZone('EST'));
        $this->assertEquals($date->format('YmdHis').'-0500', $timestamp->fromDateTime($date));
    }

    public function test_dates_can_be_converted_to_windows_type()
    {
        $timestamp = new Timestamp('windows');

        $date = new DateTime();
        $this->assertEquals($date->format('YmdHis.0\Z'), $timestamp->fromDateTime($date));

        $date = (new DateTime())->setTimezone(new \DateTimeZone('EST'));
        $this->assertEquals($date->format('YmdHis.0').'-0500', $timestamp->fromDateTime($date));
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

        $datetime = $timestamp->toDateTime($this->utcLdapTimestamp);

        $this->assertInstanceOf(DateTime::class, $datetime);
        $this->assertEquals('UTC', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:12:44 GMT+0000', $datetime->toString());

        $datetime = $timestamp->toDateTime($this->offsetLdapTimestamp);

        $this->assertInstanceOf(DateTime::class, $datetime);
        $this->assertEquals('-05:00', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:12:44 GMT-0500', $datetime->toString());
    }

    public function test_windows_type_can_be_converted_to_date()
    {
        $timestamp = new Timestamp('windows');

        $datetime = $timestamp->toDateTime($this->utcindowsTimestamp);
        $this->assertEquals('UTC', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:16:18 GMT+0000', $datetime->toString());

        $datetime = $timestamp->toDateTime($this->utcindowsMillisecTimestamp);
        $this->assertEquals('UTC', $datetime->timezone->getName());
        $this->assertEquals('Mon Nov 06 2023 08:09:44 GMT+0000', $datetime->toString());

        $datetime = $timestamp->toDateTime($this->offsetWindowsTimestamp);
        $this->assertEquals('-05:00', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:16:18 GMT-0500', $datetime->toString());
    }

    public function test_windows_integer_type_can_be_converted_to_date()
    {
        $timestamp = new Timestamp('windows-int');

        $datetime = $timestamp->toDateTime($this->windowsIntegerTime);
        $this->assertEquals('UTC', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:22:09 GMT+0000', $datetime->toString());
    }

    public function test_windows_time_to_date_time_always_has_utc_set_as_timezone()
    {
        date_default_timezone_set('Australia/Sydney');

        $timestamp = new Timestamp('windows');

        $datetime = $timestamp->toDateTime($this->utcindowsTimestamp);

        $this->assertEquals('UTC', $datetime->timezone->getName());

        date_default_timezone_set('UTC');
    }

    public function test_windows_int_type_properly_handles_maximum()
    {
        $timestamp = new Timestamp('windows-int');

        $max = Timestamp::WINDOWS_INT_MAX;

        $this->assertSame($max, $timestamp->toDateTime($max));
        $this->assertSame($max, $timestamp->toDateTime((string) $max));
    }

    public function test_windows_int_type_properly_handles_minimum()
    {
        $timestamp = new Timestamp('windows-int');

        $min = 0;

        $this->assertSame($min, $timestamp->toDateTime($min));
        $this->assertSame($min, $timestamp->toDateTime((string) $min));
    }
}
