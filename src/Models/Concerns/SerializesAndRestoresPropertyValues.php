<?php

namespace LdapRecord\Models\Concerns;

trait SerializesAndRestoresPropertyValues
{
    /**
     * Get the property value prepared for serialization.
     *
     * @param string $property
     * @param mixed $value
     *
     * @return mixed
     */
    protected function getSerializedPropertyValue($property, $value)
    {
        if ($property === 'attributes') {
            return $this->attributesToArray();
        }

        if ($property === 'original') {
            return;
        }

        return $value;
    }

    /**
     * Get the restored property value after deserialization.
     *
     * @param string $property
     * @param mixed $value
     *
     * @return mixed
     */
    protected function getRestoredPropertyValue($property, $value)
    {
        if ($property === 'original') {
        }

        return $value;
    }

    public function restoreModel($value)
    {
        // ...
    }

    protected function getQueryForModelRestoration($model, $ids)
    {
        return $model->newQueryForRestoration($ids);
    }
}
