<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Shared by Student and Teacher, which both store first, middle and last names.
 */
trait HasPersonName
{
    /**
     * "Ana Maria Cruz", or "Ana Cruz" without a middle name.
     *
     * @return Attribute<string, never>
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]))));
    }

    /**
     * "Cruz, Ana Maria", for alphabetical lists.
     *
     * @return Attribute<string, never>
     */
    protected function sortableName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->last_name.', '.$this->first_name.' '.($this->middle_name ?? '')));
    }

    /**
     * Sort alphabetically by last name, then first name.
     *
     * @param  Builder<static>  $query
     */
    public function scopeOrderByName(Builder $query): void
    {
        $query->orderBy('last_name')->orderBy('first_name');
    }
}
