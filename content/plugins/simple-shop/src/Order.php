<?php

namespace SimpleShop;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'shop_orders';

    protected $fillable = [
        'number', 'status', 'user_id', 'customer_name', 'email', 'phone', 'address', 'notes', 'items', 'subtotal', 'total', 'currency', 'payment_method',
    ];

    protected function casts(): array
    {
        return ['items' => 'array', 'subtotal' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public const STATUSES = [
        'pending' => 'Pending payment',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];
}
