{{-- Cash/Bank Book balance summaries. --}}
<div class="book-kpis summary-grid summary-grid--compact">
    <x-stat-card label="Opening balance">@money($opening)</x-stat-card>
    <x-stat-card label="Money in">@money($charts['in_total'])</x-stat-card>
    <x-stat-card label="Money out">@money($charts['out_total'])</x-stat-card>
    <x-stat-card label="Closing balance">@money($balance)</x-stat-card>
</div>
