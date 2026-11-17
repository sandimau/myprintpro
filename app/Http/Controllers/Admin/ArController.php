<?php

namespace App\Http\Controllers\Admin;

use Gate;
use App\Models\Ar;
use App\Models\Cabang;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Intervention\Image\Facades\Image;

class ArController extends Controller
{
    public function index()
    {
        abort_if(Gate::denies('ar_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $ars = Ar::whereHas('member')->with('member')->get();

        return view('admin.ars.index', compact('ars'));
    }

    public function create()
    {
        abort_if(Gate::denies('ar_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');
        $cabangs = Cabang::aktif()->orderBy('nama')->get();
        $members = Member::withoutGlobalScope('cabang')->whereDoesntHave('ar')
            ->whereIn('cabang_id', $cabangs->pluck('id'))->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'cabang_id']);

        return view('admin.ars.create', compact('cabangs', 'members'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'cabang_id' => ['required', Rule::exists('cabangs', 'id')->where('status', true)],
            'member_id' => ['required', Rule::exists('members', 'id')->where('cabang_id', $request->cabang_id)->whereNull('deleted_at'), Rule::unique('ars', 'member_id')->whereNull('deleted_at')],
            'kode' => 'required|alpha_dash|max:7',
            'warna' => 'required',
            'ttd' => 'required|mimes:jpeg,png,jpg'
        ]);

        $gambar = null;
        if ($request->hasFile('ttd')) {
            $img = $request->file('ttd');
            $filename = time() . '.' . $request->ttd->extension();
            $img_resize = Image::make($img->getRealPath());
            $img_resize->resize(500, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $save_path = public_path('uploads/ttd');
            File::ensureDirectoryExists($save_path);
            $img_resize->save($save_path . DIRECTORY_SEPARATOR . $filename);
            $gambar = $filename;
        }

        Ar::create([
            'member_id' => $request->member_id,
            'kode' => $request->kode,
            'warna' => $request->warna,
            'ttd' => $gambar,
        ]);

        return redirect()->route('ars.index')->withSuccess(__('Ar created successfully.'));
    }

    public function edit(Ar $ar)
    {
        $cabangs = Cabang::aktif()->orderBy('nama')->get();
        $members = Member::withoutGlobalScope('cabang')
            ->where(function ($query) use ($ar) {
                $query->whereDoesntHave('ar')->orWhere('members.id', $ar->member_id);
            })
            ->whereIn('cabang_id', $cabangs->pluck('id'))->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'cabang_id']);
        $ar->load(['member' => fn ($query) => $query->withoutGlobalScope('cabang')]);

        return view('admin.ars.edit', compact('ar', 'members', 'cabangs'));
    }

    public function destroy(Ar $ar)
    {
        $ar->delete();

        return redirect()->route('ars.index')
            ->withSuccess(__('Ar deleted successfully.'));
    }

    public function update(Request $request, Ar $ar)
    {
        $request->validate([
            'cabang_id' => ['required', Rule::exists('cabangs', 'id')->where('status', true)],
            'member_id' => ['required', Rule::exists('members', 'id')->where('cabang_id', $request->cabang_id)->whereNull('deleted_at'), Rule::unique('ars', 'member_id')->whereNull('deleted_at')->ignore($ar->id)],
            'kode' => 'required|alpha_dash|max:7',
            'warna' => 'required',
            'ttd' => 'nullable|mimes:jpeg,png,jpg'
        ]);

        $gambar = $ar->ttd;
        if ($request->hasFile('ttd')) {
            $img = $request->file('ttd');
            $filename = time() . '.' . $request->ttd->extension();
            $img_resize = Image::make($img->getRealPath());
            $img_resize->resize(500, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            $save_path = public_path('uploads/ttd');
            File::ensureDirectoryExists($save_path);
            $img_resize->save($save_path . DIRECTORY_SEPARATOR . $filename);
            $gambar = $filename;
            if ($ar->ttd) {
                File::delete(public_path('uploads/ttd/' . $ar->ttd));
            }
        }

        $ar->update([
            'member_id' => $request->member_id,
            'kode' => $request->kode,
            'warna' => $request->warna,
            'ttd' => $gambar,
        ]);

        return redirect()->route('ars.index')->withSuccess(__('Ar Updated successfully.'));
    }
}
