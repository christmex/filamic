<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('legacy_old_id')->nullable();
            $table->foreignUlid('branch_id')->constrained();
            $table->foreignUlid('school_id')->constrained();
            $table->foreignUlid('classroom_id')->nullable()->constrained();
            $table->foreignUlid('school_year_id')->constrained();
            $table->foreignUlid('student_id')->constrained();
            $table->unsignedTinyInteger('status')->default(1)->index();

            $table->timestamps();

            $table->unique(['student_id', 'school_year_id', 'branch_id']);
        });
    }
};
