<?php

namespace Zola\CrudGenerator\Repositories;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic data-access layer. Per the backend convention, ALL database
 * communication lives in repositories; services pass the selection columns,
 * where conditions, eager-loads and ordering in as parameters and the
 * repository just runs the query.
 *
 * Supported `$params` keys:
 *   - with:       array<int|string,string|Closure(Builder): void>|string
 *                                              relations to eager-load; a string key
 *                                              paired with a Closure constrains that relation
 *   - where:      array<string,mixed>          equality constraints (column => value)
 *   - whereIn:    array<string,array>          IN constraints (column => values)
 *   - whereHas:   array<int|string,string|Closure(Builder): void|null>
 *                                              relation existence constraints; see
 *                                              normalizeRelationConstraint() for the accepted shapes
 *   - orWhereHas: same shape as whereHas, OR-ed against the other constraints
 *   - withWhereHas: same shape as whereHas, but also eager-loads the relation
 *                                              using the very same constraint
 *   - select:     array<int,string>|string     columns to select
 *   - orderBy:    array<string,string>         column => 'asc'|'desc'
 *   - orderByRaw: string                        raw ORDER BY expression
 *   - skip:       int                           rows to offset
 *   - take:       int                           rows to limit to
 *   - scope:      Closure(Builder): void        arbitrary extra constraints
 */
abstract class BaseRepository
{
    public function __construct(protected Model $model) {}

    /**
     * Fresh query builder for the underlying model.
     */
    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Translate caller-supplied parameters into query constraints.
     *
     * @param  array<string,mixed>  $params
     */
    protected function applyParams(Builder $query, array $params): Builder
    {
        if (isset($params['with'])) {
            $query->with($params['with']);
        }

        foreach ($params['where'] ?? [] as $column => $value) {
            $query->where($column, $value);
        }

        foreach ($params['whereIn'] ?? [] as $column => $values) {
            $query->whereIn($column, $values);
        }

        foreach ($params['whereHas'] ?? [] as $key => $value) {
            [$relation, $callback] = $this->normalizeRelationConstraint($key, $value);

            $query->whereHas($relation, $callback);
        }

        foreach ($params['orWhereHas'] ?? [] as $key => $value) {
            [$relation, $callback] = $this->normalizeRelationConstraint($key, $value);

            $query->orWhereHas($relation, $callback);
        }

        foreach ($params['withWhereHas'] ?? [] as $key => $value) {
            [$relation, $callback] = $this->normalizeRelationConstraint($key, $value);

            $query->withWhereHas($relation, $callback);
        }

        if (isset($params['select'])) {
            $query->select($params['select']);
        }

        foreach ($params['orderBy'] ?? [] as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        if (isset($params['orderByRaw'])) {
            $query->orderByRaw($params['orderByRaw']);
        }

        if (isset($params['skip'])) {
            $query->skip($params['skip']);
        }

        if (isset($params['take'])) {
            $query->take($params['take']);
        }

        if (($params['scope'] ?? null) instanceof Closure) {
            ($params['scope'])($query);
        }

        return $query;
    }

    /**
     * Translate one relation-constraint entry into a [relation, callback] pair.
     *
     * Every shape below is accepted so callers can mix constrained and plain
     * existence checks in a single array:
     *   ['projectDeal']                                 → plain existence check
     *   ['projectDeal' => null]                         → plain existence check
     *   ['projectDeal' => fn ($query) => $query->…]     → constrained existence check
     *
     * @param  string|Closure(Builder): void|null  $value
     * @return array{0:string,1:?Closure}
     */
    protected function normalizeRelationConstraint(int|string $key, string|Closure|null $value): array
    {
        if (is_int($key)) {
            return [$value, null];
        }

        return [$key, $value instanceof Closure ? $value : null];
    }

    /**
     * Fetch all matching records.
     *
     * @param  array<string,mixed>  $params
     */
    public function get(array $params = []): Collection
    {
        return $this->applyParams($this->query(), $params)->get();
    }

    /**
     * Fetch a paginated slice of matching records.
     *
     * @param  array<string,mixed>  $params
     */
    public function paginate(array $params = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->applyParams($this->query(), $params)->paginate($perPage);
    }

    /**
     * Fetch the first matching record, or null.
     *
     * @param  array<string,mixed>  $params
     */
    public function show(array $params = []): ?Model
    {
        return $this->applyParams($this->query(), $params)->first();
    }

    public function findById(string $id): ?Model
    {
        return $this->show([
            'where' => [
                'id' => $id,
            ],
        ]);
    }

    /**
     * Persist a new record.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function store(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    /**
     * Fill and persist an existing model.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model;
    }

    /**
     * Persist pending changes on a model instance.
     */
    public function save(Model $model): Model
    {
        $model->save();

        return $model;
    }

    /**
     * Delete a model instance.
     */
    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * Find a matching record or return a fresh (unsaved) instance.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function firstOrNew(array $attributes): Model
    {
        return $this->query()->firstOrNew($attributes);
    }
}
