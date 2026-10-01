<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortfolioResource;
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
        $request->validate(['lang' => ['sometimes', 'string', Rule::in(['es', 'en'])]]);

        $portfolio = new PortfolioResource([
            'categories' => SkillCategory::query()->with('skills')->orderBy('order')->orderBy('id')->get(),
            'case_studies' => CaseStudy::query()->orderBy('order')->orderBy('id')->get(),
            'sections' => Section::query()->where('is_active', true)->with('contentBlocks')->orderBy('order')->orderBy('id')->get()->keyBy('slug'),
            'career' => CareerMilestone::query()->orderBy('order')->orderBy('id')->get(),
        ]);

        return response()->json($portfolio->resolve($request));
    }
}
