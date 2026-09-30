<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('security_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type')->index();
            $table->string('ip_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->json('context_payload')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('skill_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('skill_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('summary')->nullable();
            $table->string('badge_color')->nullable();
            $table->boolean('is_highlight')->default(false);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('case_studies', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->json('title');
            $table->json('context')->nullable();
            $table->json('problem');
            $table->json('solution');
            $table->json('tradeoffs')->nullable();
            $table->json('impact_metrics')->nullable();
            $table->boolean('is_featured')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('case_study_skill', function (Blueprint $table): void {
            $table->foreignId('case_study_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->primary(['case_study_id', 'skill_id']);
        });

        Schema::create('sections', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('content_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->json('body')->nullable();
            $table->string('icon')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
            $table->unique(['section_id', 'slug']);
        });

        Schema::create('career_milestones', function (Blueprint $table): void {
            $table->id();
            $table->string('period');
            $table->json('role');
            $table->string('company');
            $table->json('location')->nullable();
            $table->json('highlights')->nullable();
            $table->unsignedSmallInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('ip_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->enum('status', ['new', 'reviewed', 'archived', 'spam'])->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['contact_submissions', 'career_milestones', 'content_blocks', 'sections',
            'case_study_skill', 'case_studies', 'skills', 'skill_categories', 'security_audit_logs'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
