<?php

namespace LdapRecord;

class Utilities
{
    /**
     * Converts a DN string into an array of RDNs.
     *
     * This will also decode hex characters into their true
     * UTF-8 representation embedded inside the DN as well.
     *
     * @param string $dn
     * @param bool   $removeAttributePrefixes
     *
     * @return array|false
     */
    public static function explodeDn($dn, $removeAttributePrefixes = true)
    {
        $dn = ldap_explode_dn($dn, ($removeAttributePrefixes ? 1 : 0));

        if (! is_array($dn)) {
            return false;
        }

        if (! array_key_exists('count', $dn)) {
            return false;
        }

        unset($dn['count']);

        foreach ($dn as $rdn => $value) {
            $dn[$rdn] = static::unescape($value);
        }

        return $dn;
    }

    /**
     * Un-escapes a hexadecimal string into its original string representation.
     *
     * @param string $value
     *
     * @return string
     */
    public static function unescape($value)
    {
        return preg_replace_callback('/\\\([0-9A-Fa-f]{2})/', function ($matches) {
            return chr(hexdec($matches[1]));
        }, $value);
    }

    /**
     * Converts a string GUID to it's hex variant.
     *
     * @param string $string
     *
     * @return string
     */
    public static function stringGuidToHex($string)
    {
        $hex = '\\'.substr($string, 6, 2).'\\'.substr($string, 4, 2).'\\'.substr($string, 2, 2).'\\'.substr($string, 0, 2);
        $hex = $hex.'\\'.substr($string, 11, 2).'\\'.substr($string, 9, 2);
        $hex = $hex.'\\'.substr($string, 16, 2).'\\'.substr($string, 14, 2);
        $hex = $hex.'\\'.substr($string, 19, 2).'\\'.substr($string, 21, 2);
        $hex = $hex.'\\'.substr($string, 24, 2).'\\'.substr($string, 26, 2).'\\'.substr($string, 28, 2).'\\'.substr($string, 30, 2).'\\'.substr($string, 32, 2).'\\'.substr($string, 34, 2);

        return $hex;
    }

    /**
     * Round a Windows timestamp down to seconds and remove
     * the seconds between 1601-01-01 and 1970-01-01.
     *
     * @param float $windowsTime
     *
     * @return float
     */
    public static function convertWindowsTimeToUnixTime($windowsTime)
    {
        return round($windowsTime / 10000000) - 11644473600;
    }

    /**
     * Convert a Unix timestamp to Windows timestamp.
     *
     * @param float $unixTime
     *
     * @return float
     */
    public static function convertUnixTimeToWindowsTime($unixTime)
    {
        return ($unixTime + 11644473600) * 10000000;
    }

    /**
     * Validates that the inserted string is an object SID.
     *
     * @param string $sid
     *
     * @return bool
     */
    public static function isValidSid($sid)
    {
        return (bool) preg_match("/^S-\d(-\d{1,10}){1,16}$/i", (string) $sid);
    }

    /**
     * Validates that the inserted string is an object GUID.
     *
     * @param string $guid
     *
     * @return bool
     */
    public static function isValidGuid($guid)
    {
        return (bool) preg_match('/^([0-9a-fA-F]){8}(-([0-9a-fA-F]){4}){3}-([0-9a-fA-F]){12}$/', (string) $guid);
    }
}
