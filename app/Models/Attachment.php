<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    protected $fillable = [
        'collection', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes',
        'checksum', 'uploaded_by',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
