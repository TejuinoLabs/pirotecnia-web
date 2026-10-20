<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'active'];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($employee) {
            $employee->events()->detach();
        });
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class)->withPivot('salary')->withTimestamps();
    }

    public function experienceLevel(): BelongsTo
    {
        return $this->belongsTo(ExperienceLevel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
