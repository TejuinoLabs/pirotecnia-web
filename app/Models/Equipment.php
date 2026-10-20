<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Equipment extends Model
{
    use HasFactory;

    protected $table = 'equipments';

    protected $fillable = [
        'name',
        'description',
        'unit',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($equipment) {
            $equipment->packages()->detach();
            $equipment->events()->detach();
        });
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class)
            ->withPivot(['quantity', 'required'])
            ->withTimestamps();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class)
            ->withPivot(['quantity', 'check_employee', 'check_almacen'])
            ->withTimestamps();
    }

    public function purchases(): BelongsToMany
    {
        return $this->belongsToMany(Purchase::class)
            ->withPivot(['quantity', 'price'])
            ->withTimestamps();
    }
}
