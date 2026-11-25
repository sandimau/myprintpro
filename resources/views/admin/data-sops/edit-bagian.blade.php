@extends('layouts.app')

@section('title', 'Edit Bagian SOP')

@section('content')
    <div class="card mb-4" style="max-width: 740px">
        <div class="card-header py-3">
            <h5 class="mb-1">Kelola Bagian SOP</h5>
            <small class="text-muted">Ubah nama bagian atau kelola aturan di dalamnya.</small>
        </div>
        <div class="card-body p-4">
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
        </div>
    </div>
    @php($jumlahAturan = $sopBagian->sops()->count())
    <div class="card border-danger mb-4" id="hapus-bagian" style="max-width: 740px; scroll-margin-top: 1.5rem">
        <div class="card-body p-4">
            <h5 class="text-danger mb-2">Hapus bagian</h5>
            <p class="text-muted mb-4">
                @if ($jumlahAturan)
                    Bagian <strong>{{ $sopBagian->nama }}</strong> berisi {{ $jumlahAturan }} aturan. Semua aturan tersebut ikut dihapus jika bagian ini dihapus.
                @else
                    Bagian <strong>{{ $sopBagian->nama }}</strong> belum memiliki aturan.
                @endif
            </p>
            <form method="POST" action="{{ route('data-sops.bagian.destroy', $sopBagian) }}"
                onsubmit="return confirm('Yakin menghapus bagian ini beserta seluruh aturannya?')">
                @csrf @method('DELETE')
                <input type="hidden" name="hapus_aturan" value="1">
                <button class="btn btn-outline-danger">Hapus bagian{{ $jumlahAturan ? ' beserta aturannya' : '' }}</button>
            </form>
        </div>
    </div>
@endsection
