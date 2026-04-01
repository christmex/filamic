<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('legacy_old_id')->nullable();
            $table->string('name')->index();
            $table->foreignUlid('branch_id')->nullable()->constrained();
            $table->foreignUlid('school_id')->nullable()->constrained();
            $table->foreignUlid('classroom_id')->nullable()->constrained();
            $table->foreignUlid('user_id')->nullable()->constrained();

            $table->string('monthly_fee_virtual_account')->nullable()->unique();
            $table->string('book_fee_virtual_account')->nullable()->unique();
            $table->unsignedInteger('monthly_fee_amount')->default(0);
            $table->unsignedInteger('book_fee_amount')->default(0);

            $table->string('nisn')->unique()->nullable();
            $table->string('nis')->nullable()->index();
            $table->unsignedTinyInteger('gender');
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('previous_education')->nullable();
            $table->string('joined_at_class')->nullable();
            $table->unsignedTinyInteger('sibling_order_in_family')->nullable();
            $table->unsignedTinyInteger('status_in_family')->nullable();
            $table->unsignedTinyInteger('religion')->nullable();
            $table->boolean('is_active')->default(false);
            $table->longText('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();
            $table->string('parent_address')->nullable();
            $table->string('parent_phone')->nullable();
            $table->string('father_job')->nullable();
            $table->string('mother_job')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_address')->nullable();
            $table->string('guardian_job')->nullable();
            $table->timestamps();
        });
    }
};
