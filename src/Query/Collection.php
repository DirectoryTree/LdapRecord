<?php

namespace LdapRecord\Query;

use Illuminate\Support\Collection as BaseCollection;
use LdapRecord\Models\Model;

class Collection extends BaseCollection
{
    /**
     * @inheritdoc
     */
    protected function valueRetriever($value)
    {
        if ($this->useAsCallable($value)) {
            /** @var callable $value */
            return $value;
        }

        return function ($item) use ($value) {
            return $item instanceof Model
                ? $item->getFirstAttribute($value)
                : data_get($item, $value);
        };
    }
}
