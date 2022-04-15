<?php

namespace LdapRecord\Models\Concerns;

trait SerializesAndRestoresPropertyValues
{
    /**
     * Get the property value prepared for serialization.
     *
     * @param string $property
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function getSerializedPropertyValue($property, $value)
    {
        if ($property === 'original') {
            return $this->originalToArray();
        }

        if ($property === 'attributes') {
            return $this->attributesToArray();
        }

        return $value;
    }

    /**
     * Get the restored property value after deserialization.
     *
     * @param string $property
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function getRestoredPropertyValue($property, $value)
    {
        return $value;
    }
}
