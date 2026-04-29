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
            // [goal, tdee, expectedCode]
            'extreme_cut'   => [1000, 2500, 'extreme_cut'],   // delta -1500
            'cut'           => [2000, 2500, 'cut'],           // delta -500
            'cut_lite'      => [2300, 2500, 'cut_lite'],      // delta -200
            'maintenance_zero' => [2500, 2500, 'maintenance'],
            'maintenance_low'  => [2450, 2500, 'maintenance'],
            'maintenance_high' => [2550, 2500, 'maintenance'],
            'light_bulk'    => [2700, 2500, 'light_bulk'],    // delta +200
            'bulk'          => [3000, 2500, 'bulk'],          // delta +500
            // boundaries
            'exactly_minus_600' => [1900, 2500, 'cut'],       // delta exactly -600 → cut
            'exactly_minus_300' => [2200, 2500, 'cut_lite'],  // delta exactly -300 → cut_lite
            'exactly_minus_100' => [2400, 2500, 'maintenance'], // delta exactly -100 → maintenance
            'exactly_plus_100'  => [2600, 2500, 'maintenance'], // delta exactly +100 → maintenance
            'exactly_plus_300'  => [2800, 2500, 'light_bulk'],  // delta exactly +300 → light_bulk
        ];
    }

    #[DataProvider('modeProvider')]
    public function test_classify_returns_expected_code(int $goal, int $tdee, string $expected): void
    {
        $mode = ModeClassifier::classify($goal, $tdee);
        $this->assertSame($expected, $mode->code);
        $this->assertSame($goal - $tdee, $mode->deltaKcal);
    }

    public function test_label_is_russian(): void
    {
        $mode = ModeClassifier::classify(2000, 2500); // delta -500 → cut
        $this->assertSame('Сушка', $mode->label);
    }
}
