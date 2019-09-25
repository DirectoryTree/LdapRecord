<?php

namespace LdapRecord\Models\Attributes;

use DateTime;
use Carbon\Carbon;
use LdapRecord\Utilities;
use Carbon\CarbonInterface;
use LdapRecord\LdapRecordException;

class Timestamp
{
    /**
     * The current timestamp type.
     *
     * @var string
     */
    protected $type;

    /**
     * The available timestamp types.
     *
     * @var array
     */
    protected $types = [
        'ldap',
        'windows',
        'windows-int',
    ];

    /**
     * Constructor.
     *
     * @param string $type
     *
     * @throws LdapRecordException
     */
    public function __construct($type)
    {
        $this->setType($type);
    }

    /**
     * Set the type of timestamp to convert from / to.
     *
     * @param string $type
     *
     * @throws LdapRecordException
     */
    public function setType($type)
    {
        if (!in_array($type, $this->types)) {
            throw new LdapRecordException("Unrecognized date type '{$this->type}'");
        }

        $this->type = $type;
    }

    /**
     * Converts the value to an LDAP date string.
     *
     * @param mixed $value
     *
     * @throws LdapRecordException
     *
     * @return float|string
     */
    public function fromDateTime($value)
    {
        // If the value is numeric, we will assume it's a UNIX timestamp.
        if (is_numeric($value)) {
            $value = Carbon::createFromTimestamp($value);
        }
        // If a string is given, we will pass it into a new carbon instance.
        elseif (is_string($value)) {
            $value = new Carbon($value);
        }
        // If a date object is given, we will convert it to a carbon instance.
        elseif ($value instanceof DateTime) {
            $value = Carbon::instance($value);
        }

        // Here we'll set the dates time zone to UTC. LDAP uses UTC
        // as its timezone for all dates. We will also set the
        // microseconds to 0 as LDAP does not support them.
        $value->setTimezone('UTC')->micro(0);

        switch ($this->type) {
            case 'ldap':
                $value = $this->convertDateTimeToLdapTime($value);
                break;
            case 'windows':
                $value = $this->convertDateTimeToWindows($value);
                break;
            case 'windows-int':
                $value = $this->convertDateTimeToWindowsInteger($value);
                break;
            default:
                throw new LdapRecordException("Unrecognized date type '{$this->type}'");
        }

        return $value;
    }

    /**
     * Converts the LDAP timestamp value to a Carbon instance.
     *
     * @param mixed $value
     *
     * @throws LdapRecordException
     *
     * @return Carbon|bool
     */
    public function toDateTime($value)
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        switch ($this->type) {
            case 'ldap':
                $value = $this->convertLdapTimeToDateTime($value);
                break;
            case 'windows':
                $value = $this->convertWindowsTimeToDateTime($value);
                break;
            case 'windows-int':
                $value = $this->convertWindowsIntegerTimeToDateTime($value);
                break;
            default:
                throw new LdapRecordException("Unrecognized date type '{$this->type}'");
        }

        if ($value instanceof DateTime) {
            return (new Carbon())->setDateTimeFrom($value);
        }
    }

    /**
     * Converts standard LDAP timestamps to a date time object.
     *
     * @param string $value
     *
     * @return DateTime|bool
     */
    protected function convertLdapTimeToDateTime($value)
    {
        return DateTime::createFromFormat('YmdHisZ', $value);
    }

    /**
     * Converts date objects to a standard LDAP timestamp.
     *
     * @param DateTime $date
     *
     * @return string
     */
    protected function convertDateTimeToLdapTime(DateTime $date)
    {
        return $date->format('YmdHis\Z');
    }

    /**
     * Converts standard windows timestamps to a date time object.
     *
     * @param string $value
     *
     * @return DateTime|bool
     */
    protected function convertWindowsTimeToDateTime($value)
    {
        return DateTime::createFromFormat('YmdHis.0Z', $value);
    }

    /**
     * Converts date objects to a windows timestamp.
     *
     * @param DateTime $date
     *
     * @return string
     */
    protected function convertDateTimeToWindows(DateTime $date)
    {
        return $date->format('YmdHis.0\Z');
    }

    /**
     * Converts standard windows integer dates to a date time object.
     *
     * @param int $value
     *
     * @throws \Exception
     *
     * @return DateTime|bool
     */
    protected function convertWindowsIntegerTimeToDateTime($value)
    {
        // ActiveDirectory dates that contain integers may return
        // "0" when they are not set. We will validate that here.
        return $value ? (new DateTime())->setTimestamp(
            Utilities::convertWindowsTimeToUnixTime($value)
        ) : false;
    }

    /**
     * Converts date objects to a windows integer timestamp.
     *
     * @param DateTime $date
     *
     * @return float
     */
    protected function convertDateTimeToWindowsInteger(DateTime $date)
    {
        return Utilities::convertUnixTimeToWindowsTime($date->getTimestamp());
    }
}
