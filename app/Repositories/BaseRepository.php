<?php

namespace App\Repositories;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class BaseRepository implements RepositoryInterface
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * BaseRepository constructor
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * Get all records
     */
    public function all()
    {
        return $this->model->all();
    }

    /**
     * Get record by ID
     */
    public function find($id)
    {
        return $this->model->find($id);
    }

    /**
     * Create new record
     */
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    /**
     * Update record
     */
    public function update($id, array $data)
    {
        $record = $this->find($id);

        if ($record) {
            $record->update($data);
        }

        return $record;
    }

    /**
     * Delete record
     */
    public function delete($id)
    {
        $record = $this->find($id);

        if ($record) {
            return $record->delete();
        }

        return false;
    }

    /**
     * Get paginated records
     */
    public function paginate($perPage = 15)
    {
        return $this->model->paginate($perPage);
    }

    /**
     * Get records by conditions
     */
    public function where($column, $value = null)
    {
        if (is_array($column)) {
            return $this->model->where($column);
        }

        return $this->model->where($column, $value);
    }

    /**
     * Get with relations
     */
    public function with($relations)
    {
        return $this->model->with($relations);
    }

    /**
     * Set model for repository
     */
    public function setModel(Model $model): void
    {
        $this->model = $model;
    }

    /**
     * Get model instance
     */
    public function getModel()
    {
        return $this->model;
    }
}
