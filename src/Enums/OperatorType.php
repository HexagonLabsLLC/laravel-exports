<?php

namespace Hexlabs\LaravelExports\Enums;

use Illuminate\Database\Query\Builder;
use ReflectionMethod;

enum OperatorType: string
{
    case EQUALS = '=';
    case NOT_EQUALS = '!=';
    case GREATER_THAN = '>';
    case LESS_THAN = '<';
    case GREATER_THAN_OR_EQUAL = '>=';
    case LESS_THAN_OR_EQUAL = '<=';
    case IN = 'in';
    case NOT_IN = 'not_in';
    case BETWEEN = 'between';
    case LIKE = 'like';
    case NULL = 'null';
    case NOT_NULL = 'not_null';
    case JSON_CONTAINS = 'json_contains';
    case RELATION = 'relation';

    public static function getOperator(string $operator): self
    {
        return match ($operator) {
            '=', 'equals' => self::EQUALS,
            '!=', 'not_equals' => self::NOT_EQUALS,
            '>', 'greater_than' => self::GREATER_THAN,
            '<', 'less_than' => self::LESS_THAN,
            '>=', 'greater_than_or_equal' => self::GREATER_THAN_OR_EQUAL,
            '<=', 'less_than_or_equal' => self::LESS_THAN_OR_EQUAL,
            'in' => self::IN,
            'not_in' => self::NOT_IN,
            'between' => self::BETWEEN,
            'like' => self::LIKE,
            'null' => self::NULL,
            'not_null' => self::NOT_NULL,
            'json_contains' => self::JSON_CONTAINS,
            'relation' => self::RELATION,
            default => throw new \InvalidArgumentException("Invalid operator: $operator"),
        };
    }

    public static function getCallable(string $operator, bool $or = false): ?string
    {
        return match ($operator) {
            '=', 'equals' => $or ? 'orWhere' : 'where',
            '!=', 'not_equals' => $or ? 'orWhere' : 'where',
            '>', 'greater_than' => $or ? 'orWhere' : 'where',
            '<', 'less_than' => $or ? 'orWhere' : 'where',
            '>=', 'greater_than_or_equal' => $or ? 'orWhere' : 'where',
            '<=', 'less_than_or_equal' => $or ? 'orWhere' : 'where',
            'in' => $or ? 'orWhereIn' : 'whereIn',
            'not_in' => $or ? 'orWhereNotIn' : 'whereNotIn',
            'between' => $or ? 'orWhereBetween' : 'whereBetween',
            'like' => $or ? 'orWhereLike' : 'whereLike',
            'null' => $or ? 'orWhereNull' : 'whereNull',
            'not_null' => $or ? 'orWhereNotNull' : 'whereNotNull',
            'json_contains' => $or ? 'orWhereJsonContains' : 'whereJsonContains',
            'relation' => $or ? 'orWhereRelation' : 'whereRelation',
            default => null,
        };
    }

    public static function getCallableArguments(string $callable): array
    {
        $reflection = new ReflectionMethod('\\Illuminate\\Database\\Query\\Builder\\'.$callable);

        $parameters = $reflection->getParameters();

        return array_map(function ($parameter) {
            return $parameter->getName();
        }, $parameters);
    }

    public static function builder(
        Builder $query,
        bool $or,
        string $operator,
        ...$args
    ): Builder {
        return call_user_func_array(
            [$query, self::getCallable($operator, $or)],
            $args
        );
    }
}
