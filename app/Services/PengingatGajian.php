<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PengingatGajian
{
    public function hariIni(?Carbon $tanggal = null): Collection
    {
        $tanggal = $tanggal ?? now();

        return Member::where('status', 1)
            ->whereNotNull('tgl_gajian')
            ->orderBy('nama_lengkap')
            ->get()
            ->filter(function (Member $member) use ($tanggal) {
                $hari = $member->hariGajian();

                // Tanggal 29-31 jatuh pada hari terakhir jika bulan ini lebih pendek.
                return $hari !== null && min($hari, $tanggal->daysInMonth) === $tanggal->day;
            })
            ->values();
    }
}
