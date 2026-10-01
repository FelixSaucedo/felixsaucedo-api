<?php

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
        $lang = $request->query('lang', 'es');
        $sections = $this->resource['sections'];
        $categories = $this->resource['categories'];
        $hero = $sections->get('hero')?->contentBlocks->firstWhere('slug', 'hero');
        $philosophies = $sections->get('philosophy')?->contentBlocks ?? new Collection;
        $leadership = $sections->get('leadership')?->contentBlocks ?? new Collection;

        return [
            'hero' => $hero === null ? null : [
                'title' => $hero->getLocalized('title', $lang),
                'body' => $hero->getLocalized('body', $lang),
                'badge' => $hero->getLocalized('subtitle', $lang),
            ],
            'philosophies' => $philosophies->map(fn (ContentBlock $block): array => [
                'icon' => $block->icon,
                'accent_color_hex' => $block->accent_color_hex,
                'title' => $block->getLocalized('title', $lang),
                'body' => $block->getLocalized('body', $lang),
            ])->values()->all(),
            'case_studies' => $this->resource['case_studies']->map(fn (CaseStudy $study): array => [
                'title' => $study->getLocalized('title', $lang),
                'badge_text' => $study->badge_text,
                'badge_color_hex' => $study->badge_color_hex,
                'problem' => $study->getLocalized('problem', $lang),
                'solution' => $study->getLocalized('solution', $lang),
            ])->values()->all(),
            'leadership' => $leadership->map(fn (ContentBlock $block): array => [
                'title' => $block->getLocalized('title', $lang),
                'body' => $block->getLocalized('body', $lang),
            ])->values()->all(),
            'categories' => $categories->map(fn (SkillCategory $category): array => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->getLocalized('name', $lang),
                'default_accent_color' => $category->default_accent_color,
            ])->values()->all(),
            'skills' => $categories->flatMap(fn (SkillCategory $category): Collection => $category->skills->map(fn (Skill $skill): array => [
                'name' => $skill->name,
                'subtitle' => $skill->getLocalized('subtitle', $lang),
                'category_slug' => $category->slug,
                'accent_color' => $skill->accent_color,
            ]))->values()->all(),
            'career' => $this->resource['career']->map(fn (CareerMilestone $milestone): array => [
                'period' => $milestone->getLocalized('period', $lang),
                'role' => $milestone->getLocalized('role', $lang),
                'company' => $milestone->company,
                'accent_color_hex' => $milestone->accent_color_hex,
            ])->values()->all(),
        ];
    }
}
