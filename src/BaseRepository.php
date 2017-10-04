<?php

namespace UMFlint\Repository;

use Illuminate\Contracts\Container\Container as Application;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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
     * @var Collection
     */
    protected $scopeQuery;

    /**
     * BaseRepository constructor.
     *
     * @param Application       $app
     * @param ValidationFactory $validation
     */
    public function __construct(Application $app, ValidationFactory $validation)
    {
        $this->app = $app;
        $this->validation = $validation;
        $this->scopeQuery = new Collection();
        $this->makeModel();

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
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Create a new instance of the model.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @return Model
     * @throws \Exception
     */
    public function makeModel()
    {
        $model = $this->app->make($this->model());

        if (!$model instanceof Model) {
            throw new \Exception("Class {$this->model()} must be an instance of Illuminate\\Database\\Eloquent\\Model");
        }

        return $this->model = $model;
    }

    /**
     * Reset the model.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     */
    public function resetModel()
    {
        $this->makeModel();
    }

    /**
     * Validate attributes.
     *
     * @author Donald Wilcox <dowilcox@umflint.edu>
     * @param array $attributes
     * @throws \Exception
     */
    protected function passesOrFailsValidation(array $attributes)
    {
        $validator = $this->validation->make($attributes, $this->rules());

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
        $this->applyScope();

        if ($this->model instanceof Builder) {
            $results = $this->model->get($columns);
        }else {
            $results = $this->model->all($columns);
        }

        $this->resetModel();
        $this->resetScope();

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
        $this->applyScope();
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
        $this->applyScope();
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
        $this->applyScope();
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
        $this->applyScope();
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
        $this->applyScope();
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
        $this->applyScope();
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
     */
    public function create(array $attributes)
    {
        $attributes = $this->model->newInstance()->forceFill($attributes)->toArray();

        if (method_exists($this, 'beforeCreate')) {
            $this->app->call([$this, 'beforeCreate'], [$attributes]);
        }

        $this->passesOrFailsValidation($attributes);
        $model = $this->model->newInstance($attributes);
        $model->save();

        if (method_exists($this, 'afterCreate')) {
            $this->app->call([$this, 'afterCreate'], [$model, $attributes]);
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
     */
    public function update(array $attributes, $id)
    {
        $this->applyScope();
        $attributes = $this->model->newInstance()->forceFill($attributes)->toArray();
        $model = $this->model->findOrFail($id);

        if (method_exists($this, 'beforeUpdate')) {
            $this->app->call([$this, 'beforeUpdate'], [$model, $attributes]);
        }

        $this->passesOrFailsValidation($attributes);
        $model->fill($attributes);
        $model->save();

        if (method_exists($this, 'afterUpdate')) {
            $this->app->call([$this, 'afterUpdate'], [$model, $attributes]);
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
        $this->applyScope();
        $model = $this->find($id);

        if (method_exists($this, 'beforeDelete')) {
            $this->app->call([$this, 'beforeDelete'], [$model]);
        }

        $this->resetModel();
        $deleted = $model->delete();
        $this->afterDelete($model, $deleted);

        if (method_exists($this, 'afterDelete')) {
            $this->app->call([$this, 'afterDelete'], [$model, $deleted]);
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
        $this->model = $this->model->orderBy($column, $direction);

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
        $this->model = $this->model->has($relation);

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
        $this->model = $this->model->with($relations);

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
        $this->model = $this->model->whereHas($relation, $closure);

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
        $this->model = $this->model->withCount($relations);

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
        $this->scopeQuery->push($scope);

        return $this;
    }

    /**
     * Reset Query Scope
     *
     * @return $this
     */
    public function resetScope()
    {
        $this->scopeQuery = new Collection();

        return $this;
    }

    /**
     * Apply scope in current Query
     *
     * @return $this
     */
    protected function applyScope()
    {
        foreach ($this->scopeQuery as $scopeQuery) {
            if (is_callable($scopeQuery)) {
                $this->model = $scopeQuery($this->model);
            }
        }

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