<?php

/**
 * Félix Saucedo — Senior Software Engineer & Technical Lead
 * Architecture & High-Throughput Core Engineering
 * GitHub: [https://github.com/FelixSaucedo](https://github.com/FelixSaucedo)
 */

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CareerMilestone;
use App\Models\CaseStudy;
use App\Models\ContentBlock;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

class PortfolioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sections = $this->resource['sections'];
        $categories = $this->resource['categories'];
        $hero = $sections->get('hero')?->contentBlocks->firstWhere('slug', 'hero');
        $philosophies = $sections->get('philosophy')?->contentBlocks ?? new Collection();
        $leadership = $sections->get('leadership')?->contentBlocks ?? new Collection();

        return [
            'hero' => $hero === null ? null : [
                'title' => $hero->getLocalized('title'),
                'body' => $hero->getLocalized('body'),
                'badge' => $hero->getLocalized('subtitle'),
            ],
            'philosophies' => $philosophies->map(fn (ContentBlock $contentBlock): array => [
                'icon' => $contentBlock->icon,
                'accent_color_hex' => $contentBlock->accent_color_hex,
                'title' => $contentBlock->getLocalized('title'),
                'body' => $contentBlock->getLocalized('body'),
            ])->values()->all(),
            'case_studies' => $this->resource['case_studies']->map(fn (CaseStudy $caseStudy): array => [
                'title' => $caseStudy->getLocalized('title'),
                'badge_text' => $caseStudy->badge_text,
                'badge_color_hex' => $caseStudy->badge_color_hex,
                'problem' => $caseStudy->getLocalized('problem'),
                'solution' => $caseStudy->getLocalized('solution'),
            ])->values()->all(),
            'leadership' => $leadership->map(fn (ContentBlock $contentBlock): array => [
                'title' => $contentBlock->getLocalized('title'),
                'body' => $contentBlock->getLocalized('body'),
            ])->values()->all(),
            'categories' => $categories->map(fn (SkillCategory $skillCategory): array => [
                'id' => $skillCategory->id,
                'slug' => $skillCategory->slug,
                'name' => $skillCategory->getLocalized('name'),
                'default_accent_color' => $skillCategory->default_accent_color,
            ])->values()->all(),
            'skills' => $categories->flatMap(fn (SkillCategory $skillCategory): Collection => $skillCategory->skills->map(fn (Skill $skill): array => [
                'name' => $skill->name,
                'subtitle' => $skill->getLocalized('subtitle'),
                'category_slug' => $skillCategory->slug,
                'accent_color' => $skill->accent_color,
            ]))->values()->all(),
            'career' => $this->resource['career']->map(fn (CareerMilestone $milestone): array => [
                'period' => $milestone->getLocalized('period'),
                'role' => $milestone->getLocalized('role'),
                'company' => $milestone->company,
                'accent_color_hex' => $milestone->accent_color_hex,
            ])->values()->all(),
        ];
    }
}
