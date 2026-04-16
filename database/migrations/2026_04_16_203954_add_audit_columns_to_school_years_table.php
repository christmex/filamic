<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('is_active');
            $table->char('activated_by_id', 26)->nullable()->after('activated_at');
            $table->json('activation_summary')->nullable()->after('activated_by_id');

            $table->foreign('activated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_years', function (Blueprint $table) {
            $table->dropForeign(['activated_by_id']);
            $table->dropColumn(['activated_at', 'activated_by_id', 'activation_summary']);
        });
    }
};
