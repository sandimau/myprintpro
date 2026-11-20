@extends('layouts.app')

@section('title', 'Detail JO')

@section('content')
<div class="container-fluid py-3">
    <a href="{{ route('jo.index') }}" class="btn btn-outline-secondary btn-sm mb-3">&larr; Kembali ke JO</a>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">JO {{ $nota }}</h1>
        @if ($sumber === 'online' && $items->isNotEmpty())
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="cetakSemuaJo()">Cetak Semua JO</button>
        @endif
    </div>
    <div class="card mb-3"><div class="card-body">
        <div>Konsumen: <strong>{{ $konsumen ?: '-' }}</strong></div>
        <div>Total: Rp {{ number_format((float) $total, 0, ',', '.') }}</div>
        <div>Deadline: {{ $deadline ? \Illuminate\Support\Carbon::parse($deadline)->format('d/m/Y') : '-' }}</div>
    </div></div>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th>Produk</th><th>Tema</th><th>Jumlah</th><th>Status</th><th>Keterangan</th><th>Cetak</th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td>{{ $item->produk?->namaLengkap ?? $item->produk?->nama ?? '-' }}</td>
                <td>{{ $item->tema ?: '-' }}</td>
                <td>{{ $item->jumlah }}</td>
                <td>{{ $item->produksi?->nama ?? '-' }}</td>
                <td>{{ $item->keterangan ?: '-' }}</td>
                <td><button type="button" class="btn btn-outline-primary btn-sm" onclick="cetakJo({{ $loop->index }})">JO</button></td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>
</div>
@endsection

@push('after-scripts')
@php
    // Kirim hanya data yang dipakai slip; tidak ada aksi tulis ke server.
    $printOrder = [
        'nota' => $nota,
        'klien' => $konsumen,
        'marketplace' => $marketplace,
        'deadline' => $deadline,
        'tanggal' => $tanggal,
    ];
    $printItems = $items->map(fn ($item) => [
        'produk' => $item->produk?->namaLengkap ?? $item->produk?->nama ?? '-',
        'tema' => $item->tema,
        'jumlah' => $item->jumlah,
        'keterangan' => $item->keterangan,
        'deadline' => $sumber === 'offline' ? ($item->deadline ?? $item->deathline ?? null) : $deadline,
        'gambar' => $item->gambar
            ? asset('uploads/' . ($sumber === 'online' ? 'projectMp' : 'order') . '/' . basename($item->gambar))
            : null,
    ])->values();
@endphp
<script>
    const joOrder = {{ \Illuminate\Support\Js::from($printOrder) }};
    const joItems = {{ \Illuminate\Support\Js::from($printItems) }};
    const joEsc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[char]));
    const joTanggal = value => {
        if (!value || String(value).startsWith('1970-')) return '-';
        const date = new Date(String(value).slice(0, 10) + 'T00:00:00');
        return Number.isNaN(date.getTime()) ? '-' : new Intl.DateTimeFormat('id-ID', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
        }).format(date);
    };

    function bukaCetakJo(content) {
        const popup = window.open('', '_blank', 'width=1000,height=750');
        if (!popup) {
            alert('Jendela cetak diblokir browser. Izinkan popup untuk halaman ini.');
            return;
        }
        popup.document.open();
        popup.document.write(`<!doctype html><html lang="id"><head><meta charset="utf-8">
            <title>JO ${joEsc(joOrder.nota)}</title><style>
            @page { margin: 6mm; }
            body { font: 14px Arial, sans-serif; color: #000; }
            .bar { margin-bottom: 5mm; }
            .bar button { padding: 4px 12px; margin-right: 6px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000; padding: 6px; vertical-align: top; overflow-wrap: anywhere; }
            .slip { display: flex; gap: 6mm; break-inside: avoid; }
            .identitas { flex: 1 1 90mm; min-width: 0; }
            .spek { flex: 0 1 60mm; min-width: 0; border: 1px solid #000; padding: 6px; }
            .gambar { flex: 2 1 80mm; min-width: 0; height: 100mm; border: 1px solid #000;
                display: flex; align-items: center; justify-content: center; }
            .gambar img { max-width: 100%; max-height: 100%; object-fit: contain; }
            .catatan { margin-top: 6mm; color: #c00; font-size: 20px; font-weight: bold; }
            .isian { display: inline-block; min-width: 30mm; border-bottom: 1px dotted #999; }
            .ringkasan { display: grid; grid-template-columns: 42% 1fr; gap: 6mm; }
            .jumlah { width: 24mm; text-align: right; }
            @media print { .bar { display: none; } .isian { border: 0; }
                .slip + .slip { break-before: page; } }
            </style></head><body><div class="bar"><button type="button" onclick="window.print()">Cetak</button>
            <button type="button" onclick="window.close()">Tutup</button></div>${content}</body></html>`);
        popup.document.close();
    }

    function cetakJo(index) {
        const item = joItems[index];
        if (!item) return;
        const marketplace = joOrder.marketplace
            ? `<tr><th>Marketplace</th><td>${joEsc(joOrder.marketplace)}</td></tr>` : '';
        const gambar = item.gambar
            ? `<img src="${joEsc(item.gambar)}" alt="Gambar produk">` : 'Tanpa gambar';
        bukaCetakJo(`<div class="slip"><div class="identitas"><table>
            <tr><th>Klien</th><td><strong>${joEsc(joOrder.klien || '-')}</strong></td></tr>
            <tr><th>No Pesanan</th><td>${joEsc(joOrder.nota || '-')}</td></tr>${marketplace}
            <tr><th>Tema</th><td>${joEsc(item.tema || '-')}</td></tr>
            <tr><th>Jumlah</th><td><strong>${joEsc(item.jumlah)}</strong></td></tr>
            <tr><th>Deadline</th><td>${joEsc(joTanggal(item.deadline || joOrder.deadline))}</td></tr>
            </table><div class="catatan">Catatan: <span class="isian" contenteditable="true">${joEsc(item.keterangan || '')}</span></div>
            <p>ORDER DATE: ${joEsc(joTanggal(joOrder.tanggal))}</p></div>
            <div class="spek"><strong>${joEsc(item.produk)}</strong><hr>
            Lain: <span class="isian" contenteditable="true"></span></div>
            <div class="gambar">${gambar}</div></div>`);
    }

    function cetakSemuaJo() {
        if (!joItems.length) return;
        const jumlah = new Map();
        const tema = new Set();
        joItems.forEach(item => {
            const nama = String(item.produk || '-').trim();
            const key = nama.toLocaleLowerCase('id-ID');
            const previous = jumlah.get(key);
            jumlah.set(key, { nama, jumlah: Number(item.jumlah || 0) + (previous?.jumlah || 0) });
            if (item.tema) tema.add(String(item.tema).trim());
        });
        const rows = [...jumlah.values()].map(item =>
            `<tr><td>${joEsc(item.nama)}</td><td class="jumlah">${joEsc(item.jumlah)}</td></tr>`).join('');
        bukaCetakJo(`<div class="ringkasan"><table>
            <tr><th>Klien</th><td>${joEsc(joOrder.klien || '-')}</td></tr>
            <tr><th>No Pesanan</th><td>${joEsc(joOrder.nota || '-')}</td></tr>
            <tr><th>Marketplace</th><td>${joEsc(joOrder.marketplace || '-')}</td></tr>
            <tr><th>Tema</th><td>${joEsc([...tema].join(', ') || '-')}</td></tr>
            </table><table><thead><tr><th>Nama Produk</th><th>Jumlah</th></tr></thead>
            <tbody>${rows}</tbody></table></div>`);
    }
</script>
@endpush
