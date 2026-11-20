<?php

namespace Tests\Feature;

use Tests\TestCase;

class JoPrintViewTest extends TestCase
{
    public function test_shared_print_view_includes_online_and_offline_items_in_button_order(): void
    {
        $online = (object) [
            'produk' => (object) ['nama' => 'Produk online'],
            'tema' => 'Tema online',
            'jumlah' => 2,
            'keterangan' => null,
            'deadline' => '2026-11-25',
            'gambar' => 'online.png',
        ];
        $offline = (object) [
            'produk' => (object) ['nama' => 'Produk tambahan'],
            'tema' => 'Tema offline',
            'jumlah' => 3,
            'keterangan' => null,
            'deathline' => '2026-11-26',
            'gambar' => 'offline.png',
        ];

        $html = view('admin.jo.partials.print', [
            'joNota' => 'JO-123',
            'joKonsumen' => 'Pembeli',
            'joMarketplace' => 'Toko',
            'joDeadline' => '2026-11-30',
            'joTanggalOrder' => '2026-11-20',
            'joOnlineItems' => collect([$online]),
            'joOfflineItems' => collect([$offline]),
        ])->render();

        $this->assertStringContainsString('Produk online', $html);
        $this->assertStringContainsString('Produk tambahan', $html);
        $this->assertLessThan(strpos($html, 'Produk tambahan'), strpos($html, 'Produk online'));
        $this->assertStringContainsString('projectMp', $html);
        $this->assertStringContainsString('online.png', $html);
        $this->assertStringContainsString('offline.png', $html);
        $this->assertStringContainsString('2026-11-26', $html);
        $this->assertStringContainsString('function cetakSemuaJo()', $html);
    }
}
