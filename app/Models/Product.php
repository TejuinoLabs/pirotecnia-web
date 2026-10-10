<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_role_id',
        'name',
        'description',
        'unit',
        'duration',
        'shots',
        'caliber',
        'shape',
    ];

    protected $appends = ['display_name'];

    public function getDisplayNameAttribute(): string
    {
        $name = trim((string) $this->name);
        $caliber = trim((string) $this->caliber);
        if ($caliber === '') {
            return $name;
        }
        $label = $caliber;
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*(?:["″]|in|pulgadas?)?$/iu', $caliber, $matches)) {
            $number = str_replace(',', '.', $matches[1]);
            if (str_contains($number, '.')) {
                $number = rtrim(rtrim($number, '0'), '.');
            }
            $label = $number.'"';
            // Normalize a caliber already present at the end of the name.
            $pattern = '/(?<![\d.,])'.preg_quote($number, '/').'\s*(?:["″]|in|pulgadas?)?$/iu';
            if (preg_match($pattern, $name)) {
                return trim(preg_replace($pattern, '', $name)).' '.$label;
            }
        }
        if (mb_stripos($name, $label) !== false) {
            return $name;
        }

        return $name.' '.$label;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($product) {
            $product->products()->detach();
        });
    }

    public function products(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'productable')
            ->withPivot('quantity', 'price')
            ->withTimestamps()
            ->where('product_role_id', 1);
    }

    public function productParents(): MorphToMany
    {
        return $this->morphedByMany(Product::class, 'productable');
    }

    public function events(): MorphToMany
    {
        return $this->morphedByMany(Event::class, 'productable');
    }

    public function inventories(): MorphToMany
    {
        return $this->morphedByMany(Inventory::class, 'productable')->withPivot(['quantity', 'price']);
    }

    public function packages(): MorphToMany
    {
        return $this->morphedByMany(Package::class, 'productable');
    }
}
