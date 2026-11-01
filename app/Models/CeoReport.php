<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The CEO's daily report to the Director (see App\Services\CeoDailyReport for its facts). */
class CeoReport extends Model
{
    protected $table = 'ceo_daily_reports';

    protected $fillable = ['user_id', 'report_date', 'highlights', 'projects', 'hr_notes', 'enterprise_notes', 'status', 'submitted_at', 'facts', 'director_comment', 'commented_by', 'commented_at', 'read_at'];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'submitted_at' => 'datetime', 'commented_at' => 'datetime', 'read_at' => 'datetime', 'facts' => 'array'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }
}
