<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Goal;
use App\Models\Meal;
use App\Models\Measurement;
use App\Models\User;
use App\Services\Insights\InsightContext;
use App\Services\Insights\InsightEngine;
use App\Services\Insights\Rules\EmptyDayInsight;
use App\Services\Insights\Rules\EndOfDayDeficitInsight;
use App\Services\Insights\Rules\KcalRemainingInsight;
use App\Services\Insights\Rules\OnlyBreakfastInsight;
use App\Services\Modes\Mode;
use App\Services\Tdee\TdeeBreakdown;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class InsightEngineTest extends TestCase
{
    private function makeContext(array $overrides = []): InsightContext
    {
        $defaults = [
            'user'         => new User(['timezone' => 'UTC']),
            'date'         => Carbon::now(),
            'dayEntry'     => null,
            'goal'         => null,
            'tdee'         => null,
            'mode'         => null,
            'totals'       => ['kcal' => 0, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0],
            'meals'        => new Collection(),
            'measurements' => new Collection(),
            'workouts'     => new Collection(),
            'hoursIntoDay' => 12,
        ];
        $args = array_merge($defaults, $overrides);
        return new InsightContext(...$args);
    }

    private function makeGoal(int $kcal = 2000, int $p = 150, int $f = 60, int $c = 200): Goal
    {
        return new Goal([
            'kcal'      => $kcal,
            'protein_g' => $p,
            'fat_g'     => $f,
            'carbs_g'   => $c,
            'start_date' => Carbon::now()->subDays(5),
            'end_date'   => null,
        ]);
    }

    public function test_empty_day_today(): void
    {
        $ctx = $this->makeContext();
        $rule = new EmptyDayInsight();
        $insight = $rule->evaluate($ctx);

        $this->assertNotNull($insight);
        $this->assertSame('empty_day', $insight->code);
        $this->assertSame('neutral', $insight->tone);
        $this->assertStringContainsString('вес', mb_strtolower($insight->body));
    }

    public function test_empty_day_past(): void
    {
        $ctx = $this->makeContext([
            'date' => Carbon::now()->subDays(3),
        ]);
        $rule = new EmptyDayInsight();
        $insight = $rule->evaluate($ctx);

        $this->assertNotNull($insight);
        $this->assertStringContainsString('тренд', $insight->body);
    }

    public function test_empty_day_silent_when_meals_present(): void
    {
        $ctx = $this->makeContext([
            'meals' => new Collection([new Meal()]),
        ]);
        $rule = new EmptyDayInsight();
        $this->assertNull($rule->evaluate($ctx));
    }

    public function test_empty_day_silent_when_measurements_present(): void
    {
        $ctx = $this->makeContext([
            'measurements' => new Collection([new Measurement()]),
        ]);
        $rule = new EmptyDayInsight();
        $this->assertNull($rule->evaluate($ctx));
    }

    public function test_only_breakfast_after_13(): void
    {
        $ctx = $this->makeContext([
            'meals' => new Collection([new Meal(['slot' => 'breakfast'])]),
            'hoursIntoDay' => 14,
        ]);
        $rule = new OnlyBreakfastInsight();
        $insight = $rule->evaluate($ctx);
        $this->assertNotNull($insight);
        $this->assertSame('only_breakfast', $insight->code);
    }

    public function test_only_breakfast_silent_before_13(): void
    {
        $ctx = $this->makeContext([
            'meals' => new Collection([new Meal(['slot' => 'breakfast'])]),
            'hoursIntoDay' => 11,
        ]);
        $rule = new OnlyBreakfastInsight();
        $this->assertNull($rule->evaluate($ctx));
    }

    public function test_only_breakfast_silent_when_lunch_logged(): void
    {
        $ctx = $this->makeContext([
            'meals' => new Collection([
                new Meal(['slot' => 'breakfast']),
                new Meal(['slot' => 'lunch']),
            ]),
            'hoursIntoDay' => 14,
        ]);
        $rule = new OnlyBreakfastInsight();
        $this->assertNull($rule->evaluate($ctx));
    }

    public function test_kcal_remaining(): void
    {
        $ctx = $this->makeContext([
            'goal' => $this->makeGoal(2000, 150, 60, 200),
            'totals' => ['kcal' => 1200, 'protein_g' => 80, 'fat_g' => 30, 'carbs_g' => 100],
            'hoursIntoDay' => 15,
        ]);
        $rule = new KcalRemainingInsight();
        $insight = $rule->evaluate($ctx);

        $this->assertNotNull($insight);
        $this->assertSame('kcal_remaining', $insight->code);
        $this->assertStringContainsString('800', $insight->body); // 2000-1200
    }

    public function test_kcal_remaining_silent_when_over_goal(): void
    {
        $ctx = $this->makeContext([
            'goal' => $this->makeGoal(2000),
            'totals' => ['kcal' => 2100, 'protein_g' => 150, 'fat_g' => 60, 'carbs_g' => 200],
            'hoursIntoDay' => 15,
        ]);
        $rule = new KcalRemainingInsight();
        $this->assertNull($rule->evaluate($ctx));
    }

    public function test_end_of_day_on_target(): void
    {
        $ctx = $this->makeContext([
            'goal' => $this->makeGoal(2000),
            'totals' => ['kcal' => 1950, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0],
            'hoursIntoDay' => 22,
        ]);
        $rule = new EndOfDayDeficitInsight();
        $insight = $rule->evaluate($ctx);
        $this->assertNotNull($insight);
        $this->assertSame('good', $insight->tone);
    }

    public function test_end_of_day_overage_warm_tone(): void
    {
        $ctx = $this->makeContext([
            'goal' => $this->makeGoal(2000),
            'totals' => ['kcal' => 2400, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0],
            'hoursIntoDay' => 22,
        ]);
        $rule = new EndOfDayDeficitInsight();
        $insight = $rule->evaluate($ctx);
        $this->assertNotNull($insight);
        $this->assertSame('warm', $insight->tone);
        $this->assertStringContainsString('+400', $insight->body);
    }

    public function test_end_of_day_big_underage_neutral_tone(): void
    {
        $ctx = $this->makeContext([
            'goal' => $this->makeGoal(2000),
            'totals' => ['kcal' => 1500, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0],
            'hoursIntoDay' => 22,
        ]);
        $rule = new EndOfDayDeficitInsight();
        $insight = $rule->evaluate($ctx);
        $this->assertNotNull($insight);
        $this->assertSame('neutral', $insight->tone);
    }

    public function test_engine_picks_higher_priority_first(): void
    {
        // Both EmptyDayInsight (90) and OnlyBreakfastInsight (50) would fire,
        // but only one each — empty day blocks because it requires no meals.
        // Test priority sort by giving conditions for two rules at once.
        $ctx = $this->makeContext([
            'goal'         => $this->makeGoal(2000),
            'totals'       => ['kcal' => 0, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0],
            'measurements' => new Collection(),
            'meals'        => new Collection(),
            'hoursIntoDay' => 22,
        ]);
        $insights = InsightEngine::evaluate($ctx, 2);

        // EmptyDayInsight (90) must come before EndOfDayDeficitInsight (80)
        $this->assertCount(2, $insights);
        $this->assertSame('empty_day', $insights[0]->code);
        $this->assertSame('end_of_day', $insights[1]->code);
    }

    public function test_engine_caps_at_max_results(): void
    {
        $ctx = $this->makeContext([
            'goal'         => $this->makeGoal(2000),
            'totals'       => ['kcal' => 0, 'protein_g' => 0, 'fat_g' => 0, 'carbs_g' => 0],
            'hoursIntoDay' => 22,
        ]);
        $insights = InsightEngine::evaluate($ctx, 1);
        $this->assertCount(1, $insights);
    }
}
