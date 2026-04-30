<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Modes\ModeClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModeClassifierTest extends TestCase
{
    public static function modeProvider(): array
    {
        return [
            // [goalKcal, eatenKcal, expectedCode]
            'on_target_exact'      => [2000, 2000, 'on_target'],
            'on_target_low_5pct'   => [2000, 1900, 'on_target'],   // -5%
            'on_target_high_5pct'  => [2000, 2100, 'on_target'],   // +5%
            'over_moderate'        => [2000, 2200, 'over'],         // +10%
            'over_at_15pct'        => [2000, 2300, 'over'],         // +15%
            'far_over'             => [2000, 2500, 'far_over'],     // +25%
            'under_moderate'       => [2000, 1800, 'under'],         // -10%
            'under_at_15pct'       => [2000, 1700, 'under'],         // -15%
            'far_under'            => [2000, 1500, 'far_under'],     // -25%
        ];
    }

    #[DataProvider('modeProvider')]
    public function test_classify_returns_expected_code(int $goal, int $eaten, string $expected): void
    {
        $mode = ModeClassifier::classify($goal, (float) $eaten);
        $this->assertSame($expected, $mode->code);
        $this->assertSame($eaten - $goal, $mode->deltaKcal);
    }

    public function test_label_for_on_target(): void
    {
        $mode = ModeClassifier::classify(2000, 2000.0);
        $this->assertSame('На цели', $mode->label);
    }

    public function test_label_for_far_over(): void
    {
        $mode = ModeClassifier::classify(2000, 2600.0);
        $this->assertSame('Сильный перебор', $mode->label);
    }
}
