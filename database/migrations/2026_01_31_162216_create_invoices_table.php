<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('legacy_old_id')->nullable();

            $table->foreignUlid('branch_id')->constrained();
            $table->foreignUlid('school_id')->constrained();
            $table->foreignUlid('classroom_id')->constrained();
            $table->foreignUlid('school_year_id')->constrained();
            $table->foreignUlid('student_id')->constrained();

            $table->string('reference_number')->unique();
            $table->string('fingerprint')->unique();

            $table->string('branch_name');
            $table->string('school_name');
            $table->string('classroom_name');
            $table->string('school_year_name');
            $table->string('student_name');

            $table->unsignedTinyInteger('type');
            $table->unsignedTinyInteger('month')->nullable();

            $table->unsignedInteger('amount');
            $table->unsignedInteger('fine')->default(0);
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('total_amount');

            $table->date('issued_at');
            $table->date('due_date');

            $table->unsignedTinyInteger('status')->default(1)->index();
            $table->unsignedTinyInteger('payment_method')->nullable();
            $table->dateTime('paid_at')->nullable();

            $table->string('payment_reference')->nullable()->index(); // for group payment

            $table->text('description')->nullable();

            $table->timestamps();
        });
    }
};
