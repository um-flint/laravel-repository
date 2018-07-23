<?php

namespace UMFlint\Repository;

use Illuminate\Contracts\Container\Container as Application;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UMFlint\Repository\Contracts\RepositoryInterface;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var ValidationFactory
     */
    protected $validation;

    /**
     * @var Builder
     */
    protected $query;


    /**
     * BaseRepository constructor.
     *
     * @param Application       $app
     * @param ValidationFactory $validation
     * @throws \Exception
     */
    public function __construct(Application $app, ValidationFactory $validation)
    {
        $this->app = $app;
        $this->validation = $validation;
        $this->query = $this->makeQuery();

        if (method_exists($this, 'boot')) {
            $this->app->call([$this, 'boot']);
        }
    }

    /**
     * Specify Model class name
     *
     * @return string
     */
    abstract public function model(): string;

    /**
     * Array of rules for validation.
     *
     * @param null $model
     * @return array
     */
    abstract public function rules($model = null): array;

    /**
     * Array of messages for validation.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Create a new instance of the model.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @return Model
     * @throws \Exception
     */
    public function makeModel(): Model
    {
        $model = $this->app->make($this->model());

        if (!$model instanceof Model) {
            throw new \Exception("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $model;
    }

    /**
     * Create a new query.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @return Builder
     * @throws \Exception
     */
    public function makeQuery(): Builder
    {
        $model = $this->app->make($this->model());

        if (!$model instanceof Model) {
            throw new \Exception("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $model->newQuery();
    }

    /**
     * Cast attributes from the model.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @param array $attributes
     * @return array
     * @throws \Exception
     */
    public function castAttributes(array $attributes): array
    {
        return $this->makeModel()->forceFill($attributes)->toArray();
    }

    /**
     * Reset the query.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @throws \Exception
     */
    public function resetQuery()
    {
        $this->query = $this->makeQuery();
    }

    /**
     * Validate attributes.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @param array $attributes
     * @param null  $model
     * @throws ValidationException
     */
    protected function passesOrFailsValidation(array $attributes, $model = null)
    {
        $validator = $this->validation->make($attributes, $this->rules($model), $this->messages());

        if (!$validator->passes()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Retrieve data array for populate field select
     *
     * @param string      $column
     * @param string|null $key
     *
     * @return \Illuminate\Support\Collection|array
     */
    public function lists($column, $key = null)
    {
        return $this->query->lists($column, $key);
    }

    /**
     * Retrieve data array for populate field select
     * Compatible with Laravel 5.3
     *
     * @param string      $column
     * @param string|null $key
     *
     * @return \Illuminate\Support\Collection|array
     */
    public function pluck($column, $key = null)
    {
        return $this->query->pluck($column, $key);
    }

    /**
     * Sync relations
     *
     * @param      $id
     * @param      $relation
     * @param      $attributes
     * @param bool $detaching
     * @return mixed
     * @throws \Exception
     */
    public function sync($id, $relation, $attributes, $detaching = true)
    {
        return $this->find($id)->{$relation}()->sync($attributes, $detaching);
    }

    /**
     * SyncWithoutDetaching
     *
     * @param $id
     * @param $relation
     * @param $attributes
     * @return mixed
     * @throws \Exception
     */
    public function syncWithoutDetaching($id, $relation, $attributes)
    {
        return $this->sync($id, $relation, $attributes, false);
    }

    /**
     * Retrieve all data of repository
     *
     * @param array $columns
     *
     * @return mixed
     * @throws \Exception
     */
    public function all($columns = ['*'])
    {

        if ($this->query instanceof Builder) {
            $results = $this->query->get($columns);
        }else {
            $results = $this->query->all($columns);
        }

        $this->resetQuery();

        return $results;
    }

    /**
     * Retrieve all data of repository, paginated
     *
     * @param null   $limit
     * @param array  $columns
     *
     * @param string $method
     * @return mixed
     * @throws \Exception
     */
    public function paginate($limit = null, $columns = ['*'], $method = 'paginate')
    {
        $limit = $limit ?? 15;
        $results = $this->query->{$method}($limit, $columns);
        $results->appends($this->app->make('request')->query());
        $this->resetQuery();

        return $results;
    }

    /**
     * Retrieve all data of repository, simple paginated
     *
     * @param null  $limit
     * @param array $columns
     *
     * @return mixed
     * @throws \Exception
     */
    public function simplePaginate($limit = null, $columns = ['*'])
    {
        return $this->paginate($limit, $columns, 'simplePaginate');
    }

    /**
     * Find data by id
     *
     * @param       $id
     * @param array $columns
     *
     * @return mixed
     * @throws \Exception
     */
    public function find($id, $columns = ['*'])
    {
        $model = $this->query->findOrFail($id, $columns);
        $this->resetQuery();

        return $model;
    }

    /**
     * Find data by field and value
     *
     * @param       $field
     * @param       $value
     * @param array $columns
     *
     * @return mixed
     * @throws \Exception
     */
    public function findByField($field, $value, $columns = ['*'])
    {
        $model = $this->query->where($field, $value)->get($columns);
        $this->resetQuery();

        return $model;
    }

    /**
     * Find data by multiple fields
     *
     * @param array $where
     * @param array $columns
     *
     * @return mixed
     * @throws \Exception
     */
    public function findWhere(array $where, $columns = ['*'])
    {
        $this->applyConditions($where);
        $model = $this->query->get($columns);
        $this->resetQuery();

        return $model;
    }

    /**
     * Find data by multiple values in one field
     *
     * @param       $field
     * @param array $values
     * @param array $columns
     *
     * @return mixed
     * @throws \Exception
     */
    public function findWhereIn($field, array $values, $columns = ['*'])
    {
        $model = $this->query->whereIn($field, $values)->get($columns);
        $this->resetQuery();

        return $model;
    }

    /**
     * Find data by excluding multiple values in one field
     *
     * @param       $field
     * @param array $values
     * @param array $columns
     *
     * @return mixed
     * @throws \Exception
     */
    public function findWhereNotIn($field, array $values, $columns = ['*'])
    {
        $model = $this->query->whereNotIn($field, $values)->get($columns);
        $this->resetQuery();

        return $model;
    }

    /**
     * Save a new entity in repository
     *
     * @param array $attributes
     *
     * @return mixed
     * @throws \Exception
     */
    public function create(array $attributes)
    {
        if (method_exists($this, 'beforeCreate')) {
            call_user_func_array([$this, 'beforeCreate'], [&$attributes]);
        }

        $attributes = $this->castAttributes($attributes);
        $this->passesOrFailsValidation($attributes);
        $model = $this->makeModel()->newInstance($attributes);
        $model->save();

        if (method_exists($this, 'afterCreate')) {
            call_user_func_array([$this, 'afterCreate'], [$model, &$attributes]);
        }

        return $model;
    }

    /**
     * Update a entity in repository by id
     *
     * @param array $attributes
     * @param       $id
     *
     * @return mixed
     * @throws \Exception
     */
    public function update(array $attributes, $id)
    {
        $model = $this->query->findOrFail($id);

        if (method_exists($this, 'beforeUpdate')) {
            call_user_func_array([$this, 'beforeUpdate'], [$model, &$attributes]);
        }

        $attributes = $this->castAttributes($attributes);
        $this->passesOrFailsValidation($attributes, $model);
        $model->fill($attributes);
        $model->save();

        if (method_exists($this, 'afterUpdate')) {
            call_user_func_array([$this, 'afterUpdate'], [$model, &$attributes]);
        }

        $this->resetQuery();

        return $model;
    }

    /**
     * Delete a entity in repository by id
     *
     * @param $id
     *
     * @return int
     * @throws \Exception
     */
    public function delete($id)
    {
        $model = $this->find($id);

        if (method_exists($this, 'beforeDelete')) {
            call_user_func_array([$this, 'beforeDelete'], [$model]);
        }

        $this->resetQuery();
        $deleted = $model->delete();

        if (method_exists($this, 'afterDelete')) {
            call_user_func_array([$this, 'afterDelete'], [$model, $deleted]);
        }

        return $deleted;
    }

    /**
     * Order collection by a given column
     *
     * @param string $column
     * @param string $direction
     *
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * Check if entity has relation
     *
     * @param string $relation
     *
     * @return $this
     */
    public function has($relation)
    {
        $this->query->has($relation);

        return $this;
    }

    /**
     * Load relations
     *
     * @param $relations
     *
     * @return $this
     */
    public function with($relations)
    {
        $this->query->with($relations);

        return $this;
    }

    /**
     * Load relation with closure
     *
     * @param string   $relation
     * @param \Closure $closure
     *
     * @return $this
     */
    public function whereHas($relation, \Closure $closure)
    {
        $this->query->whereHas($relation, $closure);

        return $this;
    }

    /**
     * Add subselect queries to count the relations.
     *
     * @param  mixed $relations
     * @return $this
     */
    public function withCount($relations)
    {
        $this->query->withCount($relations);

        return $this;
    }

    /**
     * Set hidden fields
     *
     * @param array $fields
     *
     * @return $this
     */
    public function hidden(array $fields)
    {
        $this->query->setHidden($fields);

        return $this;
    }

    /**
     * Set visible fields
     *
     * @param array $fields
     *
     * @return $this
     */
    public function visible(array $fields)
    {
        $this->query->setVisible($fields);

        return $this;
    }

    /**
     * Query Scope
     *
     * @param \Closure $scope
     *
     * @return $this
     */
    public function scopeQuery(\Closure $scope)
    {
        $scope($this->query);

        return $this;
    }

    /**
     * Applies the given where conditions to the model.
     *
     * @param array $where
     * @return void
     */
    protected function applyConditions(array $where)
    {
        foreach ($where as $field => $value) {
            if (is_array($value)) {
                list($field, $condition, $val) = $value;
                $this->query->where($field, $condition, $val);
            }else {
                $this->query->where($field, '=', $value);
            }
        }
    }
}