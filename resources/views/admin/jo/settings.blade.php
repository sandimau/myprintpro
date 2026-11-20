@extends('layouts.app')

@section('title', 'Pengaturan JO')

@section('content')
<div class="container-fluid py-3">
    <a href="{{ route('jo.index') }}" class="btn btn-outline-secondary btn-sm mb-3">&larr; Kembali ke JO</a>
    <h1 class="h4">Pengaturan Status JO</h1>
    <p>Pilih status produksi yang akan ditampilkan pada antrean JO offline dan online.</p>
    @include('layouts.includes.messages')
    <form action="{{ route('jo.settings.save') }}" method="POST" class="card">
        @csrf
        <div class="card-body">
            @error('status_ids') <div class="alert alert-danger">{{ $message }}</div> @enderror
            @error('status_ids.*') <div class="alert alert-danger">{{ $message }}</div> @enderror
            @foreach ($statuses as $status)
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="status_ids[]" value="{{ $status->id }}"
                        id="jo-status-{{ $status->id }}" @checked(in_array($status->id, old('status_ids', $selected)))>
                    <label class="form-check-label" for="jo-status-{{ $status->id }}">{{ $status->grup }} / {{ $status->nama }}</label>
                </div>
            @endforeach
        </div>
        <div class="card-footer"><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
</div>
@endsection
