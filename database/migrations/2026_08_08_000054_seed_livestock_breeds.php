<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $breeds = [
            ['goat-boer', 'Boer', 'goat', 'meat'],
            ['goat-saanen', 'Saanen', 'goat', 'dairy'],
            ['goat-toggenburg', 'Toggenburg', 'goat', 'dairy'],
            ['goat-alpine', 'Alpine', 'goat', 'dairy'],
            ['goat-anglo-nubian', 'Anglo-Nubian', 'goat', 'dual'],
            ['goat-mubende', 'Mubende', 'goat', 'meat'],
            ['goat-kigezi', 'Kigezi', 'goat', 'meat'],
            ['goat-small-east-african', 'Small East African', 'goat', 'dual'],
            ['rabbit-new-zealand-white', 'New Zealand White', 'rabbit', 'meat'],
            ['rabbit-californian', 'Californian', 'rabbit', 'meat'],
            ['rabbit-chinchilla', 'Chinchilla', 'rabbit', 'dual'],
            ['rabbit-flemish-giant', 'Flemish Giant', 'rabbit', 'meat'],
            ['rabbit-dutch', 'Dutch', 'rabbit', 'dual'],
            ['rabbit-rex', 'Rex', 'rabbit', 'dual'],
            ['rabbit-angora', 'Angora', 'rabbit', 'fibre'],
            ['rabbit-local', 'Local Rabbit', 'rabbit', 'dual'],
            ['dog-german-shepherd', 'German Shepherd', 'dog', 'working'],
            ['dog-belgian-malinois', 'Belgian Malinois', 'dog', 'working'],
            ['dog-labrador-retriever', 'Labrador Retriever', 'dog', 'working'],
            ['dog-rottweiler', 'Rottweiler', 'dog', 'guarding'],
            ['dog-doberman', 'Doberman Pinscher', 'dog', 'guarding'],
            ['dog-boerboel', 'Boerboel', 'dog', 'guarding'],
            ['dog-jack-russell', 'Jack Russell Terrier', 'dog', 'working'],
            ['dog-local-mixed', 'Local / Mixed Breed', 'dog', 'working'],
        ];

        foreach ($breeds as [$code, $name, $species, $purpose]) {
            DB::table('breeds')->updateOrInsert(
                ['code'=>$code],
                ['name'=>$name, 'species'=>$species, 'purpose'=>$purpose, 'is_active'=>true,
                 'updated_at'=>now(), 'created_at'=>now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('breeds')->where('species', '!=', 'poultry')->whereIn('code', [
            'goat-boer','goat-saanen','goat-toggenburg','goat-alpine','goat-anglo-nubian',
            'goat-mubende','goat-kigezi','goat-small-east-african','rabbit-new-zealand-white',
            'rabbit-californian','rabbit-chinchilla','rabbit-flemish-giant','rabbit-dutch',
            'rabbit-rex','rabbit-angora','rabbit-local','dog-german-shepherd','dog-belgian-malinois',
            'dog-labrador-retriever','dog-rottweiler','dog-doberman','dog-boerboel',
            'dog-jack-russell','dog-local-mixed',
        ])->delete();
    }
};
