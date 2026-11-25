@extends('layouts.app')

@section('title', 'Data SOP')

@section('content')
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Bagian SOP</h5>
            @can('sop_manage')
                <a href="{{ route('data-sops.create') }}" class="btn btn-primary">Tambah SOP</a>
            @endcan
        </div>
        <div class="card-body">
            @include('layouts.includes.messages')
            @can('sop_manage')
                <form method="POST" action="{{ route('data-sops.bagian.store') }}" class="row g-2 mb-4">
                    @csrf
                    <div class="col-md-6">
                        <input name="nama" maxlength="100" required value="{{ old('nama') }}" class="form-control @error('nama') is-invalid @enderror" placeholder="Nama bagian baru">
                        @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-auto"><button class="btn btn-outline-primary">Tambah Bagian</button></div>
                </form>
            @endcan
            <div class="row g-3">
                @forelse ($bagians as $item)
                    <div class="col-md-6 col-xl-4">
                        <a class="card h-100 text-decoration-none text-body border" href="{{ route('data-sops.bagian', $item->slug) }}">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <strong>{{ $item->nama }}</strong>
                                <span class="badge bg-primary">{{ $item->sops_count }} aturan</span>
                            </div>
                        </a>
                    </div>
                @empty
                    <p class="text-muted mb-0">Belum ada data SOP.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
