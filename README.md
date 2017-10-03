# Table of Contents
- [Methods](#methods)
    - [RepositoryInterface](#repository-interface)
    - [Traits](#traits)
        - [SoftDeletes](#soft-deletes)

## Methods <a name="methods"></a>

### UMFlint\Repository\Contracts\RepositoryInterface <a name="repository-interface"></a>
- lists($column, $key = null)
- pluck($column, $key = null)
- sync($id, $relation, $attributes, $detaching = true)
- syncWithoutDetaching($id, $relation, $attributes)
- all($columns = ['*'])
- paginate($limit = null, $columns = ['*'])
- simplePaginate($limit = null, $columns = ['*'])
- find($id, $columns = ['*'])
- findByField($field, $value, $columns = ['*'])
- findWhere(array $where, $columns = ['*'])
- findWhereIn($field, array $values, $columns = ['*'])
- findWhereNotIn($field, array $values, $columns = ['*'])
- create(array $attributes)
- update(array $attributes, $id)
- delete($id)
- orderBy($column, $direction = 'asc')
- has($relation)
- with($relations)
- whereHas($relation, $closure)
- withCount($relations)
- hidden(array $fields)
- visible(array $fields)
- scopeQuery(\Closure $scope)
- resetScope()

## Traits <a name="traits"></a>

### UMFlint\Traits\SoftDeletes <a name="soft-deletes"></a>
This trait allows for soft deletes:
- delete($id, $force = false)

In order to query for deleted entities:
- withTrashed()

To restore a soft deleted entity:
- restore($id)