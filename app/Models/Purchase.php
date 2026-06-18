<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'supplier_name',
        'payment_method',
        'reference',
        'observations',
        'status',
        'discount_enabled',
        'discount_rate',
        'iva_enabled',
        'iva_rate',
        'credit_limit_enabled',
        'credit_limit_amount',
    ];

    public function products(): MorphToMany
    {
        return $this->morphToMany(Product::class, 'productable')->withPivot('quantity', 'price')->withTimestamps();
    }

    public function equipments(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class)
            ->withPivot(['quantity', 'price'])
            ->withTimestamps();
    }
}
