<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('legacy_old_id')->nullable();
            $table->foreignUlid('school_id')->constrained();
            $table->string('name');
            $table->unsignedTinyInteger('grade');
            $table->unsignedTinyInteger('identifier')->nullable();

            // custom
            $table->string('phase')->nullable();
            $table->boolean('is_moving_class')->default(false);
            // custom

            $table->timestamps();

            $table->unique(['school_id', 'name']);
        });
    }
};
