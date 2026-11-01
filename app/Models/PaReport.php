<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The PA's daily report to the Director (see App\Services\PaDailyReport for its facts). */
class PaReport extends Model
{
    protected $table = 'pa_daily_reports';

    protected $fillable = ['user_id', 'report_date', 'did_today', 'issues', 'plan_tomorrow', 'activities', 'status', 'submitted_at', 'facts', 'director_comment', 'commented_by', 'commented_at', 'read_at'];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'submitted_at' => 'datetime', 'commented_at' => 'datetime', 'read_at' => 'datetime', 'facts' => 'array', 'activities' => 'array'];
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
