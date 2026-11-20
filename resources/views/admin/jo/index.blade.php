@extends('layouts.app')

@section('title', 'JO')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">JO — Antrean Produksi</h1>
        @role('super|admin')
            <a href="{{ route('jo.settings') }}" class="btn btn-outline-secondary btn-sm">Pengaturan status</a>
        @endrole
    </div>

    @if ($statuses->isEmpty())
        <div class="alert alert-warning">Belum ada status JO yang dipilih. Hubungi admin untuk mengatur status JO.</div>
    @else
        <p class="text-muted">Status JO: {{ $statuses->implode(', ') }}</p>
    @endif

    <div class="row g-2 mb-3">
        @foreach ($counts as $label => $count)
            <div class="col-auto"><span class="badge bg-secondary p-2">{{ ucfirst($label) }}: {{ $count }}</span></div>
        @endforeach
    </div>

    <form method="GET" action="{{ route('jo.index') }}" class="row g-2 mb-3">
        <div class="col-md-5">
            <label class="visually-hidden" for="jo-cari">Cari nota atau konsumen</label>
            <input id="jo-cari" name="cari" value="{{ $cari }}" class="form-control" placeholder="Cari nota / konsumen">
        </div>
        <div class="col-md-3">
            <label class="visually-hidden" for="jo-sumber">Sumber</label>
            <select id="jo-sumber" name="sumber" class="form-select">
                <option value="all">Semua sumber</option>
                <option value="offline" @selected($sumber === 'offline')>Offline</option>
                <option value="online" @selected($sumber === 'online')>Online</option>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-primary" type="submit">Tampilkan</button></div>
    </form>

    <div class="card"><div class="table-responsive">
        <table class="table table-striped align-middle mb-0">
            <thead><tr><th>Order</th><th>Item</th></tr></thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>
                            <a href="{{ route($order['sumber'] === 'online' ? 'jo.online' : 'jo.offline', $order['id']) }}">
                                <span class="badge bg-{{ $order['sumber'] === 'online' ? 'info' : 'secondary' }}">{{ $order['label'] }}</span>
                                {{ $order['nota'] ?: '#'.$order['id'] }}
                            </a><br>
                            <strong>{{ $order['konsumen'] }}</strong><br>
                            <small>Rp {{ number_format((float) $order['total'], 0, ',', '.') }}</small>
                        </td>
                        <td>
                            @foreach ($order['items'] as $item)
                                <div class="mb-1">
                                    {{ $item->produk?->namaLengkap ?? $item->produk?->nama ?? '-' }}
                                    @if ($item->tema) / {{ $item->tema }} @endif
                                    &times;{{ $item->jumlah }}
                                    <span class="badge bg-primary">{{ $item->produksi?->nama ?? '-' }}</span>
                                    @if ($item->proses?->nama ?? false) <small>{{ $item->proses->nama }}</small> @endif
                                    @php $deadline = $order['sumber'] === 'offline' ? ($item->deadline ?? $item->deathline ?? null) : $order['deadline']; @endphp
                                    @if ($deadline && \Illuminate\Support\Carbon::parse($deadline)->year >= 2010 && \Illuminate\Support\Carbon::parse($deadline)->isPast() && !\Illuminate\Support\Carbon::parse($deadline)->isToday())
                                        <span class="badge bg-danger">Telat {{ \Illuminate\Support\Carbon::parse($deadline)->startOfDay()->diffInDays(now()->startOfDay()) }} hari</span>
                                    @elseif ($deadline && \Illuminate\Support\Carbon::parse($deadline)->isToday())
                                        <span class="badge bg-warning text-dark">Hari ini</span>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-center text-muted py-4">Tidak ada order JO sesuai saringan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div>
</div>
@endsection
