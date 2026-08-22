<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('poultry_houses')->get(['house_number'])
            ->mapWithKeys(function ($house) {
                $number = $this->extractHouseNumber($house->house_number);

                return $number === null ? [] : [$number => true];
            });

        $now = now();
        $houses = collect(range(1, 43))
            ->reject(fn (int $number) => $existing->has($number))
            ->map(fn (int $number) => [
                'house_number' => 'House '.$number,
                'animal_group_id' => null,
                'bird_quantity' => 0,
                'is_active' => true,
                'notes' => 'Registered as part of the farm poultry-house register.',
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->values()
            ->all();

        if ($houses !== []) {
            DB::table('poultry_houses')->insert($houses);
        }
    }

    public function down(): void
    {
        DB::table('poultry_houses')
            ->where('notes', 'Registered as part of the farm poultry-house register.')
            ->whereNull('animal_group_id')
            ->where('bird_quantity', 0)
            ->delete();
    }

    private function extractHouseNumber(string $houseNumber): ?int
    {
        if (preg_match('/(?:house\s*)?(\d+)/i', trim($houseNumber), $matches) !== 1) {
            return null;
        }

        $number = (int) $matches[1];

        return $number >= 1 && $number <= 43 ? $number : null;
    }
};
