<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FixedAsset extends Model
{
    public const CATEGORIES = [
        'Land' => 'Purchased land and permanent land rights',
        'Buildings' => 'Offices, stores, staff housing and permanent farm buildings',
        'Farm Structures' => 'Poultry houses, animal shelters, fences, gates and silos',
        'Poultry Equipment' => 'Incubators, brooders, cages, feeders and drinker systems',
        'Livestock Equipment' => 'Milking, weighing, handling and animal housing equipment',
        'Crop & Garden Equipment' => 'Cultivators, sprayers, planters and harvesting equipment',
        'Plant & Machinery' => 'Feed mills, mixers, grinders and production machinery',
        'Processing & Packaging Equipment' => 'Graders, sealers, scales and packing machinery',
        'Vehicles' => 'Cars, pickups, trucks, tractors and motorcycles',
        'Water & Irrigation Systems' => 'Boreholes, pumps, tanks and irrigation installations',
        'Power & Electrical Systems' => 'Generators, solar systems, inverters and fixed electrical equipment',
        'Cold Storage & Refrigeration' => 'Cold rooms, freezers and refrigeration equipment',
        'Veterinary & Laboratory Equipment' => 'Durable veterinary, testing and laboratory equipment',
        'Workshop & Maintenance Equipment' => 'Welders, compressors and durable workshop tools',
        'Office Furniture & Equipment' => 'Desks, chairs, cabinets, printers and office machines',
        'Computers & IT Equipment' => 'Computers, servers, network equipment and communication devices',
        'Security Equipment' => 'CCTV, access control, alarms and durable security installations',
        'Other Capital Equipment' => 'Other durable assets used for more than one year',
    ];

    public const COMMON_FARM_ASSETS = [
        'Land' => ['Main farm land', 'Production field', 'Access road and permanent land improvement'],
        'Buildings' => ['Administration office', 'Farm store', 'Feed store', 'Produce store', 'Staff quarters', 'Workshop building', 'Security house'],
        'Farm Structures' => ['Poultry house', 'Brooder house', 'Goat pen', 'Rabbit house and hutches', 'Dog kennels', 'Greenhouse', 'Perimeter fence and gates', 'Silo', 'Compost structure'],
        'Poultry Equipment' => ['Egg incubator', 'Egg hatcher', 'Electric brooder', 'Poultry cages', 'Automatic feeder system', 'Automatic drinker system', 'Egg grading machine', 'Poultry weighing scale'],
        'Livestock Equipment' => ['Livestock weighing scale', 'Milking machine', 'Chaff cutter', 'Animal handling crate', 'Feed trough system', 'Water trough system'],
        'Crop & Garden Equipment' => ['Power tiller', 'Boom sprayer', 'Knapsack sprayer', 'Planter', 'Cultivator', 'Lawn mower or brush cutter', 'Harvesting machine'],
        'Plant & Machinery' => ['Tractor', 'Feed mill', 'Feed mixer', 'Hammer mill or grinder', 'Pellet machine', 'Maize sheller', 'Hay baler'],
        'Processing & Packaging Equipment' => ['Platform weighing scale', 'Packaging and sealing machine', 'Produce washing equipment', 'Produce grading machine'],
        'Vehicles' => ['Farm pickup', 'Delivery truck', 'Motorcycle', 'Trailer', 'Refrigerated delivery vehicle'],
        'Water & Irrigation Systems' => ['Borehole', 'Water pump', 'Water tank', 'Drip irrigation system', 'Sprinkler irrigation system', 'Main farm plumbing installation'],
        'Power & Electrical Systems' => ['Standby generator', 'Solar power system', 'Solar inverter', 'Battery bank', 'Farm electrical installation'],
        'Cold Storage & Refrigeration' => ['Cold room', 'Chest freezer', 'Commercial refrigerator', 'Milk cooler'],
        'Veterinary & Laboratory Equipment' => ['Veterinary examination equipment', 'Vaccine refrigerator', 'Laboratory microscope', 'Autoclave or steriliser', 'Digital livestock thermometer set'],
        'Workshop & Maintenance Equipment' => ['Welding machine', 'Air compressor', 'Pressure washer', 'Heavy-duty tool set', 'Mechanical jack'],
        'Office Furniture & Equipment' => ['Office desks', 'Office chairs', 'Filing cabinets', 'Photocopier', 'Printer'],
        'Computers & IT Equipment' => ['Desktop computer', 'Laptop computer', 'Server', 'Network router and switches', 'Biometric attendance machine'],
        'Security Equipment' => ['CCTV system', 'Alarm system', 'Access-control system', 'Security lighting installation'],
        'Other Capital Equipment' => ['Fire protection system', 'Waste-management equipment'],
    ];

    protected $fillable=['name','asset_tag','category','purchase_date','purchase_cost','useful_life_years','location','notes','status','recorded_by'];
    protected function casts(): array { return ['purchase_date'=>'date','purchase_cost'=>'decimal:2']; }
}
