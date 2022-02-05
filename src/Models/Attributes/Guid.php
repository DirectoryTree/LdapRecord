<?php

namespace LdapRecord\Models\Attributes;

use InvalidArgumentException;

class Guid
{
    /**
     * The string GUID value.
     *
     * @var string
     */
    protected $value;

    /**
     * The guid structure in order by section to parse using substr().
     *
     * @author Chad Sikorra <Chad.Sikorra@gmail.com>
     *
     * @see https://github.com/ldaptools/ldaptools
     *
     * @var array
     */
    protected $guidSections = [
        [[-26, 2], [-28, 2], [-30, 2], [-32, 2]],
        [[-22, 2], [-24, 2]],
        [[-18, 2], [-20, 2]],
        [[-16, 4]],
        [[-12, 12]],
    ];

    /**
     * The hexadecimal octet order based on string position.
     *
     * @author Chad Sikorra <Chad.Sikorra@gmail.com>
     *
     * @see https://github.com/ldaptools/ldaptools
     *
     * @var array
     */
    protected $octetSections = [
        [6, 4, 2, 0],
        [10, 8],
        [14, 12],
        [16, 18, 20, 22, 24, 26, 28, 30],
    ];

    /**
     * Determines if the specified GUID is valid.
     *
     * @param string $guid
     *
     * @return bool
     */
    public static function isValid($guid)
    {
        return (bool) preg_match('/^([0-9a-fA-F]){8}(-([0-9a-fA-F]){4}){3}-([0-9a-fA-F]){12}$/', (string) $guid);
    }

    /**
     * Constructor.
     *
     * @param mixed $value
     *
     * @throws InvalidArgumentException
     */
    public function __construct($value)
    {
        if (static::isValid($value)) {
            $this->value = $value;
        } elseif ($value = $this->binaryGuidToString($value)) {
            $this->value = $value;
        } else {
            throw new InvalidArgumentException('Invalid Binary / String GUID.');
        }
    }

    /**
     * Returns the string value of the GUID.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getValue();
    }

    /**
     * Returns the string value of the SID.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Get the binary representation of the GUID string.
     *
     * @return string
     */
    public function getBinary()
    {
        return hex2bin($this->getHex());
    }

    /**
     * Get the hexadecimal representation of the GUID string.
     *
     * @return string
     */
    public function getHex()
    {
        return $this->makeHex();
    }

    /**
     * Get the encoded, hexadecimal representation of the GUID string.
     *
     * @return string
     */
    public function getEncodedHex()
    {
        return $this->makeHex(true);
    }

    /**
     * Make a representation of the current GUID value.
     *
     * @param bool $encoded
     *
     * @return string
     */
    protected function makeHex($encoded = false)
    {
        $guid = str_replace('-', '', $this->value);

        return array_reduce($this->octetSections, function ($carry, $section) use ($guid, $encoded) {
            return $carry .= $this->parseSection($guid, $section, $encoded);
        }, '');
    }

    /**
     * Returns the string variant of a binary GUID.
     *
     * @param string $binary
     *
     * @return string|null
     */
    protected function binaryGuidToString($binary)
    {
        if (is_null($binary) || trim($binary) == '') {
            return;
        }

        $hex = unpack('H*hex', $binary)['hex'];

        $hex1 = substr($hex, -26, 2).substr($hex, -28, 2).substr($hex, -30, 2).substr($hex, -32, 2);
        $hex2 = substr($hex, -22, 2).substr($hex, -24, 2);
        $hex3 = substr($hex, -18, 2).substr($hex, -20, 2);
        $hex4 = substr($hex, -16, 4);
        $hex5 = substr($hex, -12, 12);

        return sprintf('%s-%s-%s-%s-%s', $hex1, $hex2, $hex3, $hex4, $hex5);
    }

    /**
     * Return the specified section of the hexadecimal string.
     *
     * @param string $hex
     * @param array  $sections
     * @param bool   $encoded
     *
     * @return string The concatenated sections in upper-case.
     */
    protected function parseSection($hex, array $sections, $encoded = false)
    {
        $parsedString = '';

        foreach ($sections as $section) {
            $parsedString .= ($encoded ? '\\' : '').substr($hex, $section, $length = 2);
        }

        return $parsedString;
    }
}
