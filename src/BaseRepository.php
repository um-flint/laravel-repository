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
use UMFlint\Repository\Rules\BaseRules;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * @var Application
     */
    protected $app;

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
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->scopeQuery = new Collection();
        $this->makeModel();
        $this->boot();
    }

    /**
     * Anything that needs to happen when the class is created.
     */
    public function boot()
    {
        //
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
     * @return string
     */
    abstract public function rules();

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
        $factory = $this->app->make(ValidationFactory::class);
        $rules = $this->rules();

        if (is_array($rules)) {
            $messages = [];
        }else {
            $rulesClass = new $rules;

            if (!$rulesClass instanceof BaseRules) {
                throw new \Exception("Class {$rulesClass} must be an instances of UMFlint\\Repository\\Rules\\BaseRules");
            }

            $rules = $rulesClass::getRules();
            $messages = $rulesClass::getMessages();
        }

        if (count($rules) === 0) {
            return;
        }

        $validator = $factory->make($attributes, $rules, $messages);

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
     * Hook before saving in creation.
     *
     * @param array $attributes
     */
    public function beforeCreate(array &$attributes)
    {
        //
    }

    /**
     * Hook after saving in creation.
     *
     * @param Model $model
     * @param array $attributes
     */
    public function afterCreate(Model &$model, array &$attributes)
    {
        //
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
        $this->passesOrFailsValidation($attributes);

        $this->beforeCreate($attributes);
        $model = $this->model->newInstance($attributes);
        $model->save();
        $this->afterCreate($model, $attributes);
        $this->resetModel();

        return $model;
    }

    /**
     * Hook before saving in updating.
     *
     * @param Model $model
     * @param array $attributes
     */
    public function beforeUpdate(Model &$model, array &$attributes)
    {
        //
    }

    /**
     * Hook after saving in updating.
     *
     * @param Model $model
     * @param array $attributes
     */
    public function afterUpdate(Model &$model, array &$attributes)
    {
        //
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
        $this->passesOrFailsValidation($attributes);

        $model = $this->model->findOrFail($id);
        $this->beforeUpdate($model, $attributes);
        $model->fill($attributes);
        $model->save();
        $this->afterUpdate($model, $attributes);
        $this->resetModel();

        return $model;
    }

    /**
     * Hook before deleting entity.
     *
     * @param Model $model
     */
    public function beforeDelete(Model &$model)
    {
        //
    }

    /**
     * Hook after deleting entity.
     *
     * @param Model $model
     * @param bool  $deleted
     */
    public function afterDelete(Model $model, bool $deleted)
    {
        //
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
        $this->beforeDelete($model);
        $this->resetModel();
        $deleted = $model->delete();
        $this->afterDelete($model, $deleted);

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