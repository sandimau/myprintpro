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
@include('admin.jo.partials.print', [
    'joNota' => $nota,
    'joKonsumen' => $konsumen,
    'joMarketplace' => $marketplace,
    'joDeadline' => $deadline,
    'joTanggalOrder' => $tanggal,
    'joOnlineItems' => $sumber === 'online' ? $items : collect(),
    'joOfflineItems' => $sumber === 'offline' ? $items : collect(),
])
@endpush
