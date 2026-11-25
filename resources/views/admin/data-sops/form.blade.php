@extends('layouts.app')

@section('title', $dataSop->exists ? 'Edit SOP' : 'Tambah SOP')

@section('content')
    <div class="card">
        <div class="card-header"><h5 class="mb-0">{{ $dataSop->exists ? 'Edit SOP' : 'Tambah SOP' }}</h5></div>
        <div class="card-body">
            <form id="sop-form" method="POST" action="{{ $dataSop->exists ? route('data-sops.update', $dataSop) : route('data-sops.store') }}">
                @csrf
                @if ($dataSop->exists) @method('PUT') @endif
                <div class="mb-3">
                    <label class="form-label" for="judul">Judul</label>
                    <input id="judul" name="judul" required maxlength="255" class="form-control @error('judul') is-invalid @enderror" value="{{ old('judul', $dataSop->judul) }}">
                    @error('judul') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="sop_bagian_id">Bagian</label>
                    <select id="sop_bagian_id" name="sop_bagian_id" required class="form-select @error('sop_bagian_id') is-invalid @enderror">
                        <option value="">Pilih bagian</option>
                        @foreach ($bagians as $bagian)
                            <option value="{{ $bagian->id }}" @selected(old('sop_bagian_id', $dataSop->sop_bagian_id) == $bagian->id)>{{ $bagian->nama }}</option>
                        @endforeach
                    </select>
                    @error('sop_bagian_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="isi">Isi SOP</label>
                    <div id="sop-editor-ui" class="d-none">
                        <div class="sop-toolbar border rounded-top p-2 d-flex flex-wrap gap-1" role="toolbar" aria-label="Format isi SOP">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="formatBlock" data-value="p" title="Paragraf">Paragraf</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="formatBlock" data-value="h2" title="Judul bagian">Judul</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="bold" title="Tebal"><strong>B</strong></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="italic" title="Miring"><em>I</em></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="underline" title="Garis bawah"><u>U</u></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="insertUnorderedList" title="Daftar poin">• Daftar</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="insertOrderedList" title="Daftar nomor">1. Daftar</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="sop-link" title="Tambahkan tautan">Tautan</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="unlink" title="Hapus tautan">Lepas tautan</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-command="removeFormat" title="Hapus format teks">Hapus format</button>
                        </div>
                        <div id="sop-editor" class="sop-editor border border-top-0 rounded-bottom p-3" contenteditable="true" role="textbox" aria-label="Isi SOP" aria-multiline="true">{!! \App\Support\SopHtml::clean(old('isi', $dataSop->isi ?? '')) !!}</div>
                        <small class="text-muted">Klik area isi untuk mengedit. Sorot teks, lalu gunakan tombol format di atas.</small>
                    </div>
                    <textarea id="isi" name="isi" rows="14" required class="form-control @error('isi') is-invalid @enderror">{{ old('isi', $dataSop->isi) }}</textarea>
                    @error('isi') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="form-check mb-3">
                    <input type="hidden" name="global" value="0">
                    <input type="checkbox" class="form-check-input" name="global" id="global" value="1" @checked(old('global', $dataSop->global))>
                    <label for="global" class="form-check-label">Global</label>
                </div>
                <button class="btn btn-primary">Simpan</button>
                <a class="btn btn-outline-secondary" href="{{ route('data-sops.index') }}">Batal</a>
            </form>
        </div>
    </div>
@endsection

@push('after-styles')
    <style>
        .sop-editor { min-height: 360px; max-height: 65vh; overflow: auto; overflow-wrap: anywhere; white-space: pre-wrap; }
        .sop-editor:focus { outline: 2px solid #6ea8fe; outline-offset: -2px; }
        .sop-editor img, .sop-editor table { max-width: 100%; height: auto; }
        .sop-editor table, .sop-editor td, .sop-editor th { border: 1px solid #adb5bd; padding: .4rem; }
    </style>
@endpush

@push('after-scripts')
    <script>
        (() => {
            const form = document.getElementById('sop-form');
            const input = document.getElementById('isi');
            const editor = document.getElementById('sop-editor');
            const ui = document.getElementById('sop-editor-ui');
            if (!form || !editor || !input) return;

            ui.classList.remove('d-none');
            input.classList.add('d-none');
            input.required = false;

            ui.querySelectorAll('[data-command]').forEach((button) => {
                button.addEventListener('mousedown', (event) => event.preventDefault());
                button.addEventListener('click', () => {
                    editor.focus();
                    document.execCommand(button.dataset.command, false, button.dataset.value || null);
                });
            });

            document.getElementById('sop-link').addEventListener('click', () => {
                const selection = window.getSelection();
                const range = selection.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
                const url = prompt('Alamat tautan (https://...):');
                if (!url || !/^(https?:\/\/|mailto:|\/(?!\/)|#)/i.test(url.trim())) return;
                if (range) {
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
                document.execCommand('createLink', false, url.trim());
            });

            form.addEventListener('submit', (event) => {
                if (!editor.textContent.trim()) {
                    event.preventDefault();
                    editor.focus();
                    alert('Isi SOP wajib diisi.');
                    return;
                }
                input.value = editor.innerHTML;
            });
        })();
    </script>
@endpush
