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
}
