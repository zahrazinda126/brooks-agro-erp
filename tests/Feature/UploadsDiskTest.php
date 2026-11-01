<?php

namespace Tests\Feature;

use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadsDiskTest extends TestCase
{
    public function test_uploads_go_to_spaces_in_production_and_stay_local_otherwise(): void
    {
        Storage::fake('public');
        Storage::fake('spaces');

        // Local use (UPLOADS_DISK empty): the screen's usual disk.
        config(['filesystems.uploads' => null]);
        $local = Uploads::store(UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'), 'sales-payment-receipts');
        Storage::disk('public')->assertExists($local);
        $this->assertTrue(Uploads::exists($local));

        // Production (UPLOADS_DISK=spaces): every upload goes to Spaces.
        config(['filesystems.uploads' => 'spaces']);
        $remote = Uploads::store(UploadedFile::fake()->create('fuel.jpg', 10, 'image/jpeg'), 'fleet/fuel-receipts');
        Storage::disk('spaces')->assertExists($remote);
        Storage::disk('public')->assertMissing($remote);
        $this->assertSame('spaces', Uploads::disk('local'), 'contracts follow the same setting');
    }
}
