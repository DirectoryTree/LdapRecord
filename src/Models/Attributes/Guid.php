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
     * @param  string  $guid
     * @return bool
     */
    public static function isValid($guid)
    {
        return (bool) preg_match('/^([0-9a-fA-F]){8}(-([0-9a-fA-F]){4}){3}-([0-9a-fA-F]){12}$/', (string) $guid);
    }

    /**
     * Constructor.
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
     */
    public function __toString(): string
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
     * Get the encoded hexadecimal representation of the GUID string.
     *
     * @return string
     */
    public function getEncodedHex()
    {
        return '\\'.implode('\\', str_split($this->getHex(), 2));
    }

    /**
     * Get the hexadecimal representation of the GUID string.
     *
     * @return string
     */
    public function getHex()
    {
        return implode($this->getOctetSections());
    }

    /**
     * Get the octect sections of the GUID.
     *
     * @return array
     */
    protected function getOctetSections()
    {
        $sections = [];

        $guid = str_replace('-', '', $this->value);

        foreach ($this->octetSections as $section) {
            array_push($sections, $this->parseSection($guid, $section, $octet = true));
        }

        return $sections;
    }

    /**
     * Returns the string variant of a binary GUID.
     *
     * @param  string  $binary
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
     * @author Chad Sikorra <Chad.Sikorra@gmail.com>
     *
     * @see https://github.com/ldaptools/ldaptools
     *
     * @param  string  $hex  The full hex string.
     * @param  array  $sections  An array of start and length (unless octet is true, then length is always 2).
     * @param  bool  $octet  Whether this is for octet string form.
     * @return string The concatenated sections in upper-case.
     */
    protected function parseSection($hex, array $sections, $octet = false)
    {
        $parsedString = '';

        foreach ($sections as $section) {
            $start = $octet ? $section : $section[0];

            $length = $octet ? 2 : $section[1];

            $parsedString .= substr($hex, $start, $length);
        }

        return $parsedString;
    }
}
