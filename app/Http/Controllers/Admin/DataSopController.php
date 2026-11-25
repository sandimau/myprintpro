<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DataSop;
use App\Models\SopBagian;
use App\Support\SopHtml;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DataSopController extends Controller
{
    public function index()
    {
        return view('admin.data-sops.index', [
            'bagians' => SopBagian::withCount('sops')->orderBy('nama')->get(),
        ]);
    }

    public function bagian(Request $request, SopBagian $sopBagian)
    {
        $query = $sopBagian->sops();
        if ($request->filled('cari')) {
            $cari = trim($request->input('cari'));
            $query->where(function ($q) use ($cari) {
                $q->where('judul', 'like', '%' . $cari . '%')
                    ->orWhere('isi', 'like', '%' . $cari . '%');
            });
        }

        return view('admin.data-sops.bagian', [
            'bagian' => $sopBagian,
            'sops' => $query->orderBy('judul')->paginate(20)->withQueryString(),
        ]);
    }

    public function storeBagian(Request $request)
    {
        Gate::authorize('sop_manage');
        $data = $this->validatedBagian($request);
        SopBagian::create($data);

        return redirect()->route('data-sops.index')->withSuccess('Bagian SOP ditambahkan.');
    }

    public function editBagian(SopBagian $sopBagian)
    {
        Gate::authorize('sop_manage');
        return view('admin.data-sops.edit-bagian', compact('sopBagian'));
    }

    public function updateBagian(Request $request, SopBagian $sopBagian)
    {
        Gate::authorize('sop_manage');
        $sopBagian->update($this->validatedBagian($request, $sopBagian));

        if ($request->input('_sop_action') === 'edit') {
            return redirect()->route('data-sops.index')->withSuccess('Bagian SOP diperbarui.');
        }

        return redirect()->route('data-sops.bagian', $sopBagian->slug)->withSuccess('Bagian SOP diperbarui.');
    }

    public function destroyBagian(Request $request, SopBagian $sopBagian)
    {
        Gate::authorize('sop_manage');
        if ($sopBagian->sops()->exists() && $request->input('hapus_aturan') !== '1') {
            return back()->withErrors(['bagian' => 'Konfirmasikan penghapusan seluruh aturan sebelum menghapus bagian.']);
        }

        DB::transaction(function () use ($sopBagian, $request) {
            $bagian = SopBagian::whereKey($sopBagian->id)->lockForUpdate()->firstOrFail();
            $aturan = $bagian->sops()->orderBy('id')->get();
            if ($aturan->isNotEmpty()) {
                if ($request->input('hapus_aturan') !== '1') {
                    throw ValidationException::withMessages(['bagian' => 'Konfirmasikan penghapusan seluruh aturan sebelum menghapus bagian.']);
                }
                $backup = 'sop-backups/hapus-bagian-' . $bagian->slug . '-' . now()->format('Ymd-His-u') . '.json';
                $data = json_encode(['bagian' => $bagian->toArray(), 'aturan' => $aturan->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                if (!Storage::disk('local')->put($backup, $data)) {
                    throw new \RuntimeException('Cadangan SOP tidak dapat disimpan. Penghapusan dibatalkan.');
                }
                $bagian->sops()->delete();
            }
            $bagian->delete();
        });

        return redirect()->route('data-sops.index')->withSuccess('Bagian SOP dan seluruh aturannya berhasil dihapus.');
    }

    public function show(DataSop $dataSop)
    {
        $dataSop->load('bagian');
        return view('admin.data-sops.show', compact('dataSop'));
    }

    public function create()
    {
        Gate::authorize('sop_manage');
        return view('admin.data-sops.form', [
            'dataSop' => new DataSop(),
            'bagians' => SopBagian::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('sop_manage');
        $data = $this->validated($request);
        $sop = DataSop::create($data);
        return redirect()->route('data-sops.show', $sop)->withSuccess('SOP berhasil ditambahkan.');
    }

    public function edit(DataSop $dataSop)
    {
        Gate::authorize('sop_manage');
        return view('admin.data-sops.form', [
            'dataSop' => $dataSop,
            'bagians' => SopBagian::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, DataSop $dataSop)
    {
        Gate::authorize('sop_manage');
        $dataSop->update($this->validated($request));
        return redirect()->route('data-sops.show', $dataSop)->withSuccess('SOP berhasil diperbarui.');
    }

    public function destroy(DataSop $dataSop)
    {
        Gate::authorize('sop_manage');
        $bagian = $dataSop->bagian;
        $dataSop->delete();
        return redirect()->route('data-sops.bagian', $bagian->slug)->withSuccess('Aturan SOP berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'isi' => ['required', 'string'],
            'sop_bagian_id' => ['required', 'integer', 'exists:sop_bagians,id'],
            'global' => ['nullable', 'boolean'],
        ]);
        $data['global'] = $request->boolean('global');
        $data['isi'] = SopHtml::clean($data['isi']);
        if (trim(strip_tags($data['isi'])) === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['isi' => 'Isi SOP wajib diisi.']);
        }
        return $data;
    }

    private function validatedBagian(Request $request, ?SopBagian $bagian = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('sop_bagians', 'nama')->ignore($bagian?->id)],
        ]);
        $data['slug'] = Str::slug($data['nama']);
        if ($data['slug'] === '' || SopBagian::where('slug', $data['slug'])->when($bagian, fn ($q) => $q->where('id', '!=', $bagian->id))->exists()) {
            throw ValidationException::withMessages(['nama' => 'Nama bagian tidak valid atau sudah digunakan.']);
        }

        return $data;
    }
}
