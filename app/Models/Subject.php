<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subject in the school's catalogue, for example "MATH7 - Mathematics 7".
 */
#[Fillable(['code', 'name', 'description', 'status'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /**
     * Where this subject is taught.
     *
     * @return HasMany<ClassSubject, $this>
     */
    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    /**
     * @param  Builder<Subject>  $query
     */
    public function scopeSearch(Builder $query, string $search): void
    {
        $query->where(fn (Builder $query) => $query->whereContains('code', $search)->orWhereContains('name', $search));
    }

    /**
     * Subjects taught to any class are kept for history and cannot be deleted.
     */
    public function hasDependents(): bool
    {
        return $this->classSubjects()->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
        ];
    }
}
