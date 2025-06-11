<?php

namespace Hexlabs\LaravelExports\Casts;

use Hexlabs\LaravelExports\Enums\OperatorType;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class OperatorTypeCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        return OperatorType::getOperator($value);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof OperatorType) {
            return $value->value;
        }

        return OperatorType::getOperator($value)->value;
    }
}
