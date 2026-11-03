<?php

namespace LdapRecord\Models\Attributes;

use InvalidArgumentException;
use Stringable;

class Sid implements Stringable
{
    public const EVERYONE = 'S-1-1-0';

    public const SELF = 'S-1-5-10';

    /**
     * The string SID value.
     */
    protected string $value;

    /**
     * Determine if the specified SID is valid.
     */
    public static function isValid(string $sid): bool
    {
        if (! preg_match('/^S-\d-\d{1,15}(-\d{1,10}){0,15}$/i', $sid)) {
            return false;
        }

        $parts = explode('-', $sid);

        if ((int) $parts[2] > 0xFFFFFFFFFFFF) {
            return false;
        }

        foreach (array_slice($parts, 3) as $subAuthority) {
            if ((int) $subAuthority > 0xFFFFFFFF) {
                return false;
            }
        }

        return true;
    }

    /**
     * Constructor.
     *
     * @throws InvalidArgumentException
     */
    public function __construct(string $value)
    {
        if (static::isValid($value)) {
            $this->value = $value;
        } elseif ($value = $this->binarySidToString($value)) {
            $this->value = $value;
        } else {
            throw new InvalidArgumentException('Invalid Binary / String SID.');
        }
    }

    /**
     * Get the string value of the SID.
     */
    public function __toString(): string
    {
        return $this->getValue();
    }

    /**
     * Get the string value of the SID.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the binary variant of the SID.
     */
    public function getBinary(): string
    {
        $sid = explode('-', substr($this->value, 2));

        $level = (int) array_shift($sid);

        $authority = (int) array_shift($sid);

        $subAuthorities = array_map('intval', $sid);

        $params = array_merge(
            ['C2nNV*', $level, count($subAuthorities), $authority >> 32, $authority & 0xFFFFFFFF],
            $subAuthorities
        );

        return call_user_func_array('pack', $params);
    }

    /**
     * Get the string variant of a binary SID.
     */
    protected function binarySidToString(string $binary): ?string
    {
        if (trim($binary) === '') {
            return null;
        }

        // Revision - 8bit unsigned int (C1)
        // Count - 8bit unsigned int (C1)
        // Identifier authority - 48bit unsigned int, big-endian order
        $sid = @unpack('C1rev/C1count/n1high/N1id', $binary);

        if (! isset($sid['id']) || ! isset($sid['rev'])) {
            return null;
        }

        $revisionLevel = $sid['rev'];

        $identifierAuthority = ($sid['high'] << 32) | $sid['id'];

        $subs = $sid['count'] ?? 0;

        if ($subs > 15 || strlen($binary) < 8 + $subs * 4) {
            return null;
        }

        $sidHex = $subs ? bin2hex($binary) : '';

        $subAuthorities = [];

        // The sub-authorities depend on the count, so only get as
        // many as the count, regardless of data beyond it.
        for ($i = 0; $i < $subs; $i++) {
            $data = implode(array_reverse(
                str_split(
                    substr($sidHex, 16 + ($i * 8), 8),
                    2
                )
            ));

            $subAuthorities[] = hexdec($data);
        }

        // Tack on the 'S-' and glue it all together...
        return 'S-'.$revisionLevel.'-'.$identifierAuthority.implode(
            preg_filter('/^/', '-', $subAuthorities)
        );
    }
}
