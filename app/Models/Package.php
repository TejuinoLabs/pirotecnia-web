<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type_id',
        'name',
        'description',
        'price',
        'duration',
        'video_url',
        'experience_level_id',
        'status',
        'valid_from',
        'valid_until',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_until' => 'date',
    ];

    public function getVideoUrlAttribute(?string $value): ?string
    {
        return app(\App\Services\Admin\AdminMediaService::class)->present($value);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($package) {
            $package->products()->detach();
            $package->materials()->detach();
            $package->equipments()->detach();
        });
    }

    public function materials(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'productable')
            ->withPivot(['quantity', 'price'])
            ->withTimestamps()
            ->where(function ($query) {
                $query->where('product_role_id', 1)
                    ->orWhere('product_role_id', 2);
            });
    }

    public function products(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'productable')
            ->withPivot(['quantity', 'price'])
            ->withTimestamps()
            ->where('product_role_id', 1);
    }

    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class)
            ->withPivot(['quantity', 'required'])
            ->withTimestamps();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_package')->withPivot('quantity', 'price')->withTimestamps();
    }

    public function experienceLevel(): BelongsTo
    {
        return $this->belongsTo(ExperienceLevel::class, 'experience_level_id');
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'event_type_id');
    }
}
