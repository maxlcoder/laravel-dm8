<?php

namespace LaravelDm8\Dm8\SchemaManager;

/**
 * DM8 Column
 * 
 * Represents a database column with Doctrine-compatible methods.
 */
class DmColumn
{
    /**
     * Column attributes.
     *
     * @var array
     */
    protected $attributes;

    /**
     * Create a new column instance.
     *
     * @param  array  $attributes
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Get column name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->attributes['name'];
    }

    /**
     * Get column type.
     *
     * @return string
     */
    public function getType()
    {
        return $this->attributes['type'];
    }

    /**
     * Get column length.
     *
     * @return int|null
     */
    public function getLength()
    {
        return $this->attributes['length'] ?? null;
    }

    /**
     * Get column precision.
     *
     * @return int|null
     */
    public function getPrecision()
    {
        return $this->attributes['precision'] ?? null;
    }

    /**
     * Get column scale.
     *
     * @return int|null
     */
    public function getScale()
    {
        return $this->attributes['scale'] ?? null;
    }

    /**
     * Check if column is nullable.
     *
     * @return bool
     */
    public function getNotnull()
    {
        return !($this->attributes['nullable'] ?? true);
    }

    /**
     * Get column default value.
     *
     * @return mixed
     */
    public function getDefault()
    {
        return $this->attributes['default'] ?? null;
    }

    /**
     * Check if column is nullable (alternative method).
     *
     * @return bool
     */
    public function isNullable()
    {
        return $this->attributes['nullable'] ?? true;
    }

    /**
     * Get all attributes.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributes;
    }
}

