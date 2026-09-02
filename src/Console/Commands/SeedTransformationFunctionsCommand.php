<?php

namespace HexagonLabsLLC\LaravelExports\Console\Commands;

use HexagonLabsLLC\LaravelExports\Models\ExportFunction;
use HexagonLabsLLC\LaravelExports\Services\TransformationFunctions;
use Illuminate\Console\Command;

class SeedTransformationFunctionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'export:seed-functions 
                            {--force : Force re-seed existing functions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed built-in transformation functions into the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $functions = TransformationFunctions::getAvailableFunctions();
        $force = $this->option('force');

        $this->info('Seeding transformation functions...');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($functions as $functionData) {
            $existing = ExportFunction::where('callable', $functionData['callable'])->first();

            if ($existing && !$force) {
                $skipped++;
                $this->line("Skipped: {$functionData['name']} (already exists)");

                continue;
            }

            if ($existing) {
                $existing->update($functionData);
                $updated++;
                $this->info("Updated: {$functionData['name']}");
            } else {
                ExportFunction::create($functionData);
                $created++;
                $this->info("Created: {$functionData['name']}");
            }
        }

        $this->newLine();
        $this->info('Transformation functions seeding complete!');
        $this->info("Created: {$created}, Updated: {$updated}, Skipped: {$skipped}");

        // Display available functions
        $this->newLine();
        $this->info('Available transformation functions:');
        $this->table(
            ['Name', 'Callable', 'Parameters', 'Description'],
            ExportFunction::all()->map(function ($func) {
                $metadata = $func->metadata ?? [];

                return [
                    $func->name,
                    class_basename($func->callable),
                    implode(', ', $metadata['parameters'] ?? []),
                    $metadata['description'] ?? '',
                ];
            })
        );

        return Command::SUCCESS;
    }
}
