@extends('layouts.app')

@section('title', 'SOP ' . $bagian->nama)

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">SOP {{ $bagian->nama }}</h5>
            <div class="d-flex gap-2">
                @can('sop_manage')
                    <a href="{{ route('data-sops.bagian.edit', $bagian) }}" class="btn btn-outline-primary">Edit Bagian</a>
                @endcan
                <a href="{{ route('data-sops.index') }}" class="btn btn-outline-secondary">Semua Bagian</a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('data-sops.bagian', $bagian->slug) }}" class="row g-2 mb-3">
                <div class="col-md-10"><input class="form-control" name="cari" value="{{ request('cari') }}" placeholder="Cari aturan di bagian ini"></div>
                <div class="col-md-2"><button class="btn btn-outline-primary w-100">Cari</button></div>
            </form>
            <div class="list-group mb-3">
                @forelse ($sops as $sop)
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                        href="{{ route('data-sops.show', $sop) }}">
                        <span>{{ html_entity_decode($sop->judul, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span>
                        <span aria-hidden="true">&rsaquo;</span>
                    </a>
                @empty
                    <div class="list-group-item text-muted">Tidak ada aturan yang cocok.</div>
                @endforelse
            </div>
            {{ $sops->links() }}
        </div>
    </div>
@endsection
