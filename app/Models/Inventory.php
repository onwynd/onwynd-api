<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventories';

    protected $fillable = [
        'physical_center_id',
        'item_name',
        'sku',
        'category',
        'quantity_in_stock',
        'reorder_level',
        'unit_price',
        'supplier_details',
        'expiry_date',
    ];

    protected $casts = [
        'supplier_details' => 'array',
        'expiry_date' => 'date',
        'unit_price' => 'decimal:2',
    ];

    public function center(): BelongsTo
    {
        return $this->belongsTo(PhysicalCenter::class, 'physical_center_id');
    }
}
