@extends('layouts.app')

@section('title', 'Data SOP')

@section('content')
    <div class="card sop-hub">
        <div class="sop-hub__header">
            <div>
                <span class="sop-hub__eyebrow">PUSAT DOKUMEN</span>
                <h1 class="sop-hub__title">Standar Operasional Prosedur</h1>
                <p class="sop-hub__subtitle">Temukan panduan kerja berdasarkan bagian yang Anda butuhkan.</p>
            </div>
            @can('sop_manage')
                <a href="{{ route('data-sops.create') }}" class="btn btn-primary sop-hub__add">
                    <i class="bx bx-plus" aria-hidden="true"></i> Tambah SOP
                </a>
            @endcan
        </div>

        <div class="card-body sop-hub__body">
            @include('layouts.includes.messages')
            <div class="sop-hub__intro">
                <div>
                    <h2>Jelajahi bagian</h2>
                    <p>{{ $bagians->count() }} bagian &middot; {{ $bagians->sum('sops_count') }} aturan tersedia</p>
                </div>
                @can('sop_manage')
                    <details class="sop-hub__new" @if ($errors->has('nama') && old('_sop_action') !== 'edit') open @endif>
                        <summary><i class="bx bx-folder-plus" aria-hidden="true"></i> Bagian baru</summary>
                        <form method="POST" action="{{ route('data-sops.bagian.store') }}" class="sop-hub__new-form">
                            @csrf
                            <label for="sop-nama-bagian" class="form-label">Nama bagian</label>
                            <div class="d-flex flex-wrap gap-2">
                                <input id="sop-nama-bagian" name="nama" maxlength="100" required
                                    value="{{ old('_sop_action') === 'edit' ? '' : old('nama') }}" class="form-control {{ $errors->has('nama') && old('_sop_action') !== 'edit' ? 'is-invalid' : '' }}"
                                    placeholder="Contoh: Quality Control">
                                <button class="btn btn-primary" type="submit">Simpan</button>
                            </div>
                            @if (old('_sop_action') !== 'edit')
                                @error('nama') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            @endif
                        </form>
                    </details>
                @endcan
            </div>

            <div class="sop-hub__grid">
                @forelse ($bagians as $item)
                    <article class="sop-hub__section">
                        <a class="sop-hub__section-main" href="{{ route('data-sops.bagian', $item->slug) }}">
                            <span class="sop-hub__icon" aria-hidden="true"><i class="bx bx-folder"></i></span>
                            <span class="sop-hub__section-name">{{ $item->nama }}</span>
                            <span class="sop-hub__count">{{ $item->sops_count }} aturan</span>
                            <span class="sop-hub__section-footer">
                                <span>Lihat aturan</span>
                                <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                            </span>
                        </a>
                        @can('sop_manage')
                            <details class="sop-hub__manage">
                                <summary aria-label="Pilihan untuk bagian {{ $item->nama }}" title="Kelola bagian {{ $item->nama }}">
                                    <i class="bx bx-dots-horizontal-rounded" aria-hidden="true"></i>
                                </summary>
                                <div class="sop-hub__menu">
                                    <button type="button" data-sop-edit data-id="{{ $item->id }}"
                                        data-name="{{ $item->nama }}" data-url="{{ route('data-sops.bagian.update', $item) }}">
                                        <i class="bx bx-edit-alt" aria-hidden="true"></i> Edit bagian
                                    </button>
                                    <button type="button" class="sop-hub__menu-danger" data-sop-delete
                                        data-name="{{ $item->nama }}" data-count="{{ $item->sops_count }}"
                                        data-url="{{ route('data-sops.bagian.destroy', $item) }}">
                                        <i class="bx bx-trash" aria-hidden="true"></i> Hapus bagian
                                    </button>
                                </div>
                            </details>
                        @endcan
                    </article>
                @empty
                    <div class="sop-hub__empty">
                        <i class="bx bx-folder-open" aria-hidden="true"></i>
                        <h3>Belum ada bagian SOP</h3>
                        <p>Tambahkan bagian untuk mulai mengelompokkan aturan kerja.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
    @can('sop_manage')
        <dialog id="sop-edit-dialog" class="sop-hub__dialog" aria-labelledby="sop-edit-title">
            <div class="sop-hub__dialog-head">
                <span class="sop-hub__dialog-icon"><i class="bx bx-edit-alt" aria-hidden="true"></i></span>
                <button type="button" class="sop-hub__dialog-close" data-sop-close aria-label="Tutup">&times;</button>
            </div>
            <h2 id="sop-edit-title">Edit bagian</h2>
            <p>Perbarui nama bagian. Seluruh aturan di dalamnya tetap tersimpan.</p>
            <form id="sop-edit-form" method="POST">
                @csrf @method('PUT')
                <input type="hidden" name="_sop_action" value="edit">
                <input type="hidden" name="_sop_bagian_id" id="sop-edit-id">
                <label class="form-label" for="sop-edit-name">Nama bagian</label>
                <input id="sop-edit-name" name="nama" maxlength="100" required class="form-control {{ $errors->has('nama') && old('_sop_action') === 'edit' ? 'is-invalid' : '' }}">
                @if (old('_sop_action') === 'edit')
                    @error('nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                @endif
                <div class="sop-hub__dialog-actions">
                    <button type="button" class="btn btn-outline-secondary" data-sop-close>Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan perubahan</button>
                </div>
            </form>
        </dialog>

        <dialog id="sop-delete-dialog" class="sop-hub__dialog" aria-labelledby="sop-delete-title">
            <div class="sop-hub__dialog-head">
                <span class="sop-hub__dialog-icon sop-hub__dialog-icon--danger"><i class="bx bx-trash" aria-hidden="true"></i></span>
                <button type="button" class="sop-hub__dialog-close" data-sop-close aria-label="Tutup">&times;</button>
            </div>
            <h2 id="sop-delete-title">Hapus bagian?</h2>
            <p id="sop-delete-description"></p>
            <form id="sop-delete-form" method="POST">
                @csrf @method('DELETE')
                <input type="hidden" name="hapus_aturan" value="1">
                <div class="sop-hub__dialog-actions">
                    <button type="button" class="btn btn-outline-secondary" data-sop-close>Batal</button>
                    <button type="submit" class="btn btn-danger">Ya, hapus bagian</button>
                </div>
            </form>
        </dialog>
    @endcan
@endsection

@push('after-styles')
    <style>
        .sop-hub { overflow: visible; }
        .sop-hub__header { display: flex; justify-content: space-between; align-items: center; gap: 1.5rem; flex-wrap: wrap; padding: 1.75rem 2rem; border-bottom: 1px solid var(--app-border); background: linear-gradient(110deg, var(--app-primary-light), var(--app-surface) 68%); }
        .sop-hub__eyebrow { display: block; margin-bottom: .45rem; color: var(--app-primary); font-size: .7rem; font-weight: 800; letter-spacing: .13em; }
        .sop-hub__title { margin: 0; color: var(--app-text); font-size: clamp(1.25rem, 2vw, 1.65rem); font-weight: 700; }
        .sop-hub__subtitle { margin: .45rem 0 0; color: var(--app-text-muted); font-size: .9rem; }
        .sop-hub__add { display: inline-flex; align-items: center; gap: .35rem; white-space: nowrap; }
        .sop-hub__add i { font-size: 1.2rem; }
        .sop-hub__body { padding: 1.75rem 2rem 2rem; }
        .sop-hub__intro { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.4rem; }
        .sop-hub__intro h2 { margin: 0 0 .25rem; color: var(--app-text); font-size: 1.05rem; font-weight: 700; }
        .sop-hub__intro p { margin: 0; color: var(--app-text-muted); font-size: .86rem; }
        .sop-hub__new { position: relative; z-index: 2; }
        .sop-hub__new summary { display: inline-flex; align-items: center; gap: .45rem; padding: .55rem .85rem; border: 1px solid var(--app-border); border-radius: .6rem; color: var(--app-primary); background: var(--app-surface); font-size: .85rem; font-weight: 600; cursor: pointer; list-style: none; }
        .sop-hub__new summary::-webkit-details-marker { display: none; }
        .sop-hub__new summary:hover, .sop-hub__new[open] summary { border-color: var(--app-primary); background: var(--app-primary-light); }
        .sop-hub__new summary i { font-size: 1.2rem; }
        .sop-hub__new-form { position: absolute; right: 0; top: calc(100% + .5rem); width: min(360px, 85vw); padding: 1rem; border: 1px solid var(--app-border); border-radius: .75rem; background: var(--app-surface); box-shadow: var(--app-shadow-lg); }
        .sop-hub__new-form .form-control { flex: 1 1 170px; min-width: 0; }
        .sop-hub__grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
        .sop-hub__section { position: relative; min-width: 0; border: 1px solid var(--app-border); border-radius: .85rem; background: var(--app-surface); transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease; }
        .sop-hub__section:hover { transform: translateY(-2px); border-color: var(--app-primary); box-shadow: var(--app-shadow); }
        .sop-hub__section:focus-within { z-index: 3; }
        .sop-hub__section-main { display: flex; min-height: 162px; flex-direction: column; align-items: flex-start; padding: 1.1rem 1.15rem .85rem; text-decoration: none; color: var(--app-text); }
        .sop-hub__section-main:hover, .sop-hub__section-main:focus { color: var(--app-text); text-decoration: none; }
        .sop-hub__section-main:focus-visible { outline: 2px solid var(--app-primary); outline-offset: 2px; border-radius: .85rem; }
        .sop-hub__icon { display: grid; width: 36px; height: 36px; place-items: center; margin-bottom: .7rem; border-radius: .65rem; background: var(--app-primary-light); color: var(--app-primary); font-size: 1.3rem; }
        .sop-hub__section-name { max-width: calc(100% - 2.25rem); font-size: .96rem; font-weight: 700; line-height: 1.35; overflow-wrap: anywhere; }
        .sop-hub__count { margin-top: .2rem; color: var(--app-text-muted); font-size: .78rem; }
        .sop-hub__section-footer { display: flex; align-items: center; justify-content: space-between; width: 100%; margin-top: auto; padding-top: .75rem; border-top: 1px solid var(--app-border); color: var(--app-primary); font-size: .8rem; font-weight: 600; }
        .sop-hub__section-footer i { font-size: 1.2rem; }
        .sop-hub__manage { position: absolute; top: 1rem; right: 1rem; z-index: 4; }
        .sop-hub__manage summary { display: grid; width: 32px; height: 32px; place-items: center; border-radius: .5rem; color: var(--app-text-muted); font-size: 1.25rem; cursor: pointer; list-style: none; }
        .sop-hub__manage summary::-webkit-details-marker { display: none; }
        .sop-hub__manage summary:hover, .sop-hub__manage[open] summary, .sop-hub__manage summary:focus-visible { color: var(--app-primary); background: var(--app-primary-light); }
        .sop-hub__menu { position: absolute; top: calc(100% + .4rem); right: 0; width: 175px; padding: .35rem; border: 1px solid var(--app-border); border-radius: .65rem; background: var(--app-surface); box-shadow: var(--app-shadow-lg); }
        .sop-hub__menu button { display: flex; align-items: center; gap: .5rem; width: 100%; padding: .55rem .6rem; border: 0; border-radius: .4rem; background: transparent; color: var(--app-text); text-align: left; font-size: .83rem; cursor: pointer; }
        .sop-hub__menu button:hover, .sop-hub__menu button:focus-visible { background: var(--app-primary-light); }
        .sop-hub__menu button i { font-size: 1.1rem; }
        .sop-hub__menu .sop-hub__menu-danger { color: #dc3545; }
        .sop-hub__menu .sop-hub__menu-danger:hover, .sop-hub__menu .sop-hub__menu-danger:focus-visible { background: rgba(220, 53, 69, .1); }
        .sop-hub__dialog { width: min(440px, calc(100vw - 2rem)); max-width: none; padding: 1.5rem; border: 1px solid var(--app-border); border-radius: 1rem; background: var(--app-surface); color: var(--app-text); box-shadow: var(--app-shadow-lg); }
        .sop-hub__dialog::backdrop { background: rgba(15, 23, 42, .58); }
        .sop-hub__dialog-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
        .sop-hub__dialog-icon { display: grid; width: 42px; height: 42px; place-items: center; border-radius: .7rem; background: var(--app-primary-light); color: var(--app-primary); font-size: 1.35rem; }
        .sop-hub__dialog-icon--danger { background: rgba(220, 53, 69, .1); color: #dc3545; }
        .sop-hub__dialog-close { border: 0; background: transparent; color: var(--app-text-muted); font-size: 1.5rem; line-height: 1; cursor: pointer; }
        .sop-hub__dialog h2 { margin-bottom: .4rem; font-size: 1.25rem; font-weight: 700; }
        .sop-hub__dialog p { margin-bottom: 1.25rem; color: var(--app-text-muted); font-size: .9rem; line-height: 1.55; }
        .sop-hub__dialog-actions { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: .5rem; margin-top: 1.4rem; }
        .sop-hub__empty { grid-column: 1 / -1; padding: 3rem 1rem; text-align: center; border: 1px dashed var(--app-border); border-radius: .85rem; color: var(--app-text-muted); }
        .sop-hub__empty i { font-size: 2.5rem; }
        .sop-hub__empty h3 { margin: .5rem 0; color: var(--app-text); font-size: 1rem; }
        .sop-hub__empty p { margin-bottom: 0; }
        @media (max-width: 1100px) { .sop-hub__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px) { .sop-hub__header, .sop-hub__body { padding: 1.25rem; } .sop-hub__grid { grid-template-columns: 1fr; } .sop-hub__new { width: 100%; } .sop-hub__new-form { left: 0; right: auto; } }
    </style>
@endpush

@push('after-scripts')
    <script>
        (() => {
            const hub = document.querySelector('.sop-hub');
            const editDialog = document.getElementById('sop-edit-dialog');
            const deleteDialog = document.getElementById('sop-delete-dialog');
            if (!hub || !editDialog || !deleteDialog) return;

            const editForm = document.getElementById('sop-edit-form');
            const editName = document.getElementById('sop-edit-name');
            const deleteForm = document.getElementById('sop-delete-form');

            hub.addEventListener('click', (event) => {
                const edit = event.target.closest('[data-sop-edit]');
                const remove = event.target.closest('[data-sop-delete]');
                if (!edit && !remove) return;

                event.target.closest('.sop-hub__manage').open = false;
                if (edit) {
                    editForm.action = edit.dataset.url;
                    document.getElementById('sop-edit-id').value = edit.dataset.id;
                    editName.value = edit.dataset.name;
                    editDialog.showModal();
                    editName.focus();
                } else {
                    deleteForm.action = remove.dataset.url;
                    const count = Number(remove.dataset.count);
                    document.getElementById('sop-delete-description').textContent = count
                        ? `Bagian "${remove.dataset.name}" dan ${count} aturan di dalamnya akan dihapus. Tindakan ini tidak dapat dibatalkan.`
                        : `Bagian "${remove.dataset.name}" akan dihapus. Tindakan ini tidak dapat dibatalkan.`;
                    deleteDialog.showModal();
                }
            });

            document.addEventListener('click', (event) => {
                document.querySelectorAll('.sop-hub__manage[open]').forEach((menu) => {
                    if (!menu.contains(event.target)) menu.open = false;
                });
                if (event.target.closest('[data-sop-close]')) {
                    event.target.closest('dialog').close();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    document.querySelectorAll('.sop-hub__manage[open]').forEach((menu) => menu.open = false);
                }
            });

            @if ($errors->has('nama') && old('_sop_action') === 'edit')
                const failedEdit = [...hub.querySelectorAll('[data-sop-edit]')]
                    .find((button) => button.dataset.id === @json((string) old('_sop_bagian_id')));
                if (failedEdit) {
                    failedEdit.click();
                    editName.value = @json(old('nama'));
                }
            @endif
        })();
    </script>
@endpush
