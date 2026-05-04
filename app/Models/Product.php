<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'category_id',
        'price',
        'sku',
        'image',
        'stock_count',
        'alert_stock',
        'track_stock',
        'incentive_amount','type',
        'is_active'
    ];
    protected $guarded = ['id']; // Plus sécurisé que tout laisser vide

    protected $casts = [
        'is_active' => 'boolean',
        'track_stock' => 'boolean',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relation avec la catégorie
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

/*    public function modifiers()
    {
        // Un produit a plusieurs groupes de modificateurs (ex: Accompagnement ET Boisson)
        return $this->belongsToMany(Modifier::class, 'modifier_product')
            ->withPivot('sort_order')
            ->orderBy('pivot_sort_order');
    }*/
    public function modifiers(): BelongsToMany
    {
        // On charge aussi les items par défaut pour faciliter le POS
        return $this->belongsToMany(Modifier::class)->with('items');
    }

    // Scope pour filtrer les produits actifs (utile pour le POS)
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, ',', ' ') . ' FCFA';
    }

    public function getImageUrlAttribute(): string
    {
        return $this->image ? asset($this->image) : '';
    }
}
