<?php

namespace LdapRecord\Models\Concerns;

trait SerializesProperties
{
    use SerializesAndRestoresPropertyValues;

    /**
     * Prepare the instance values for serialization.
     *
     * @return array
     */
    public function __serialize()
    {
        $values = [];

        foreach (get_object_vars($this) as $property => $value) {
            $values[$property] = $this->getSerializedPropertyValue($property, $value);
        }

        return $values;
    }

    /**
     * Restore the instance values after deserialization.
     *
     * @param array $values
     *
     * @return void
     */
    public function __unserialize(array $values)
    {
        array_walk($values, function ($value, $property) {
            $this->{$property} = $this->getUnserializedPropertyValue($property, $value);
        });
    }
}
