<?php

namespace HexagonLabsLLC\LaravelExports\Builders;

use HexagonLabsLLC\LaravelExports\Models\ExportFilter;
use HexagonLabsLLC\LaravelExports\Models\ExportLayout;
use HexagonLabsLLC\LaravelExports\Models\ExportModel;
use HexagonLabsLLC\LaravelExports\Models\ExportSort;
use HexagonLabsLLC\LaravelExports\Services\SchemaSync;
use Illuminate\Support\Facades\DB;

final class ExportLayoutBuilder
{
    protected ExportModel $exportModel;

    protected array $attributes = [];

    protected array $columns = [];

    protected array $filters = [];

    protected array $sorts = [];

    public static function for(string|ExportModel $model): static
    {
        $builder = new self;
        $builder->exportModel = $model instanceof ExportModel
            ? $model
            : app(SchemaSync::class)->ensureFresh($model);

        return $builder;
    }

    public function name(string $name): static
    {
        $this->attributes['name'] = $name;

        return $this;
    }

    public function title(string $title): static
    {
        $this->attributes['title'] = $title;

        return $this;
    }

    public function description(string $description): static
    {
        $this->attributes['description'] = $description;

        return $this;
    }

    /**
     * Add a column using the addColumns() definition shapes: a value_path
     * string or an attribute array (relation, aggregator, format, ...).
     */
    public function column(string $title, string|array $definition): static
    {
        $this->columns[$title] = $definition;

        return $this;
    }

    public function filter(string $path, string $operator, mixed $value = null, array $options = []): static
    {
        $this->filters[] = array_merge(['path' => $path, 'operator' => $operator, 'value' => $value], $options);

        return $this;
    }

    public function requestFilter(string $path, string $operator, bool $required = false, array $options = []): static
    {
        return $this->filter($path, $operator, null, array_merge(['is_request' => true, 'is_required' => $required], $options));
    }

    public function sort(string $path, string $direction = 'asc', ?int $priority = null): static
    {
        $this->sorts[] = ['path' => $path, 'direction' => $direction, 'priority' => $priority ?? count($this->sorts) + 1];

        return $this;
    }

    /**
     * Persist the layout with catalog-backed columns, filters, and sorts.
     * Paths resolve (and lazy-sync) inside the transaction, so an invalid
     * path rolls everything back.
     */
    public function save(): ExportLayout
    {
        return DB::transaction(function () {
            $layout = ExportLayout::create($this->attributes + ['export_model_id' => $this->exportModel->id]);

            $layout->addColumns($this->columns);

            foreach ($this->filters as $definition) {
                $row = $layout->resolveRelationRow($definition['path']);

                if (!empty($definition['column']) && $row->column !== $definition['column']) {
                    $row->update(['column' => $definition['column']]);
                }

                $data = array_intersect_key($definition, array_flip(['operator', 'value', 'value_type', 'logical_operator', 'is_request', 'is_required']));

                if (isset($data['value']) && is_array($data['value'])) {
                    $data['value'] = json_encode($data['value']);
                    $data['value_type'] ??= 'array';
                }

                ExportFilter::create($data + [
                    'export_layout_id' => $layout->id,
                    'export_model_relation_id' => $row->id,
                ]);
            }

            foreach ($this->sorts as $definition) {
                ExportSort::create([
                    'export_layout_id' => $layout->id,
                    'export_model_relation_id' => $layout->resolveRelationRow($definition['path'])->id,
                    'direction' => $definition['direction'],
                    'priority' => $definition['priority'],
                ]);
            }

            return $layout;
        });
    }
}
