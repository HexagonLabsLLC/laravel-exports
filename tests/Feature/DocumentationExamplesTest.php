<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

$docFiles = function (): array {
    $root = dirname(__DIR__, 2);
    $files = [$root.'/README.md', $root.'/database.md'];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/docs')) as $file) {
        if ($file->getExtension() === 'md') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
};

// Returns [opening fence line number => snippet] for every ```php block in the file.
$phpFences = function (string $file): array {
    $fences = [];
    $open = null;
    $language = '';
    $buffer = [];

    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $index => $line) {
        if (!preg_match('/^\s*```+\s*([A-Za-z0-9_+-]*)\s*$/', $line, $match)) {
            if ($open !== null) {
                $buffer[] = $line;
            }

            continue;
        }

        if ($open === null) {
            [$open, $language, $buffer] = [$index + 1, strtolower($match[1]), []];

            continue;
        }

        if ($language === 'php') {
            $fences[$open] = implode("\n", $buffer);
        }

        $open = null;
    }

    return $fences;
};

it('parses every php snippet in the documentation', function () use ($docFiles, $phpFences) {
    $root = dirname(__DIR__, 2);
    $failures = [];

    foreach ($docFiles() as $file) {
        foreach ($phpFences($file) as $line => $code) {
            // Doc snippets are usually fragments, so try the shapes a fragment can take
            // before calling one broken: a whole file, loose statements, class members
            // (with and without a body), and a bare array or expression literal.
            $attempts = [
                '<?php '.$code,
                "<?php class __c {\n".$code."\n}",
                "<?php class __c {\n".$code."\n; }",
                "<?php \$__d = [\n".$code."\n];",
            ];

            if (str_starts_with(ltrim($code), '<?php')) {
                array_unshift($attempts, $code);
            }

            $error = null;

            foreach ($attempts as $attempt) {
                try {
                    token_get_all($attempt, TOKEN_PARSE);
                    $error = null;

                    break;
                } catch (ParseError $e) {
                    $error ??= $e->getMessage();
                }
            }

            if ($error !== null) {
                $failures[] = str_replace($root.'/', '', $file).':'.$line.' - '.$error;
            }
        }
    }

    expect($failures)->toBe([]);
});

it('documents classes and static methods that exist', function () use ($docFiles) {
    $root = dirname(__DIR__, 2);
    $problems = [];

    foreach ($docFiles() as $file) {
        $relative = str_replace($root.'/', '', $file);
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $imports = [];

        foreach ($lines as $index => $line) {
            if (!preg_match('/^\s*use\s+(HexagonLabsLLC\\\\LaravelExports\\\\[A-Za-z0-9_\\\\]+)\s*;/', $line, $match)) {
                continue;
            }

            $class = $match[1];
            $imports[class_basename($class)] = $class;

            if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
                $problems[] = $relative.':'.($index + 1).' - unknown class '.$class;
            }
        }

        foreach ($lines as $index => $line) {
            preg_match_all('/\b([A-Z][A-Za-z0-9_]*)::([a-zA-Z_][A-Za-z0-9_]*)\s*\(/', $line, $matches, PREG_SET_ORDER);

            foreach ($matches as [, $short, $method]) {
                $class = $imports[$short] ?? null;

                if ($class === null || !class_exists($class) || is_subclass_of($class, Facade::class)) {
                    continue;
                }

                // Eloquent resolves create()/where()/... through __callStatic, so reflection never sees them.
                if (!method_exists($class, $method)) {
                    if (!is_subclass_of($class, Model::class)) {
                        $problems[] = $relative.':'.($index + 1).' - undefined method '.$short.'::'.$method.'()';
                    }

                    continue;
                }

                $reflection = new ReflectionMethod($class, $method);

                if (!$reflection->isPublic() || !$reflection->isStatic()) {
                    $problems[] = $relative.':'.($index + 1).' - '.$short.'::'.$method.'() is not public static';
                }
            }
        }
    }

    expect($problems)->toBe([]);
});
