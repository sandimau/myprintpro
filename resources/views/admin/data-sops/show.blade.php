@extends('layouts.app')

@section('title', $dataSop->judul)

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">{{ html_entity_decode($dataSop->judul, ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</h5>
                <small class="text-muted">Bagian: {{ $dataSop->bagian?->nama ?? '-' }} · {{ $dataSop->global ? 'Global' : 'Internal' }}</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ $dataSop->bagian ? route('data-sops.bagian', $dataSop->bagian->slug) : route('data-sops.index') }}" class="btn btn-outline-secondary">Kembali</a>
                @can('sop_manage')
                    <a href="{{ route('data-sops.edit', $dataSop) }}" class="btn btn-primary">Edit</a>
                    <form method="POST" action="{{ route('data-sops.destroy', $dataSop) }}" onsubmit="return confirm('Hapus SOP ini?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger">Hapus</button>
                    </form>
                @endcan
            </div>
        </div>
        <div class="card-body sop-content" style="overflow-wrap: anywhere; overflow-x: auto">
            @if ($dataSop->isi === strip_tags($dataSop->isi))
                {!! nl2br(e($dataSop->isi)) !!}
            @else
                {!! \App\Support\SopHtml::clean($dataSop->isi) !!}
            @endif
        </div>
    </div>
@endsection

@push('after-styles')
    <style>.sop-content img, .sop-content table { max-width: 100%; height: auto; } .sop-content table, .sop-content td, .sop-content th { border: 1px solid #adb5bd; padding: .4rem; }</style>
@endpush
