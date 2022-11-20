<?php

namespace LdapRecord\Models\Attributes;

class EscapedValue
{
    /**
     * The value to be escaped.
     *
     * @var string
     */
    protected $value;

    /**
     * The characters to ignore when escaping.
     *
     * @var string
     */
    protected $ignore;

    /**
     * The escape flags.
     *
     * @var int
     */
    protected $flags;

    /**
     * Constructor.
     *
     * @param string $value
     * @param string $ignore
     * @param int    $flags
     */
    public function __construct($value, $ignore = '', $flags = 0)
    {
        $this->value = (string) $value;
        $this->ignore = $ignore;
        $this->flags = $flags;
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
     * Get the escaped value.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->get();
    }

    /**
     * Get the escaped value.
     *
     * @return mixed
     */
    public function get()
    {
        return ldap_escape($this->value, $this->ignore, $this->flags);
    }

    /**
     * Get the raw (unescaped) value.
     *
     * @return mixed
     */
    public function raw()
    {
        return $this->value;
    }

    /**
     * Set the characters to exclude from being escaped.
     *
     * @param string $characters
     *
     * @return $this
     */
    public function ignore($characters)
    {
        $this->ignore = $characters;

        return $this;
    }

    /**
     * Prepare the value to be escaped for use in a distinguished name.
     *
     * @return $this
     */
    public function dn()
    {
        $this->flags = LDAP_ESCAPE_DN;

        return $this;
    }

    /**
     * Prepare the value to be escaped for use in a filter.
     *
     * @return $this
     */
    public function filter()
    {
        $this->flags = LDAP_ESCAPE_FILTER;

        return $this;
    }

    /**
     * Prepare the value to be escaped for use in a distinguished name and filter.
     *
     * @return $this
     */
    public function both()
    {
        $this->flags = LDAP_ESCAPE_FILTER + LDAP_ESCAPE_DN;

        return $this;
    }
}
