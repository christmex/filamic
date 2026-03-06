<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained();
            $table->foreignUlid('product_item_id')->constrained();
            $table->foreignUlid('branch_id')->constrained();
            $table->decimal('old_purchase_price', 10, 2);
            $table->decimal('old_sale_price', 10, 2);
            $table->decimal('new_purchase_price', 10, 2)->default(0);
            $table->decimal('new_sale_price', 10, 2)->default(0);
            $table->unsignedInteger('old_stock')->default(0);
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedTinyInteger('discount_percentage')->default(0);
            $table->timestamps();
        });
    }
};
