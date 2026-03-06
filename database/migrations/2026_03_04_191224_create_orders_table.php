<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('supplier_id')->constrained();
            $table->foreignUlid('school_year_id')->constrained();
            $table->date('ordered_at');
            $table->unsignedTinyInteger('discount_percentage')->default(0);
            $table->unsignedSmallInteger('total_items')->default(0);
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->string('coordinator');
            $table->string('person_in_charge');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['school_year_id', 'supplier_id']);
        });
    }
};
