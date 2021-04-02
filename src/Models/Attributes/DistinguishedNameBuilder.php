<?php

namespace LdapRecord\Models\Attributes;

use LdapRecord\EscapesValues;

class DistinguishedNameBuilder
{
    use EscapesValues;

    /**
     * The components of the DN.
     *
     * @var array
     */
    protected $components = [];

    /**
     * Whether to output the DN in reverse.
     *
     * @var bool
     */
    protected $reverse = false;

    /**
     * Constructor.
     *
     * @param string|null $value
     */
    public function __construct($dn = null)
    {
        $this->components = array_map(
            [$this, 'explodeRdn'], (new DistinguishedName($dn))->components()
        );
    }

    /**
     * Prepend an RDN onto the DN.
     *
     * @param string $attribute
     * @param string $value
     *
     * @return $this
     */
    public function prepend($attribute, $value)
    {
        array_unshift($this->components, [
            $attribute, $this->escape($value)->dn(),
        ]);

        return $this;
    }

    /**
     * Append an RDN onto the DN.
     *
     * @param string $attribute
     * @param string $value
     *
     * @return $this
     */
    public function append($attribute, $value)
    {
        array_push($this->components, [
            $attribute, $this->escape($value)->dn(),
        ]);

        return $this;
    }

    /**
     * Pop an RDN off of the end of the DN.
     *
     * @param int $amount
     *
     * @return $this
     */
    public function pop($amount = 1)
    {
        array_splice($this->components, -$amount, $amount);

        return $this;
    }

    /**
     * Shift an RDN off of the beginning of the DN.
     *
     * @param int $amount
     *
     * @return $this
     */
    public function shift($amount = 1)
    {
        array_splice($this->components, 0, $amount);

        return $this;
    }

    /**
     * Whether to output the DN in reverse.
     *
     * @return $this
     */
    public function reverse()
    {
        $this->reverse = true;

        return $this;
    }

    /**
     * Get the fully qualified DN.
     *
     * @return string
     */
    public function get()
    {
        $components = $this->reverse
            ? array_reverse($this->components)
            : $this->components;

        return implode(',', array_map(
            [$this, 'makeRdn'], $components
        ));
    }

    /**
     * Explode the RDN into an attribute and value.
     *
     * @param string $rdn
     *
     * @return string
     */
    protected function explodeRdn($rdn)
    {
        return explode('=', $rdn);
    }

    /**
     * Implode the component attribute and value into an RDN.
     *
     * @param string $rdn
     *
     * @return string
     */
    protected function makeRdn(array $component)
    {
        return implode('=', $component);
    }
}
