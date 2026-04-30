<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Numeric constants that are facts of nature, not policy decisions.
 *
 * Policy thresholds (e.g. "what counts as a perceptable deviation") live next
 * to the rule that uses them — see ModeClassifier, the Insights/Rules/*, etc.
 */
final class Numbers
{
    /** Atwater factors — energy yield per gram of macronutrient. */
    public const KCAL_PER_PROTEIN_G = 4;
    public const KCAL_PER_CARB_G    = 4;
    public const KCAL_PER_FAT_G     = 9;

    /** Time. */
    public const SECONDS_PER_DAY = 86400;
    public const DAYS_PER_WEEK   = 7;
    public const DAYS_PER_MONTH  = 30; // for "× 30 days/month" rough monthly-impact wording

    /** Reference per-100g basis for nutrition data (kcal_per_100g, etc.). */
    public const NUTRITION_REFERENCE_GRAMS = 100;
}
