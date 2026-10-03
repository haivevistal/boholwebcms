<?php

namespace SimpleShop;

use App\Models\Post;

/**
 * Session cart: [product_id => quantity].
 */
class Cart
{
    protected const KEY = 'simple_shop_cart';

    public static function items(): array
    {
        return (array) session(self::KEY, []);
    }

    public static function add(int $productId, int $qty = 1): void
    {
        $items = static::items();
        $items[$productId] = max(1, ($items[$productId] ?? 0) + $qty);
        session([self::KEY => $items]);
        do_action('shop_cart_item_added', $productId, $qty);
    }

    public static function set(int $productId, int $qty): void
    {
        $items = static::items();
        if ($qty <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $qty;
        }
        session([self::KEY => $items]);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    public static function count(): int
    {
        return array_sum(static::items());
    }

    /**
     * @return array<int, array{product: Post, qty: int, price: float, line: float}>
     */
    public static function lines(): array
    {
        $items = static::items();
        if (! $items) {
            return [];
        }

        $products = Post::where('type', 'product')->where('status', 'publish')->whereIn('id', array_keys($items))->get()->keyBy('id');
        $lines = [];
        foreach ($items as $id => $qty) {
            if ($product = $products->get($id)) {
                $price = Shop::price($product);
                $lines[] = ['product' => $product, 'qty' => (int) $qty, 'price' => $price, 'line' => $price * $qty];
            }
        }

        return $lines;
    }

    public static function subtotal(): float
    {
        return array_sum(array_column(static::lines(), 'line'));
    }

    public static function total(): float
    {
        return (float) apply_filters('shop_cart_total', static::subtotal(), static::lines());
    }
}
