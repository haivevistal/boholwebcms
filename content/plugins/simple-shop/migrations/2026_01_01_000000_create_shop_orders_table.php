<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plugin migrations run automatically on activation (and with
 * `php artisan migrate` while the plugin is active).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_orders')) {
            return;
        }

        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('user_id')->nullable();
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->json('items');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('payment_method', 50)->default('cod');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
