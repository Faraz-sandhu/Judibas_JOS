<?php

namespace App\Modules\Daymark\Models;

use App\Modules\Daymark\Services\ReportRichText;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'daymark_reports';

    protected $guarded = ['id'];

    protected $attributes = ['content_format' => 'plain'];

    protected $appends = ['sections_html', 'sections_text'];

    public function getSectionsHtmlAttribute(): array
    {
        return collect(ReportRichText::FIELDS)->mapWithKeys(fn ($f) => [$f => $this->sectionHtml($f)])->all();
    }

    public function getSectionsTextAttribute(): array
    {
        return collect(ReportRichText::FIELDS)->mapWithKeys(fn ($f) => [$f => $this->sectionText($f)])->all();
    }

    public function sectionHtml(string $field): string
    {
        return ReportRichText::html($this->{$field}, $this->content_format);
    }

    public function sectionText(string $field): string
    {
        return ReportRichText::text($this->{$field}, $this->content_format);
    }

    protected function casts(): array
    {
        return ['hours' => 'float', 'user_id' => 'integer'];
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo($query, User $viewer)
    {
        return $query->whereIn('user_id', User::visibleTo($viewer)->select('id'))
            ->where(fn ($q) => $q->where('status', '!=', 'draft')->orWhere('user_id', $viewer->id));
    }
}
