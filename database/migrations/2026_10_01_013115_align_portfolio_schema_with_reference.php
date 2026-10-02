<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->renameColumn('category_id', 'skill_category_id');
            $table->renameColumn('summary', 'subtitle');
        });
        Schema::table('skills', function (Blueprint $table): void {
            $table->foreign('skill_category_id')->references('id')->on('skill_categories')->cascadeOnDelete();
            $table->string('border_hover_color')->nullable();
        });
        Schema::table('content_blocks', function (Blueprint $table): void {
            $table->string('badge_bg')->nullable();
        });
        Schema::table('case_studies', function (Blueprint $table): void {
            $table->string('badge_text')->nullable();
            $table->string('badge_color')->nullable();
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->string('badge_color')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->dropColumn('badge_color');
        });
        Schema::table('case_studies', function (Blueprint $table): void {
            $table->dropColumn(['badge_text', 'badge_color']);
        });
        Schema::table('content_blocks', function (Blueprint $table): void {
            $table->dropColumn('badge_bg');
        });
        Schema::table('skills', function (Blueprint $table): void {
            $table->dropForeign(['skill_category_id']);
            $table->dropColumn('border_hover_color');
            $table->renameColumn('subtitle', 'summary');
            $table->renameColumn('skill_category_id', 'category_id');
        });
        Schema::table('skills', function (Blueprint $table): void {
            $table->foreign('category_id')->references('id')->on('skill_categories')->cascadeOnDelete();
        });
    }
};
