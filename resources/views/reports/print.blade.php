<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'Poppins', ui-sans-serif, system-ui, "Segoe UI", sans-serif; color: #111; font-size: 12px; margin: 0; }
        header { border-bottom: 2px solid #f97316; padding-bottom: 10px; margin-bottom: 14px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; }
        td.num { text-align: right; font-variant-numeric: tabular-nums; }
        tfoot td { font-weight: 700; background: #fafafa; }
        .toolbar { margin: 12px 0; }
        button { font: inherit; padding: 8px 14px; border: 0; border-radius: 6px; background: #f97316; color: #fff; font-weight: 700; cursor: pointer; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Cetak / Simpan PDF</button>
        <span class="muted">Di dialog cetak, pilih "Save as PDF".</span>
    </div>

    <header>
        <h1>{{ $title }}</h1>
        <div class="muted">{{ $siteName }} — {{ $subtitle }}</div>
        <div class="muted">Dibuat {{ $generatedAt->format('d/m/Y H:i') }}</div>
    </header>

    <table>
        <thead>
            <tr>
                @foreach($header as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $index => $value)
                        <td class="{{ $index === 0 ? '' : 'num' }}">
                            @if($index === 0)
                                {{ $value }}
                            @else
                                {{ number_format((int) $value, 0, ',', '.') }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($header) }}">Tidak ada data pada rentang tanggal ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
