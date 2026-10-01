<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->json('localized_period')->nullable();
        });
        DB::table('career_milestones')->orderBy('id')->chunkById(100, function (Collection $rows): void {
            foreach ($rows as $row) {
                DB::table('career_milestones')->where('id', $row->id)->update([
                    'localized_period' => json_encode(['es' => $row->period, 'en' => $row->period], JSON_THROW_ON_ERROR),
                ]);
            }
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->dropColumn('period');
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->renameColumn('localized_period', 'period');
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->json('period')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->string('legacy_period')->nullable();
        });
        DB::table('career_milestones')->orderBy('id')->chunkById(100, function (Collection $rows): void {
            foreach ($rows as $row) {
                $translations = json_decode($row->period, true, flags: JSON_THROW_ON_ERROR);
                DB::table('career_milestones')->where('id', $row->id)->update([
                    'legacy_period' => $translations['es'] ?? $translations['en'],
                ]);
            }
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->dropColumn('period');
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->renameColumn('legacy_period', 'period');
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->string('period')->nullable(false)->change();
        });
    }
};
