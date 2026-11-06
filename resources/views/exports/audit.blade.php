<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Log audit</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 9px; color: #1f2a1f; margin: 18px; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        p.meta { margin: 0 0 10px; color: #5b6b5b; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #cfd8cf; padding: 3px 4px; text-align: left; vertical-align: top; word-wrap: break-word; }
        th { background: #eef4ec; }
        code { font-size: 7px; }
    </style>
</head>
<body>
    <h1>Log audit {{ config('document_templates.company_name') }}</h1>
    <p class="meta">
        Dicetak {{ $printedAt->format('d/m/Y H:i') }} WIB oleh {{ $printedBy }} · {{ $entries->count() }} entri
        @foreach (array_filter($filters) as $key => $value) · {{ $key }}: {{ $value }} @endforeach
    </p>
    <table>
        <thead>
            <tr><th style="width:11%">Waktu (WIB)</th><th style="width:12%">Pelaku</th><th style="width:14%">Aksi</th><th style="width:9%">Tiket</th><th style="width:7%">Sumber</th><th style="width:31%">Perubahan</th><th style="width:16%">Hash</th></tr>
        </thead>
        <tbody>
            @foreach ($entries as $entry)
                <tr>
                    <td>{{ $entry->created_at->format('d/m/Y H:i:s') }}</td>
                    <td>{{ $entry->actor_name ?? 'Sistem' }}@if ($entry->actor_role)<br>{{ $entry->actor_role }}@endif</td>
                    <td>{{ $entry->event }}@if ($entry->reason)<br>Alasan: {{ $entry->reason }}@endif</td>
                    <td>{{ $entry->ticket_code }}</td>
                    <td>{{ $entry->source }}</td>
                    <td><code>{{ json_encode($entry->attribute_changes?->get('attributes', []) ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</code></td>
                    <td><code>{{ $entry->hash }}</code></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
