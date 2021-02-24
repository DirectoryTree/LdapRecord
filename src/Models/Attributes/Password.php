<?php

namespace LdapRecord\Models\Attributes;

use InvalidArgumentException;
use LdapRecord\LdapRecordException;

class Password
{
    /**
     * Make an encoded password for transmission over LDAP.
     *
     * @param string $password
     *
     * @return string
     */
    public static function encode($password)
    {
        return iconv('UTF-8', 'UTF-16LE', '"'.$password.'"');
    }

    /**
     * Make a salted md5 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function smd5($password, $salt = null)
    {
        if(!$salt) $salt = random_bytes(4);
        return '{SMD5}'.static::makeHash($password, 'md5', null, $salt);
    }

    /**
     * Make a salted SSHA512 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function ssha512($password, $salt = null)
    {
        if(!$salt) $salt = random_bytes(4);
        return '{SSHA512}'.static::makeHash($password, 'hash', 'sha512', $salt);
    }

    /**
     * Make a salted SHA password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function ssha($password, $salt = null)
    {
        if(!$salt) $salt = random_bytes(4);
        return '{SSHA}'.static::makeHash($password, 'sha1', null, $salt);
    }

    /**
     * Make a salted SSHA256 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function ssha256($password, $salt = null)
    {
        if(!$salt) $salt = random_bytes(4);
        return '{SSHA256}'.static::makeHash($password, 'hash', 'sha256', $salt);
    }

    /**
     * Make a salted SSHA384 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function ssha384($password, $salt = null)
    {
        if(!$salt) $salt = random_bytes(4);
        return '{SSHA384}'.static::makeHash($password, 'hash', 'sha384', $salt);
    }

    /**
     * Make a non-salted SHA password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function sha($password)
    {
        return '{SHA}'.static::makeHash($password, 'sha1');
    }

    /**
     * Make a non-salted SHA256 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function sha256($password)
    {
        return '{SHA256}'.static::makeHash($password, 'hash', 'sha256');
    }

    /**
     * Make a non-salted SHA384 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function sha384($password)
    {
        return '{SHA384}'.static::makeHash($password, 'hash', 'sha384');
    }

    /**
     * Make a non-salted SHA512 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function sha512($password)
    {
        return '{SHA512}'.static::makeHash($password, 'hash', 'sha512');
    }

    /**
     * Make a non-salted md5 password.
     *
     * @param string $password
     *
     * @return string
     */
    public static function md5($password)
    {
        return '{MD5}'.static::makeHash($password, 'md5');
    }
    
    /**
     * Crypt password with SHA512
     *
     * @param string $password Password to encrypt
     * @param string $salt Salt
     * 
     * @return string
     */
    public static function md5crypt($password, $salt = null)  : string
    {
        if(!$salt) $salt = self::makeCryptSalt(1);
        return '{CRYPT}' . crypt($password, $salt);
    }

    /**
     * Crypt password with SHA512
     *
     * @param string $password Password to encrypt
     * @param string $salt Salt
     * 
     * @return string
     */
    public static function sha256crypt($password, $salt = null) : string
    {
        if(!$salt) $salt = self::makeCryptSalt(5);
        return '{CRYPT}' . crypt($password, $salt);
    }

    /**
     * Crypt password with SHA512
     *
     * @param string $password Password to encrypt
     * @param string $salt Salt
     * 
     * @return string
     */
    public static function sha512crypt($password, $salt = null) : string
    {
        if(!$salt) $salt = self::makeCryptSalt(6);
        return '{CRYPT}' . crypt($password, $salt);
    }

    /**
     * Make a new password hash.
     *
     * @param string $password The password to make a hash of
     * @param string $method The hash function to use
     * @param string|null $algo The algorithm to use for hashing
     * @param string|null $salt Salt for encrytion
     *
     * @return string
     */
    protected static function makeHash($password, $method, $algo = null, $salt = null)
    {
        $params = $algo ? [$algo, $password.$salt] : [$password.$salt];

        return base64_encode(pack('H*', call_user_func($method, ...$params)).$salt);
    }

    /**
     * Create special salt for crypt() method
     *
     * @param integer $algo Crypt alogorythem
     * 
     * @return string Returns salt with prefix
     */
    private static function makeCryptSalt(string $type) : string
    {
        switch($type){
            case "1":
                $salt = '$1$';
                $len = 12;
            break;
            case "5": 
                $salt = '$5$';
                $len = 16;
            break;
            case "6":
                $salt = '$6$';
                $len = 16;
            break;
            default:
                throw new InvalidArgumentException("Invalid crypt type");
        }

        $str = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        while(strlen($salt) <  $len) {
            $salt .= substr($str, random_int(0, strlen($str) - 1), 1);
        }

        return $salt;
    }

    /**
     * Get salt from encrypted password
     *
     * @return null|string return string if password is saled
     */
    public static function getSalt($encryptedPassword)
    {
        // crypt() methods
        if(preg_match('/^\{(\w+)\}(\$.*\$).*$/', $encryptedPassword, $mc)){
            return $mc[2];
        }

        // All other methods
        if (preg_match('/{([^}]+)}(.*)/', $encryptedPassword, $mc)) {
            return substr(base64_decode($mc[2]), -4);
        }

        throw new LdapRecordException("Could not extract salt from encrypted password");
    }

    /**
     * Method is using salt for encryption.
     *
     * @param string $method Method name
     * 
     * @return boolean
     */
    public static function isSalted($method) : bool
    {
        foreach((new \ReflectionMethod(self::class, $method))->getParameters() as $parameter){
            if ($parameter->name == "salt") {
                return true;
            }
        }
        return false;
    }
}
