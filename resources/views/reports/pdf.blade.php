<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $result->title }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #152033; }
        .school { color: #566275; font-size: 9px; text-transform: none; margin: 0; }
        h1 { font-size: 16px; color: #1e4e8c; margin: 2px 0 6px; }
        .meta { margin: 0 0 2px; color: #566275; }
        .summary { margin: 10px 0; padding: 6px 8px; background: #e8eff8; }
        .summary span { margin-right: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #1e4e8c; color: #fff; text-align: left; padding: 5px 6px; font-weight: bold; }
        td { padding: 4px 6px; border-bottom: 1px solid #dde3ea; vertical-align: top; }
        tr:nth-child(even) td { background: #f4f6f9; }
        .empty { padding: 12px; color: #566275; }
        .footer { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #566275; }
    </style>
</head>
<body>
    <p class="school">{{ $school }}</p>
    <h1>{{ $result->title }}</h1>
    @foreach ($result->subtitle as $line)
        <p class="meta">{{ $line }}</p>
    @endforeach

    @if ($result->summary)
        <div class="summary">
            @foreach ($result->summary as $item)
                <span><strong>{{ $item['label'] }}:</strong> {{ $item['value'] }}</span>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($result->columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($result->rows as $row)
                <tr>
                    @foreach ($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="empty" colspan="{{ count($result->columns) }}">No records match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Generated {{ $generatedAt }}</p>
</body>
</html>
