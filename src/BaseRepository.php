<?php

namespace UMFlint\Repository;

use Illuminate\Contracts\Container\Container as Application;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use UMFlint\Repository\Contracts\closure;
use UMFlint\Repository\Contracts\RepositoryInterface;
use UMFlint\Repository\Contracts\ValidatorException;

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
     * @var Model
     */
    protected $model;


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
        $this->model = $this->makeModel();

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
     * Rules class or array of rules.
     *
     * @param null $model
     * @return array
     */
    abstract public function rules($model = null): array;

    /**
     * Create a new instance of the model.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @return \Illuminate\Database\Eloquent\Builder
     * @throws \Exception
     */
    public function makeModel()
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
     */
    public function castAttributes(array $attributes): array
    {
        $model = $this->app->make($this->model());

        return $model->forceFill($attributes)->toArray();
    }

    /**
     * Reset the model.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     */
    public function resetModel()
    {
        $this->model = $this->makeModel();
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
        $validator = $this->validation->make($attributes, $this->rules($model));

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
        return $this->model->lists($column, $key);
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
        return $this->model->pluck($column, $key);
    }

    /**
     * Sync relations
     *
     * @param      $id
     * @param      $relation
     * @param      $attributes
     * @param bool $detaching
     * @return mixed
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
     */
    public function all($columns = ['*'])
    {

        if ($this->model instanceof Builder) {
            $results = $this->model->get($columns);
        }else {
            $results = $this->model->all($columns);
        }

        $this->resetModel();

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
     */
    public function paginate($limit = null, $columns = ['*'], $method = 'paginate')
    {
        $limit = $limit ?? 15;
        $results = $this->model->{$method}($limit, $columns);
        $results->appends($this->app->make('request')->query());
        $this->resetModel();

        return $results;
    }

    /**
     * Retrieve all data of repository, simple paginated
     *
     * @param null  $limit
     * @param array $columns
     *
     * @return mixed
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
     */
    public function find($id, $columns = ['*'])
    {
        $model = $this->model->findOrFail($id, $columns);
        $this->resetModel();

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
     */
    public function findByField($field, $value, $columns = ['*'])
    {
        $model = $this->model->where($field, $value)->get($columns);
        $this->resetModel();

        return $model;
    }

    /**
     * Find data by multiple fields
     *
     * @param array $where
     * @param array $columns
     *
     * @return mixed
     */
    public function findWhere(array $where, $columns = ['*'])
    {
        $this->applyConditions($where);
        $model = $this->model->get($columns);
        $this->resetModel();

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
     */
    public function findWhereIn($field, array $values, $columns = ['*'])
    {
        $model = $this->model->whereIn($field, $values)->get($columns);
        $this->resetModel();

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
     */
    public function findWhereNotIn($field, array $values, $columns = ['*'])
    {
        $model = $this->model->whereNotIn($field, $values)->get($columns);
        $this->resetModel();

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
        $model = $this->model->fill($attributes);
        $model->save();

        if (method_exists($this, 'afterCreate')) {
            call_user_func_array([$this, 'afterCreate'], [$model, &$attributes]);
        }

        $this->resetModel();

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
        $model = $this->model->findOrFail($id);

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

        $this->resetModel();

        return $model;
    }

    /**
     * Delete a entity in repository by id
     *
     * @param $id
     *
     * @return int
     */
    public function delete($id)
    {
        $model = $this->find($id);

        if (method_exists($this, 'beforeDelete')) {
            call_user_func_array([$this, 'beforeDelete'], [$model]);
        }

        $this->resetModel();
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
        $this->model->orderBy($column, $direction);

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
        $this->model->has($relation);

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
        $this->model->with($relations);

        return $this;
    }

    /**
     * Load relation with closure
     *
     * @param string  $relation
     * @param closure $closure
     *
     * @return $this
     */
    public function whereHas($relation, $closure)
    {
        $this->model->whereHas($relation, $closure);

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
        $this->model->withCount($relations);

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
        $this->model->setHidden($fields);

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
        $this->model->setVisible($fields);

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
        $scope($this->model);

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
                $this->model = $this->model->where($field, $condition, $val);
            }else {
                $this->model = $this->model->where($field, '=', $value);
            }
        }
    }
}