<?php

namespace Jchedev\Laravel\Classes\BuilderServices;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers;
use Jchedev\Laravel\Classes\Pagination\ByOffsetLengthAwarePaginator;

abstract class BuilderService
{
    /**
     * @var bool
     */
    protected $with_validation = true;

    /**
     * @var array
     */
    protected $filters = [];


    /**
     * BuilderService constructor.
     *
     * @param array $filters
     */
    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /*
     * Configuration of the Service Builder
     */

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    abstract protected function defaultBuilder();

    /**
     * @return array
     */
    protected function availableFilters()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function availableSort()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function validationRulesForCreate()
    {
        return [];
    }

    /**
     * @return array
     */
    protected function validationRulesForUpdate()
    {
        return [];
    }

    /*
     * Configure Builder based on modifiers and filters
     */

    /**
     * @param \Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers|null $modifiers
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function builder(Modifiers $modifiers = null)
    {
        $builder = $this->defaultBuilder();

        return $this->modifyBuilder($builder, $modifiers);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers|null $modifiers
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function modifyBuilder(Builder $builder, Modifiers $modifiers = null)
    {
        $modifiers = $modifiers ? clone $modifiers : new Modifiers();

        $modifiers->filters($this->filters);

        $modifiers->applyToBuilder($builder, $this->availableFilters(), $this->availableSort());

        return $builder;
    }

    /*
     * Create New Model
     */

    /**
     * @param array $data
     * @param array $opts
     * @return array|\Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function createManyWithoutValidation(array $data, array $opts = [])
    {
        $this->withoutValidation();

        try {
            $result = $this->createMany($data, $opts);
        }
        catch (\Exception $e) {
        }

        $this->withValidation();

        if (isset($e)) {
            throw $e;
        }

        return $result;
    }

    /**
     * @param array $data
     * @param array $opts
     * @param bool $skip_errors
     * @return array|\Illuminate\Database\Eloquent\Collection
     * @throws \Exception
     */
    public function createMany(array $data, array $opts = [], $skip_errors = false)
    {
        $validator = $this->validatorForCreate();

        $validated_data = [];

        foreach ($data as $element_data) {
            try {
                $validator->setData($element_data);

                $validated_data[] = $this->validate($validator);
            }
            catch (\Exception $e) {
                if ($skip_errors === false) {
                    throw $e;
                }
            }
        }

        return $this->onCreateMany($validated_data, $opts);
    }

    /**
     * @param array $data
     * @param array $opts
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function onCreateMany(array $data, array $opts = [])
    {
        $return = $this->builder()->getModel()->newCollection();

        foreach ($data as $element_data) {
            $return->push($this->onCreate($element_data, $opts));
        }

        return $return;
    }

    /**
     * @param array $data
     * @param array $opts
     * @return \Illuminate\Database\Eloquent\Model
     * @throws \Exception
     */
    public function createWithoutValidation(array $data, array $opts = [])
    {
        $this->withoutValidation();

        try {
            $result = $this->create($data, $opts);
        }
        catch (\Exception $e) {
        }

        $this->withValidation();

        if (isset($e)) {
            throw $e;
        }

        return $result;
    }

    /**
     * @param array $data
     * @param array $opts
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function create(array $data, array $opts = [])
    {
        $validator = $this->validatorForCreate($data);

        $validated_data = $this->validate($validator);

        return $this->onCreate($validated_data, $opts);
    }

    /**
     * @param array $data
     * @param array $opts
     * @return $this|\Illuminate\Database\Eloquent\Model
     */
    protected function onCreate(array $data, array $opts = [])
    {
        return $this->builder()->create($data);
    }

    /**
     * @param array $data
     * @return mixed
     */
    public function validatorForCreate(array $data = [])
    {
        return Validator::make($data, $this->validationRulesForCreate());
    }

    /*
     * Update Model
     */

    // ... todo

    /*
     * Delete Model
     */

    /**
     * @param $element
     * @return bool|null
     */
    public function delete($element)
    {
        $model = $this->defaultBuilder()->getModel();

        if (!is_a($element, get_class($model))) {
            $element = $model->newQuery()->find($element);
        }

        return $this->onDelete($element);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return bool|null
     */
    protected function onDelete(Model $model)
    {
        return $model->delete();
    }

    /*
     * Get / Paginate / Count and other builder actions
     */

    /**
     * @param \Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers|null $modifiers
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get(Modifiers $modifiers = null, $columns = ['*'])
    {
        $builder = $this->builder($modifiers);

        return $builder->get($columns);
    }

    /**
     * @param \Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers|null $modifiers
     * @param int $per_page
     * @param array $columns
     * @return \Jchedev\Laravel\Classes\Pagination\ByOffsetLengthAwarePaginator
     */
    public function paginate(Modifiers $modifiers = null, $per_page = 15, $columns = ['*'])
    {
        $modifiers = $modifiers ?: new Modifiers();

        $limit = !is_null($limit = $modifiers->getLimit()) ? (int)$limit : $per_page;

        $offset = !is_null($offset = $modifiers->getOffset()) ? (int)$offset : 0;

        $modifiers->limit($limit)->offset($offset);

        $builder = $this->builder($modifiers);

        $total = $builder->toBase()->getCountForPagination();

        $items = ($total != 0 ? $builder->get($columns) : $builder->getModel()->newCollection());

        return new ByOffsetLengthAwarePaginator($items, $total, $limit, $offset);
    }

    /**
     * @param $id
     * @param null $key
     * @param \Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers|null $modifiers
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function find($id, $key = null, Modifiers $modifiers = null, $columns = ['*'])
    {
        $builder = $this->builder($modifiers);

        $key = $key ?: $builder->getModel()->getKeyName();

        $builder->where($key, '=', $id);

        return $builder->first($columns);
    }

    /**
     * @param \Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers|null $modifiers
     * @param array $columns
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public function first(Modifiers $modifiers = null, $columns = ['*'])
    {
        $builder = $this->builder($modifiers);

        return $builder->first($columns);
    }

    /**
     * @param \Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers|null $modifiers
     * @param string $columns
     * @return int
     */
    public function count(Modifiers $modifiers = null, $columns = '*')
    {
        $builder = $this->builder($modifiers);

        return $builder->count($columns);
    }

    /**
     * @param \Jchedev\Laravel\Classes\BuilderServices\Modifiers\Modifiers|null $modifiers
     * @return string
     */
    public function toSql(Modifiers $modifiers = null)
    {
        $builder = $this->builder($modifiers);

        return $builder->toSql();
    }

    /*
     * Validation Management
     */

    /**
     * @param bool $bool
     * @return $this
     */
    public function withValidation($bool = true)
    {
        $this->with_validation = $bool;

        return $this;
    }

    /**
     * @return \Jchedev\Laravel\Classes\BuilderServices\BuilderService
     */
    public function withoutValidation()
    {
        return $this->withValidation(false);
    }

    /**
     * @param \Illuminate\Contracts\Validation\Validator $validator
     * @return array
     */
    protected function validate(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if ($this->with_validation === true) {
            $validator->validate();
        }

        return array_only($validator->getData(), array_keys($validator->getRules()));
    }

    /*
     * Filters Management
     */

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function addFilter($key, $value)
    {
        $this->filters[] = [$key => $value];

        return $this;
    }

    public function removeFilter()
    {
        // ...
    }
}