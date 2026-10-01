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
        Schema::table('skill_categories', function (Blueprint $table): void {
            $table->string('default_accent_color', 7)->default('#38bdf8');
        });
        Schema::table('skills', function (Blueprint $table): void {
            $table->string('accent_color', 7)->default('#94a3b8');
        });
        Schema::table('case_studies', function (Blueprint $table): void {
            $table->string('badge_color_hex', 7)->default('#38bdf8');
        });
        Schema::table('content_blocks', function (Blueprint $table): void {
            $table->string('accent_color_hex', 7)->nullable();
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->string('accent_color_hex', 7)->default('#38bdf8');
        });

        foreach (['data' => '#10b981', 'api' => '#c084fc', 'quality' => '#fbbf24'] as $slug => $color) {
            DB::table('skill_categories')->where('slug', $slug)->update(['default_accent_color' => $color]);
        }
        foreach ([
            ['skills', 'badge_color', 'accent_color', '#94a3b8'],
            ['case_studies', 'badge_color', 'badge_color_hex', '#38bdf8'],
            ['content_blocks', 'badge_bg', 'accent_color_hex', null],
            ['career_milestones', 'badge_color', 'accent_color_hex', '#38bdf8'],
        ] as [$table, $source, $target, $default]) {
            DB::table($table)->orderBy('id')->chunkById(100, function (Collection $rows) use ($table, $source, $target, $default): void {
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([
                        $target => $this->hexColor($row->{$source}, $default),
                    ]);
                }
            });
        }

        Schema::table('skills', function (Blueprint $table): void {
            $table->dropColumn(['badge_color', 'border_hover_color']);
        });
        Schema::table('case_studies', function (Blueprint $table): void {
            $table->dropColumn('badge_color');
        });
        Schema::table('content_blocks', function (Blueprint $table): void {
            $table->dropColumn('badge_bg');
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->dropColumn('badge_color');
        });
    }

    /**
     * Restore equivalent colors with arbitrary Tailwind classes; original class names cannot be reconstructed.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table): void {
            $table->string('badge_color')->nullable();
            $table->string('border_hover_color')->nullable();
        });
        Schema::table('case_studies', function (Blueprint $table): void {
            $table->string('badge_color')->nullable();
        });
        Schema::table('content_blocks', function (Blueprint $table): void {
            $table->string('badge_bg')->nullable();
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->string('badge_color')->nullable();
        });
        foreach ([
            ['skills', 'accent_color', 'badge_color'],
            ['case_studies', 'badge_color_hex', 'badge_color'],
            ['content_blocks', 'accent_color_hex', 'badge_bg'],
            ['career_milestones', 'accent_color_hex', 'badge_color'],
        ] as [$table, $source, $target]) {
            DB::table($table)->orderBy('id')->chunkById(100, function (Collection $rows) use ($table, $source, $target): void {
                foreach ($rows as $row) {
                    $color = $row->{$source};
                    $classes = $color === null ? null : "text-[{$color}]";
                    if ($color !== null && in_array($table, ['case_studies', 'content_blocks'], true)) {
                        $classes = "bg-[{$color}]/10 ".$classes;
                    }
                    if ($color !== null && $table === 'case_studies') {
                        $classes .= " border border-[{$color}]/20";
                    }
                    $attributes = [$target => $classes];
                    if ($table === 'skills') {
                        $categoryColor = DB::table('skill_categories')->where('id', $row->skill_category_id)->value('default_accent_color');
                        $attributes['border_hover_color'] = "hover:border-[{$categoryColor}]";
                    }
                    DB::table($table)->where('id', $row->id)->update($attributes);
                }
            });
        }
        Schema::table('skill_categories', function (Blueprint $table): void {
            $table->dropColumn('default_accent_color');
        });
        Schema::table('skills', function (Blueprint $table): void {
            $table->dropColumn('accent_color');
        });
        Schema::table('case_studies', function (Blueprint $table): void {
            $table->dropColumn('badge_color_hex');
        });
        Schema::table('content_blocks', function (Blueprint $table): void {
            $table->dropColumn('accent_color_hex');
        });
        Schema::table('career_milestones', function (Blueprint $table): void {
            $table->dropColumn('accent_color_hex');
        });
    }

    private function hexColor(?string $value, ?string $default): ?string
    {
        if ($value === null) {
            return $default;
        }
        if (preg_match('/#[0-9a-fA-F]{6}(?![0-9a-fA-F])/', $value, $matches) === 1) {
            return strtolower($matches[0]);
        }
        foreach (['sky' => '#38bdf8', 'emerald' => '#10b981', 'purple' => '#c084fc', 'amber' => '#fbbf24', 'slate' => '#94a3b8'] as $name => $color) {
            if (str_contains($value, 'text-'.$name.'-')) {
                return $color;
            }
        }

        return $default;
    }
};
