<!DOCTYPE html>
<html><head><meta charset="UTF-8"><style>
body{font-family:Arial,sans-serif;color:#231d19;background:#fff}table{border-collapse:collapse;width:100%}.report-head td{border:0}.logo{width:78px;height:auto}.company{font-size:19pt;font-weight:700;color:#241d18}.tagline{font-size:8pt;letter-spacing:2px;color:#d9862f;font-weight:700}.title{font-size:16pt;font-weight:700;color:#971f2b;padding:14px 0 3px}.subtitle{font-size:10pt;color:#725b49;padding-bottom:12px}.meta{font-size:8pt;color:#78695e;text-align:right}.data th{background:#9f2730;color:#fff;border:1px solid #7e1720;padding:8px 7px;text-align:left;font-size:9pt}.data td{border:1px solid #dfd3c7;padding:7px;font-size:9pt;vertical-align:top}.data tr:nth-child(even) td{background:#fff8ee}.data tr.section td{background:#2a211b;color:#fff;font-weight:700}.data tr.heading td{background:#f4dfc3;color:#7e3515;font-weight:700}.data tr.subtotal td{background:#f8ead7;font-weight:700;border-top:2px solid #d9862f}.data tr.total td{background:#f3d8d7;font-weight:700;border-top:2px solid #9f2730}.data tr.final td{background:#9f2730;color:#fff;font-weight:700}.footer{margin-top:14px;border-top:2px solid #d9862f;padding-top:7px;font-size:8pt;color:#78695e}
</style></head><body>
<table class="report-head"><tr><td style="width:90px">
@if($logo)
<img class="logo" src="{{ $logo }}" alt="Brooks Agro Farm">
@endif
</td><td><div class="company">BROOKS AGRO FARM LTD.</div><div class="tagline">POULTRY &bull; LIVESTOCK &bull; FOOD CROPS</div><div class="title">{{ $title }}</div><div class="subtitle">{{ $subtitle }}</div></td><td class="meta">Generated {{ $generatedAt->format('d M Y, H:i') }}<br>East Africa Time
@if($generatedBy)
<br>Prepared by {{ $generatedBy }}
@endif
</td></tr></table>
<table class="data"><thead><tr>@foreach($headers as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>
@forelse($rows as $row)<tr class="{{ $row['style'] }}">@foreach($row['cells'] as $cell)<td>{{ $cell }}</td>@endforeach</tr>@empty<tr><td colspan="{{ max(1,count($headers)) }}">No records for this report period.</td></tr>@endforelse
</tbody></table><div class="footer">Brooks Agro Farm Ltd. &bull; Official system-generated report</div>
</body></html>
