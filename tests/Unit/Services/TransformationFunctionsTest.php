<?php

use Carbon\Carbon;
use HexagonLabsLLC\LaravelExports\Services\TransformationFunctions;

beforeEach(function () {
    Carbon::setTestNow('2025-01-15 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

// Date/Time Functions Tests
test('formats date correctly', function () {
    $date = '2025-01-15 10:30:45';

    expect(TransformationFunctions::formatDate($date, 'Y-m-d'))->toBe('2025-01-15');
    expect(TransformationFunctions::formatDate($date, 'd/m/Y'))->toBe('15/01/2025');
    expect(TransformationFunctions::formatDate($date, 'H:i'))->toBe('10:30');
    expect(TransformationFunctions::formatDate(null))->toBeNull();
    expect(TransformationFunctions::formatDate('invalid'))->toBe('invalid');
});

test('formats timestamp with timezone', function () {
    $date = '2025-01-15 15:00:00'; // Assumed UTC

    expect(TransformationFunctions::formatTimestamp($date, 'Y-m-d H:i:s', 'America/New_York'))->toBe('2025-01-15 10:00:00');
    expect(TransformationFunctions::formatTimestamp($date, 'Y-m-d H:i:s', 'Europe/London'))->toBe('2025-01-15 15:00:00');
    expect(TransformationFunctions::formatTimestamp($date, 'H:i', 'Asia/Tokyo'))->toBe('00:00');
    expect(TransformationFunctions::formatTimestamp($date, 'Y-m-d H:i:s', 'UTC'))->toBe('2025-01-15 15:00:00');
    expect(TransformationFunctions::formatTimestamp(null))->toBeNull();
    expect(TransformationFunctions::formatTimestamp('invalid', 'Y-m-d', 'UTC'))->toBe('invalid');
});

test('formats date in human readable format', function () {
    $date = '2025-01-15 09:00:00';

    expect(TransformationFunctions::formatDateHuman($date))->toBe('1 hour ago');
    expect(TransformationFunctions::formatDateHuman('2025-01-14 10:00:00'))->toBe('1 day ago');
    expect(TransformationFunctions::formatDateHuman(null))->toBeNull();
});

test('calculates date difference', function () {
    $date1 = '2025-01-10 10:00:00';
    $date2 = '2025-01-15 10:00:00';

    expect(TransformationFunctions::dateDifference($date1, $date2, 'days'))->toEqual(5);
    expect(TransformationFunctions::dateDifference($date1, $date2, 'hours'))->toEqual(120);
    expect(TransformationFunctions::dateDifference($date1, null, 'days'))->toEqual(5); // Uses current time
    expect(TransformationFunctions::dateDifference(null))->toBeNull();
});

// String Functions Tests
test('converts string cases', function () {
    $string = 'Hello World';

    expect(TransformationFunctions::uppercase($string))->toBe('HELLO WORLD');
    expect(TransformationFunctions::lowercase($string))->toBe('hello world');
    expect(TransformationFunctions::titleCase('hello world'))->toBe('Hello World');
});

test('truncates string', function () {
    $string = 'This is a very long string that needs to be truncated';

    expect(TransformationFunctions::truncate($string, 20))->toBe('This is a very long...');
    expect(TransformationFunctions::truncate($string, 20, '…'))->toBe('This is a very long…');
    expect(TransformationFunctions::truncate('short', 10))->toBe('short');
});

test('creates slug from string', function () {
    expect(TransformationFunctions::slug('Hello World!'))->toBe('hello-world');
    expect(TransformationFunctions::slug('Hello World!', '_'))->toBe('hello_world');
});

test('replaces text in string', function () {
    $string = 'Hello World';

    expect(TransformationFunctions::replace($string, 'World', 'PHP'))->toBe('Hello PHP');
    expect(TransformationFunctions::replace($string, 'o', 'a'))->toBe('Hella Warld');
});

test('extracts text using regex', function () {
    expect(TransformationFunctions::extract('Order #12345', '/[0-9]+/'))->toBe('12345');
    expect(TransformationFunctions::extract('No numbers here', '/[0-9]+/'))->toBeNull();
});

// Number Functions Tests
test('formats numbers', function () {
    expect(TransformationFunctions::formatNumber(1234.567, 2))->toBe('1,234.57');
    expect(TransformationFunctions::formatNumber(1234.567, 0))->toBe('1,235');
    expect(TransformationFunctions::formatNumber(1234.567, 2, '.'))->toBe('1.234.57');
    expect(TransformationFunctions::formatNumber('not a number'))->toBe('not a number');
});

test('formats currency', function () {
    expect(TransformationFunctions::formatCurrency(1234.56, 'USD', 'en_US'))->toBe('$1,234.56');
    expect(TransformationFunctions::formatCurrency(1234.56, 'EUR', 'de_DE'))->toContain('€');
    expect(TransformationFunctions::formatCurrency('not a number'))->toBe('not a number');
});

test('rounds numbers', function () {
    expect(TransformationFunctions::round(3.14159, 2))->toBe(3.14);
    expect(TransformationFunctions::round(3.14159, 0))->toBe(3.0);
    expect(TransformationFunctions::round('not a number'))->toBe('not a number');
});

test('formats percentage', function () {
    expect(TransformationFunctions::percentage(0.1234, 2))->toBe('12.34%');
    expect(TransformationFunctions::percentage(0.5, 0))->toBe('50%');
    expect(TransformationFunctions::percentage('not a number'))->toBe('not a number');
});

// Boolean Functions Tests
test('converts boolean to text', function () {
    expect(TransformationFunctions::booleanText(true))->toBe('Yes');
    expect(TransformationFunctions::booleanText(false))->toBe('No');
    expect(TransformationFunctions::booleanText(true, 'Active', 'Inactive'))->toBe('Active');
    expect(TransformationFunctions::booleanText(false, 'Active', 'Inactive'))->toBe('Inactive');
});

// Array/JSON Functions Tests
test('extracts from JSON', function () {
    $json = '{"user": {"name": "John", "email": "john@example.com"}}';
    $array = ['user' => ['name' => 'John', 'email' => 'john@example.com']];

    expect(TransformationFunctions::jsonExtract($json, 'user.name'))->toBe('John');
    expect(TransformationFunctions::jsonExtract($array, 'user.email'))->toBe('john@example.com');
    expect(TransformationFunctions::jsonExtract($json, 'user.phone'))->toBeNull();
});

test('joins array elements', function () {
    $array = ['apple', 'banana', 'orange'];

    expect(TransformationFunctions::arrayJoin($array))->toBe('apple, banana, orange');
    expect(TransformationFunctions::arrayJoin($array, ' | '))->toBe('apple | banana | orange');
    expect(TransformationFunctions::arrayJoin('not an array'))->toBe('not an array');
});

test('counts array elements', function () {
    expect(TransformationFunctions::arrayCount(['a', 'b', 'c']))->toBe(3);
    expect(TransformationFunctions::arrayCount([]))->toBe(0);
    expect(TransformationFunctions::arrayCount('not an array'))->toBe(0);
});

// Utility Functions Tests
test('provides default value', function () {
    expect(TransformationFunctions::defaultValue('value', 'default'))->toBe('value');
    expect(TransformationFunctions::defaultValue('', 'default'))->toBe('default');
    expect(TransformationFunctions::defaultValue(null, 'default'))->toBe('default');
    expect(TransformationFunctions::defaultValue(0, 'default'))->toBe('default');
});

test('concatenates values', function () {
    expect(TransformationFunctions::concatenate('Hello', 'World'))->toBe('Hello World');
    expect(TransformationFunctions::concatenate('Hello', 'World', ', '))->toBe('Hello, World');
});

test('hashes values', function () {
    $value = 'test';

    expect(TransformationFunctions::hash($value))->toBe(hash('sha256', $value));
    expect(TransformationFunctions::hash($value, 'md5'))->toBe(hash('md5', $value));
});

test('masks strings', function () {
    expect(TransformationFunctions::mask('1234567890', 4))->toBe('1234******');
    expect(TransformationFunctions::mask('secret', 2, '#'))->toBe('se####');
    expect(TransformationFunctions::mask('abc', 5))->toBe('abc'); // String too short
    expect(TransformationFunctions::mask('', 4))->toBe('');
});

test('all functions are registered', function () {
    $functions = TransformationFunctions::getAvailableFunctions();

    expect($functions)->toBeArray();
    expect(count($functions))->toBeGreaterThan(20);

    // Check structure of first function
    $firstFunction = $functions[0];
    expect($firstFunction)->toHaveKeys(['name', 'callable', 'parameter_count', 'value_parameter_index', 'metadata']);
    expect($firstFunction['metadata'])->toHaveKeys(['description', 'parameters', 'example']);
});
