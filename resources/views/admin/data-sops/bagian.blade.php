@extends('layouts.app')

@section('title', 'SOP ' . $bagian->nama)

@section('content')
    <div class="card sop-list">
        <div class="sop-list__header">
            <div>
                <a href="{{ route('data-sops.index') }}" class="sop-list__back"><i class="bx bx-left-arrow-alt" aria-hidden="true"></i> Semua bagian</a>
                <h1>{{ $bagian->nama }}</h1>
                <p>Panduan dan aturan kerja untuk bagian {{ $bagian->nama }}.</p>
            </div>
            @can('sop_manage')
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('data-sops.bagian.edit', $bagian) }}" class="btn btn-outline-secondary">Kelola Bagian</a>
                    <a href="{{ route('data-sops.create', ['sop_bagian_id' => $bagian->id]) }}" class="btn btn-primary"><i class="bx bx-plus" aria-hidden="true"></i> Tambah Aturan</a>
                </div>
            @endcan
        </div>
        <div class="card-body sop-list__body">
            @include('layouts.includes.messages')
            <div class="sop-list__tools">
                <div><strong>Daftar aturan</strong><span>{{ $sops->total() }} ditemukan</span></div>
                <form method="GET" action="{{ route('data-sops.bagian', $bagian->slug) }}" class="sop-list__search">
                    <label for="sop-cari" class="visually-hidden">Cari aturan</label>
                    <input id="sop-cari" class="form-control" name="cari" value="{{ request('cari') }}" placeholder="Cari aturan...">
                    <button class="btn btn-outline-primary" type="submit">Cari</button>
                </form>
            </div>
            <div class="sop-list__items">
                @forelse ($sops as $sop)
                    <div class="sop-list__item">
                        <a class="sop-list__item-link" href="{{ route('data-sops.show', $sop) }}">
                            <span class="sop-list__item-icon" aria-hidden="true"><i class="bx bx-file-blank"></i></span>
                            <span>{{ html_entity_decode($sop->judul, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</span>
                            <i class="bx bx-chevron-right sop-list__item-arrow" aria-hidden="true"></i>
                        </a>
                        @can('sop_manage')
                            <form method="POST" action="{{ route('data-sops.destroy', $sop) }}" onsubmit="return confirm('Hapus aturan ini?')">
                                @csrf @method('DELETE')
                                <button class="sop-list__delete" title="Hapus aturan {{ $sop->judul }}" aria-label="Hapus aturan {{ $sop->judul }}" type="submit"><i class="bx bx-trash" aria-hidden="true"></i></button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <div class="sop-list__empty">{{ request()->filled('cari') ? 'Tidak ada aturan yang cocok dengan pencarian.' : 'Belum ada aturan di bagian ini.' }}</div>
                @endforelse
            </div>
            <div class="mt-3">{{ $sops->links() }}</div>
        </div>
    </div>
@endsection

@push('after-styles')
    <style>
        .sop-list { overflow: hidden; }
        .sop-list__header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; padding: 1.6rem 2rem; border-bottom: 1px solid var(--app-border); background: linear-gradient(110deg, var(--app-primary-light), var(--app-surface) 68%); }
        .sop-list__back { display: inline-flex; align-items: center; gap: .2rem; margin-bottom: .6rem; color: var(--app-primary); font-size: .82rem; font-weight: 600; text-decoration: none; }
        .sop-list__back i { font-size: 1.25rem; }
        .sop-list__header h1 { margin: 0; color: var(--app-text); font-size: 1.5rem; font-weight: 700; }
        .sop-list__header p { margin: .35rem 0 0; color: var(--app-text-muted); font-size: .88rem; }
        .sop-list__body { padding: 1.5rem 2rem 2rem; }
        .sop-list__tools { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.1rem; }
        .sop-list__tools strong { display: block; font-size: .98rem; }
        .sop-list__tools span { color: var(--app-text-muted); font-size: .8rem; }
        .sop-list__search { display: flex; gap: .5rem; width: min(100%, 360px); }
        .sop-list__items { overflow: hidden; border: 1px solid var(--app-border); border-radius: .8rem; }
        .sop-list__item { display: flex; align-items: center; border-bottom: 1px solid var(--app-border); }
        .sop-list__item:last-child { border-bottom: 0; }
        .sop-list__item:hover { background: var(--app-primary-light); }
        .sop-list__item-link { display: flex; flex: 1; min-width: 0; align-items: center; gap: .9rem; padding: .85rem 1rem; color: var(--app-text); font-weight: 600; text-decoration: none; overflow-wrap: anywhere; }
        .sop-list__item-link:hover { color: var(--app-primary); text-decoration: none; }
        .sop-list__item-icon { display: grid; flex: 0 0 32px; height: 32px; place-items: center; border-radius: .5rem; background: var(--app-primary-light); color: var(--app-primary); font-size: 1.2rem; }
        .sop-list__item-arrow { margin-left: auto; color: var(--app-text-muted); font-size: 1.25rem; }
        .sop-list__delete { display: grid; width: 34px; height: 34px; place-items: center; margin-right: .75rem; border: 0; border-radius: .5rem; background: transparent; color: var(--app-text-muted); font-size: 1.15rem; }
        .sop-list__delete:hover, .sop-list__delete:focus-visible { background: rgba(239, 68, 68, .12); color: #ef4444; }
        .sop-list__empty { padding: 2rem; color: var(--app-text-muted); text-align: center; }
        @media (max-width: 640px) { .sop-list__header, .sop-list__body { padding: 1.25rem; } .sop-list__search { width: 100%; } }
    </style>
@endpush
