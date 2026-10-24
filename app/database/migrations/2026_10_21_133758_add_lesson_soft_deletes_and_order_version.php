<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->softDeletes();
            $table->index(['course_id', 'deleted_at', 'is_published']);
        });
        Schema::table('courses', function (Blueprint $table): void {
            $table->unsignedBigInteger('lesson_order_version')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropIndex(['course_id', 'deleted_at', 'is_published']);
            $table->dropSoftDeletes();
        });
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('lesson_order_version'));
    }
};
