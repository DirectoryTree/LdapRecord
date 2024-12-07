<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use DateTime;
use DateTimeZone;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Tests\TestCase;

class TimestampTest extends TestCase
{
    protected int $unixTimestamp = 1601605329;

    protected string $utcLdapTimestamp = '20201002021240Z';

    protected string $utcLdapMillisecondsTimestamp = '20231106080944.000Z';

    protected string $offsetLdapTimestamp = '20201002021244-0500';

    protected string $utcWindowsTimestamp = '20201002021618.0Z';

    protected string $offsetWindowsTimestamp = '20201002021618.0-0500';

    protected string $windowsIntegerTime = '132460789290000000';

    public function test_exception_is_thrown_when_invalid_type_given()
    {
        $this->expectException(LdapRecordException::class);

        new Timestamp('invalid');
    }

    public function test_converting_to_date_returns_date_objects()
    {
        $timestamp = new Timestamp(Timestamp::TYPE_LDAP);

        $date = new DateTime;

        $this->assertEquals($date, $timestamp->toDateTime($date));
    }

    public function test_dates_can_be_converted_to_ldap_type()
    {
        $timestamp = new Timestamp(Timestamp::TYPE_LDAP);

        $date = new DateTime;
        $this->assertEquals($date->format('YmdHis\Z'), $timestamp->fromDateTime($date));

        $date = (new DateTime)->setTimezone(new DateTimeZone('EST'));
        $this->assertEquals($date->format('YmdHis').'-0500', $timestamp->fromDateTime($date));
    }

    public function test_dates_can_be_converted_to_windows_type()
    {
        $timestamp = new Timestamp(Timestamp::TYPE_WINDOWS);

        $date = new DateTime;
        $this->assertEquals($date->format('YmdHis.0\Z'), $timestamp->fromDateTime($date));

        $date = (new DateTime)->setTimezone(new DateTimeZone('EST'));
        $this->assertEquals($date->format('YmdHis.0').'-0500', $timestamp->fromDateTime($date));
    }

    public function test_dates_can_be_converted_to_windows_integer_type()
    {
        $timestamp = new Timestamp(Timestamp::TYPE_WINDOWS_INT);

        $date = (new DateTime)->setTimestamp($this->unixTimestamp);

        $this->assertEquals('132460789290000000', $timestamp->fromDateTime($date));
    }

    public function test_ldap_type_can_be_converted_to_date()
    {
        $timestamp = new Timestamp(Timestamp::TYPE_LDAP);

        $datetime = $timestamp->toDateTime($this->utcLdapTimestamp);

        $this->assertInstanceOf(DateTime::class, $datetime);
        $this->assertEquals('UTC', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:12:40 GMT+0000', $datetime->toString());

        $datetime = $timestamp->toDateTime($this->offsetLdapTimestamp);

        $this->assertInstanceOf(DateTime::class, $datetime);
        $this->assertEquals('-05:00', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:12:44 GMT-0500', $datetime->toString());

        $datetime = $timestamp->toDateTime($this->utcLdapMillisecondsTimestamp);
        $this->assertEquals('UTC', $datetime->timezone->getName());
        $this->assertEquals('Mon Nov 06 2023 08:09:44 GMT+0000', $datetime->toString());
    }

    public function test_windows_type_can_be_converted_to_date()
    {
        $timestamp = new Timestamp(Timestamp::TYPE_WINDOWS);

        $datetime = $timestamp->toDateTime($this->utcWindowsTimestamp);
        $this->assertEquals('UTC', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:16:18 GMT+0000', $datetime->toString());

        $datetime = $timestamp->toDateTime($this->offsetWindowsTimestamp);
        $this->assertEquals('-05:00', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:16:18 GMT-0500', $datetime->toString());
    }

    public function test_windows_integer_type_can_be_converted_to_date()
    {
        $timestamp = new Timestamp(Timestamp::TYPE_WINDOWS_INT);

        $datetime = $timestamp->toDateTime($this->windowsIntegerTime);
        $this->assertEquals('UTC', $datetime->timezone->getName());
        $this->assertEquals('Fri Oct 02 2020 02:22:09 GMT+0000', $datetime->toString());
    }

    public function test_windows_time_to_date_time_always_has_utc_set_as_timezone()
    {
        date_default_timezone_set('Australia/Sydney');

        $timestamp = new Timestamp(Timestamp::TYPE_WINDOWS);

        $datetime = $timestamp->toDateTime($this->utcWindowsTimestamp);

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
