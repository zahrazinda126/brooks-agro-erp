<?php

/*
 | Farm operating rules. Defaults are the values the application has always used;
 | override them in .env if the farm's practice changes.
 */
return [
    // Planning estimate of feed per bird per day (kg). Used by the production dashboard and feed-serving checks.
    'feed_kg_per_bird_per_day' => (float) env('FARM_FEED_KG_PER_BIRD_DAY', 0.125),

    // ISO weekday on which supplier feed deliveries are recorded (1 = Monday … 7 = Sunday). Empty = any day.
    'feed_delivery_weekday' => env('FARM_FEED_DELIVERY_WEEKDAY', 2),
    // A feed bill may charge up to this much more than the farm weighed (bags lose a little in transit) before it is held for the CEO.
    'feed_bill_tolerance_percent' => env('FARM_FEED_BILL_TOLERANCE_PERCENT', 1),

    // Chicks up to this age (days) must be housed in brooders.
    'brooder_max_age_days' => (int) env('FARM_BROODER_MAX_AGE_DAYS', 42),

    // The farm's own details, printed on every report's letterhead and footer.
    'name' => env('FARM_NAME', 'BROOKS AGRO FARM'),
    'address' => env('FARM_ADDRESS', 'Kitojjo, Buwama, Mpigi District, Uganda'),
    'phone' => env('FARM_PHONE', '+256 786 531 114'),
    'currency' => env('FARM_CURRENCY', 'UGX'),
];
