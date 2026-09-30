<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CareerMilestoneResource;
use App\Http\Resources\CaseStudyResource;
use App\Http\Resources\SectionResource;
use App\Http\Resources\SkillCategoryResource;
use App\Models\CareerMilestone;
use App\Models\CaseStudy;
use App\Models\Section;
use App\Models\SkillCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortfolioController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate(['lang' => ['sometimes', 'string', Rule::in(['es', 'en'])]]);

        return response()->json([
            'lang' => $validated['lang'] ?? 'es',
            'skill_categories' => SkillCategoryResource::collection(
                SkillCategory::query()->with('skills')->orderBy('order')->orderBy('id')->get(),
            )->resolve($request),
            'case_studies' => CaseStudyResource::collection(
                CaseStudy::query()->with('skills')->orderBy('order')->orderBy('id')->get(),
            )->resolve($request),
            'sections' => SectionResource::collection(
                Section::query()->where('is_active', true)->with('contentBlocks')->orderBy('order')->orderBy('id')->get(),
            )->resolve($request),
            'career_milestones' => CareerMilestoneResource::collection(
                CareerMilestone::query()->orderBy('order')->orderBy('id')->get(),
            )->resolve($request),
        ]);
    }
}
