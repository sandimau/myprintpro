@extends('layouts.app')

@section('title', 'Edit Bagian SOP')

@section('content')
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Edit Bagian SOP</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('data-sops.bagian.update', $sopBagian) }}">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="nama">Nama Bagian</label>
                    <input id="nama" name="nama" required maxlength="100" value="{{ old('nama', $sopBagian->nama) }}" class="form-control @error('nama') is-invalid @enderror">
                    @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <button class="btn btn-primary">Simpan</button>
                <a href="{{ route('data-sops.bagian', $sopBagian->slug) }}" class="btn btn-outline-secondary">Batal</a>
            </form>
            @if (!$sopBagian->sops()->exists())
                <form method="POST" action="{{ route('data-sops.bagian.destroy', $sopBagian) }}" class="mt-4" onsubmit="return confirm('Hapus bagian kosong ini?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger">Hapus Bagian</button>
                </form>
            @endif
        </div>
    </div>
@endsection
