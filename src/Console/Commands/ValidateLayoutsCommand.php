<?php

namespace HexagonLabsLLC\LaravelExports\Console\Commands;

use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Services\LayoutValidator;
use Illuminate\Console\Command;

class ValidateLayoutsCommand extends Command
{
    protected $signature = 'export:validate {--layout= : Layout id or name}';

    protected $description = 'Validate export layout configurations';

    public function handle(LayoutValidator $validator): int
    {
        $query = ExportLayout::query();

        if ($target = $this->option('layout')) {
            $query->where(fn ($q) => $q->where('id', $target)->orWhere('name', $target));
        }

        $layouts = $query->get();

        if ($layouts->isEmpty()) {
            $this->error($target ? "Layout '{$target}' not found." : 'No layouts found.');

            return $target ? self::FAILURE : self::SUCCESS;
        }

        $errors = 0;
        $warnings = 0;

        foreach ($layouts as $layout) {
            $problems = $validator->validate($layout);

            if ($problems === []) {
                continue;
            }

            $this->info("Layout: {$layout->name}");
            $this->table(
                ['Severity', 'Source', 'Message'],
                array_map(fn ($problem) => [$problem['severity'], $problem['source'], $problem['message']], $problems)
            );

            foreach ($problems as $problem) {
                $problem['severity'] === 'error' ? $errors++ : $warnings++;
            }
        }

        $this->line(sprintf('%d layouts checked, %d errors, %d warnings', $layouts->count(), $errors, $warnings));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
