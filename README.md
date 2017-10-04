# Table of Contents
- [Hooks](#hooks) 
- [Methods](#methods)
    - [RepositoryInterface](#repository-interface)
    - [Traits](#traits)
        - [SoftDeletes](#soft-deletes)
- [Validation](#validation)     

## Hooks <a name="hooks"></a>

##### Create
- beforeCreate(&$attributes)
- afterCreate($model, &$attributes)

##### Update
- beforeUpdate(&$attributes)
- afterUpdate($model, &$attributes)

##### Delete
- afterDelete($model, $deleted)

##### Restore
- afterRestore($model)

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

## Validation <a name="validation"></a>
Example rules:
```php
<?php

namespace App\Rules;

use App\Models\Announcement;
use Illuminate\Validation\Rule;
use UMFlint\Repository\Rules\BaseRules;

class AnnouncementRules extends BaseRules
{
    /**
     * Array of rules.
     *
     * @return array
     */
    protected function rules(): array
    {
        return [
            'type'     => ['required', Rule::in(array_keys(Announcement::$types))],
            'title'    => 'required',
            'message'  => 'required',
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date'],
        ];
    }
}
```

To use it in a repository:
```php
<?php

namespace App\Repositories;

use App\Models\Announcement;
use App\Rules\AnnouncementRules;
use UMFlint\Repository\BaseRepository;

class AnnouncementRepository extends BaseRepository
{
    /**
     * @inheritdoc
     */
    public function model(): string
    {
        return Announcement::class;
    }

    /**
     * Array of rules.
     *
     * @return array
     */
    public function rules(): array
    {
        return AnnouncementRules::getRules();
    }
}
```