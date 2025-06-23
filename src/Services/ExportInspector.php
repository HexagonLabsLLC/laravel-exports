<?php

namespace HexagonLabsLLC\LaravelExports\Services;

use HexagonLabsLLC\LaravelExports\Helpers\ModelRelationInspector;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

class ExportInspector
{
    protected ModelRelationInspector $inspector;

    public function __construct(?ModelRelationInspector $inspector = null)
    {
        $this->inspector = $inspector ?: new ModelRelationInspector;
    }

    /**
     * Sync all export_model_relations rows: fill related_model_id & is_collection.
     */
    public function syncModelRelations(): void
    {
        // 1) Load all models
        $models = DB::table('export_models')->get();

        foreach ($models as $model) {
            $modelClass = $model->model;

            // 2) Fetch its configured relation‐rows
            $relations = DB::table('export_model_relations')
                ->where('export_model_id', $model->id)
                ->get();

            foreach ($relations as $row) {
                if ($row->is_column) {
                    // simple column check
                    $columns = $this->inspector->getModelColumns($modelClass);
                    if (! in_array($row->relation, $columns, true)) {
                        throw new \RuntimeException("Column {$row->relation} not found on {$modelClass}");
                    }
                    // No related_model_id or collection
                    DB::table('export_model_relations')
                        ->where('id', $row->id)
                        ->update([
                            'related_model_id' => null,
                            'is_collection' => false,
                        ]);

                    continue;
                }

                // 3) Resolve a dot‑notation relationship path
                [$relationType, $relatedModel] = $this->resolveRelationPath($modelClass, $row->relation);

                // 4) Find the matching export_model for that related model, if any
                $relatedModelId = DB::table('export_models')
                    ->where('model', $relatedModel)
                    ->value('id');

                // 5) Determine if this type returns a collection
                $isCollection = in_array($relationType, [
                    'HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany', 'MorphedByMany',
                ], true);

                // 6) Persist back to your export_model_relations
                DB::table('export_model_relations')
                    ->where('id', $row->id)
                    ->update([
                        'related_model_id' => $relatedModelId,
                        'is_collection' => $isCollection,
                    ]);
            }
        }
    }

    /**
     * Walks “Dot.Notation” through Eloquent relations to the final Relation object.
     *
     * @param  string  $modelClass  e.g. App\Models\WorkOrder
     * @param  string  $path  e.g. contact.org_name
     * @return array [RelationShortName, FullyQualifiedRelatedModelClass]
     *
     * @throws \RuntimeException
     */
    protected function resolveRelationPath(string $modelClass, string $path): array
    {
        $segments = explode('.', $path);
        $currentClass = $modelClass;
        $relation = null;

        foreach ($segments as $method) {
            if (! method_exists($currentClass, $method)) {
                throw new \RuntimeException("Method {$method} not found on {$currentClass}");
            }

            $model = new $currentClass;
            $return = $model->$method();

            if (! $return instanceof Relation) {
                throw new \RuntimeException("{$method} on {$currentClass} is not an Eloquent relation");
            }

            $relation = $return;
            $relatedModel = get_class($return->getRelated());
            $currentClass = $relatedModel;
        }

        $shortName = (new ReflectionClass($relation))->getShortName();

        return [$shortName, $relatedModel];
    }
}
