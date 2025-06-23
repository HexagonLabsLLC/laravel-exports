<?php

namespace HexagonLabsLLC\LaravelExports\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class TransformationFunctions
{
    /**
     * Get all available transformation functions
     */
    public static function getAvailableFunctions(): array
    {
        return [
            // Date/Time Functions
            [
                'name' => 'Format Date',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::formatDate',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Format a date using a specified format',
                    'parameters' => ['date', 'format'],
                    'example' => 'formatDate($date, "Y-m-d")',
                ],
            ],
            [
                'name' => 'Format Date Human',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::formatDateHuman',
                'parameter_count' => 1,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Format a date in human-readable format (e.g., "2 hours ago")',
                    'parameters' => ['date'],
                    'example' => 'formatDateHuman($date)',
                ],
            ],
            [
                'name' => 'Date Difference',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::dateDifference',
                'parameter_count' => 3,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Calculate difference between two dates',
                    'parameters' => ['date1', 'date2', 'unit'],
                    'example' => 'dateDifference($date1, $date2, "days")',
                ],
            ],

            // String Functions
            [
                'name' => 'Uppercase',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::uppercase',
                'parameter_count' => 1,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Convert string to uppercase',
                    'parameters' => ['string'],
                    'example' => 'uppercase($string)',
                ],
            ],
            [
                'name' => 'Lowercase',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::lowercase',
                'parameter_count' => 1,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Convert string to lowercase',
                    'parameters' => ['string'],
                    'example' => 'lowercase($string)',
                ],
            ],
            [
                'name' => 'Title Case',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::titleCase',
                'parameter_count' => 1,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Convert string to title case',
                    'parameters' => ['string'],
                    'example' => 'titleCase($string)',
                ],
            ],
            [
                'name' => 'Truncate',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::truncate',
                'parameter_count' => 3,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Truncate string to specified length',
                    'parameters' => ['string', 'length', 'suffix'],
                    'example' => 'truncate($string, 50, "...")',
                ],
            ],
            [
                'name' => 'Slug',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::slug',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Convert string to URL-friendly slug',
                    'parameters' => ['string', 'separator'],
                    'example' => 'slug($string, "-")',
                ],
            ],
            [
                'name' => 'Replace',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::replace',
                'parameter_count' => 3,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Replace occurrences in string',
                    'parameters' => ['string', 'search', 'replace'],
                    'example' => 'replace($string, "old", "new")',
                ],
            ],
            [
                'name' => 'Extract',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::extract',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Extract substring using regex pattern',
                    'parameters' => ['string', 'pattern'],
                    'example' => 'extract($string, "/[0-9]+/")',
                ],
            ],

            // Number Functions
            [
                'name' => 'Format Number',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::formatNumber',
                'parameter_count' => 3,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Format number with decimals and thousands separator',
                    'parameters' => ['number', 'decimals', 'thousands_separator'],
                    'example' => 'formatNumber($number, 2, ",")',
                ],
            ],
            [
                'name' => 'Format Currency',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::formatCurrency',
                'parameter_count' => 3,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Format number as currency',
                    'parameters' => ['number', 'currency', 'locale'],
                    'example' => 'formatCurrency($number, "USD", "en_US")',
                ],
            ],
            [
                'name' => 'Round',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::round',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Round number to specified decimals',
                    'parameters' => ['number', 'decimals'],
                    'example' => 'round($number, 2)',
                ],
            ],
            [
                'name' => 'Percentage',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::percentage',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Format number as percentage',
                    'parameters' => ['number', 'decimals'],
                    'example' => 'percentage($number, 2)',
                ],
            ],

            // Boolean Functions
            [
                'name' => 'Boolean Text',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::booleanText',
                'parameter_count' => 3,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Convert boolean to custom text',
                    'parameters' => ['value', 'true_text', 'false_text'],
                    'example' => 'booleanText($value, "Yes", "No")',
                ],
            ],

            // Array/JSON Functions
            [
                'name' => 'JSON Extract',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::jsonExtract',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Extract value from JSON using dot notation',
                    'parameters' => ['json', 'path'],
                    'example' => 'jsonExtract($json, "user.name")',
                ],
            ],
            [
                'name' => 'Array Join',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::arrayJoin',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Join array elements with separator',
                    'parameters' => ['array', 'separator'],
                    'example' => 'arrayJoin($array, ", ")',
                ],
            ],
            [
                'name' => 'Array Count',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::arrayCount',
                'parameter_count' => 1,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Count array elements',
                    'parameters' => ['array'],
                    'example' => 'arrayCount($array)',
                ],
            ],

            // Utility Functions
            [
                'name' => 'Default Value',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::defaultValue',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Return default if value is empty',
                    'parameters' => ['value', 'default'],
                    'example' => 'defaultValue($value, "N/A")',
                ],
            ],
            [
                'name' => 'Concatenate',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::concatenate',
                'parameter_count' => 3,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Concatenate values with separator',
                    'parameters' => ['value1', 'value2', 'separator'],
                    'example' => 'concatenate($value1, $value2, " - ")',
                ],
            ],
            [
                'name' => 'Hash',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::hash',
                'parameter_count' => 2,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Hash value using specified algorithm',
                    'parameters' => ['value', 'algorithm'],
                    'example' => 'hash($value, "sha256")',
                ],
            ],
            [
                'name' => 'Mask',
                'callable' => 'HexagonLabsLLC\LaravelExports\Services\TransformationFunctions::mask',
                'parameter_count' => 3,
                'value_parameter_index' => 0,
                'metadata' => [
                    'description' => 'Mask part of string (e.g., for sensitive data)',
                    'parameters' => ['string', 'visible_chars', 'mask_char'],
                    'example' => 'mask($string, 4, "*")',
                ],
            ],
        ];
    }

    // Date/Time Functions
    public static function formatDate($date, $format = 'Y-m-d H:i:s')
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return $date;
        }
    }

    public static function formatDateHuman($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->diffForHumans();
        } catch (\Exception $e) {
            return $date;
        }
    }

    public static function dateDifference($date1, $date2 = null, $unit = 'days')
    {
        if (empty($date1)) {
            return null;
        }

        try {
            $carbon1 = Carbon::parse($date1);
            $carbon2 = $date2 ? Carbon::parse($date2) : Carbon::now();

            return match ($unit) {
                'seconds' => $carbon1->diffInSeconds($carbon2),
                'minutes' => $carbon1->diffInMinutes($carbon2),
                'hours' => $carbon1->diffInHours($carbon2),
                'days' => $carbon1->diffInDays($carbon2),
                'weeks' => $carbon1->diffInWeeks($carbon2),
                'months' => $carbon1->diffInMonths($carbon2),
                'years' => $carbon1->diffInYears($carbon2),
                default => $carbon1->diffInDays($carbon2),
            };
        } catch (\Exception $e) {
            return null;
        }
    }

    // String Functions
    public static function uppercase($string)
    {
        return Str::upper($string);
    }

    public static function lowercase($string)
    {
        return Str::lower($string);
    }

    public static function titleCase($string)
    {
        return Str::title($string);
    }

    public static function truncate($string, $length = 50, $suffix = '...')
    {
        return Str::limit($string, $length, $suffix);
    }

    public static function slug($string, $separator = '-')
    {
        return Str::slug($string, $separator);
    }

    public static function replace($string, $search, $replace)
    {
        return str_replace($search, $replace, $string);
    }

    public static function extract($string, $pattern)
    {
        preg_match($pattern, $string, $matches);

        return $matches[0] ?? null;
    }

    // Number Functions
    public static function formatNumber($number, $decimals = 2, $thousandsSeparator = ',')
    {
        if (! is_numeric($number)) {
            return $number;
        }

        return number_format($number, $decimals, '.', $thousandsSeparator);
    }

    public static function formatCurrency($number, $currency = 'USD', $locale = 'en_US')
    {
        if (! is_numeric($number)) {
            return $number;
        }

        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($number, $currency);
    }

    public static function round($number, $decimals = 0)
    {
        if (! is_numeric($number)) {
            return $number;
        }

        return round($number, $decimals);
    }

    public static function percentage($number, $decimals = 2)
    {
        if (! is_numeric($number)) {
            return $number;
        }

        return number_format($number * 100, $decimals).'%';
    }

    // Boolean Functions
    public static function booleanText($value, $trueText = 'Yes', $falseText = 'No')
    {
        return $value ? $trueText : $falseText;
    }

    // Array/JSON Functions
    public static function jsonExtract($json, $path)
    {
        if (is_string($json)) {
            $json = json_decode($json, true);
        }

        return data_get($json, $path);
    }

    public static function arrayJoin($array, $separator = ', ')
    {
        if (! is_array($array) && ! ($array instanceof \Traversable)) {
            return $array;
        }

        return collect($array)->implode($separator);
    }

    public static function arrayCount($array)
    {
        if (! is_array($array) && ! ($array instanceof \Countable)) {
            return 0;
        }

        return count($array);
    }

    // Utility Functions
    public static function defaultValue($value, $default = '')
    {
        return empty($value) ? $default : $value;
    }

    public static function concatenate($value1, $value2, $separator = ' ')
    {
        return $value1.$separator.$value2;
    }

    public static function hash($value, $algorithm = 'sha256')
    {
        return hash($algorithm, $value);
    }

    public static function mask($string, $visibleChars = 4, $maskChar = '*')
    {
        if (empty($string)) {
            return $string;
        }

        $length = strlen($string);
        if ($length <= $visibleChars) {
            return $string;
        }

        $masked = substr($string, 0, $visibleChars);
        $masked .= str_repeat($maskChar, $length - $visibleChars);

        return $masked;
    }
}
