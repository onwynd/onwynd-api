<?php

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * HasUUID Trait
 *
 * Automatically generates and assigns a UUID to models using this trait.
 * Useful for models that need unique identifiers across systems.
 */
trait HasUUID
{
    /**
     * Boot the HasUUID trait
     *
     * Automatically generates a UUID when creating new instances
     */
    protected static function bootHasUUID(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the UUID column name
     *
     * Override this method in models that use a different UUID column name
     */
    public function getUuidColumnName(): string
    {
        return 'uuid';
    }

    /**
     * Scope to find a model by UUID
     */
    public function scopeByUuid($query, $uuid)
    {
        return $query->where($this->getUuidColumnName(), $uuid);
    }

    /**
     * Find a model by UUID
     */
    public static function findByUuid($uuid)
    {
        return static::byUuid($uuid)->first();
    }

    /**
     * Find a model by UUID or throw exception
     */
    public static function findByUuidOrFail($uuid)
    {
        return static::byUuid($uuid)->firstOrFail();
    }
}
