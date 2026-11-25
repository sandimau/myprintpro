@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-2">
        @include('layouts.includes.messages')
    </div>

    <div class="dashboard-page">
    @if ($tampilkanPengingatGajian)
        <div class="card payroll-reminder border-0 shadow-sm mb-4">
            <div class="payroll-reminder__header d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="payroll-reminder__icon" aria-hidden="true"><i class='bx bx-calendar-check'></i></div>
                    <div>
                        <div class="payroll-reminder__eyebrow">PENGINGAT PENGGAJIAN</div>
                        <h5 class="fw-bold mb-1">Gajian Hari Ini</h5>
                        <span class="payroll-reminder__date">{{ now()->translatedFormat('l, d F Y') }}</span>
                    </div>
                </div>
                <div class="payroll-reminder__count" aria-label="{{ $anggotaGajianHariIni->count() }} anggota gajian hari ini">
                    <strong>{{ $anggotaGajianHariIni->count() }}</strong>
                    <span>anggota</span>
                </div>
            </div>
            <div class="card-body p-3 p-md-4">
                @forelse ($anggotaGajianHariIni as $anggota)
                    <div class="payroll-reminder__row d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="payroll-reminder__avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($anggota->nama_lengkap ?: '?', 0, 1)) }}</div>
                            <div>
                                <div class="fw-semibold">{{ $anggota->nama_lengkap }}</div>
                                <small class="text-muted">{{ $anggota->jenis === 'freelance' ? 'Freelance' : 'Karyawan' }} · Gajian hari ini</small>
                            </div>
                        </div>
                        <a href="{{ route($anggota->jenis === 'freelance' ? 'members.penggajianFreelance' : 'members.penggajian', $anggota->id) }}"
                            class="btn btn-sm payroll-reminder__link">
                            Lihat penggajian <i class='bx bx-right-arrow-alt' aria-hidden="true"></i>
                        </a>
                    </div>
                @empty
                    <div class="payroll-reminder__empty text-center py-4">
                        <i class='bx bx-calendar-check d-block mb-2' aria-hidden="true"></i>
                        <div class="fw-semibold">Belum ada jadwal gajian hari ini</div>
                        <small class="text-muted">Anggota yang gajian akan tampil otomatis di sini.</small>
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <div class="dashboard-page__section d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <div>
            <span class="dashboard-page__eyebrow">PERFORMA</span>
            <h2 class="h5 fw-bold mb-0">Tren penjualan</h2>
        </div>
        <span class="dashboard-page__period">7 hari terakhir</span>
    </div>

    {{-- Chart Section --}}
    <div class="row g-4 mb-4">
        {{-- Omzet Offline Pekanan --}}
        <div class="col-lg-6">
            <div class="card dashboard-card dashboard-card--green border-0 shadow-sm h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="dashboard-card__heading d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dashboard-card__icon"><i class='bx bx-store' aria-hidden="true"></i></div>
                            <div>
                                <span class="dashboard-card__label">PENJUALAN LANGSUNG</span>
                                <h3 class="h6 fw-bold mb-0">Omzet Offline Pekanan</h3>
                            </div>
                        </div>
                        <a href="{{ route('order.index') }}" class="dashboard-card__action" aria-label="Lihat order offline" title="Lihat order offline">
                            <i class='bx bx-right-arrow-alt' aria-hidden="true"></i>
                        </a>
                    </div>
                    <div id="chart-offline"></div>
                </div>
            </div>
        </div>

        {{-- Omzet Online Pekanan --}}
        <div class="col-lg-6">
            <div class="card dashboard-card dashboard-card--purple border-0 shadow-sm h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="dashboard-card__heading d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dashboard-card__icon"><i class='bx bx-shopping-bag' aria-hidden="true"></i></div>
                            <div>
                                <span class="dashboard-card__label">PENJUALAN ONLINE</span>
                                <h3 class="h6 fw-bold mb-0">Omzet Marketplace Pekanan</h3>
                            </div>
                        </div>
                        <a href="{{ route('projectmp.index') }}" class="dashboard-card__action" aria-label="Lihat proyek marketplace" title="Lihat proyek marketplace">
                            <i class='bx bx-right-arrow-alt' aria-hidden="true"></i>
                        </a>
                    </div>
                    <div id="chart-online"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-page__section d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
        <div>
            <span class="dashboard-page__eyebrow">SOROTAN</span>
            <h2 class="h5 fw-bold mb-0">Aktivitas penjualan</h2>
        </div>
        <span class="dashboard-page__period">Data terkini</span>
    </div>

    {{-- Tables Section --}}
    <div class="row g-4">
        {{-- Order Terbesar Offline Pekanan --}}
        <div class="col-lg-4">
            <div class="card dashboard-card dashboard-card--pink border-0 shadow-sm h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="dashboard-card__heading d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dashboard-card__icon"><i class='bx bx-receipt' aria-hidden="true"></i></div>
                            <div>
                                <span class="dashboard-card__label">7 HARI TERAKHIR</span>
                                <h3 class="h6 fw-bold mb-0">Order Terbesar Offline</h3>
                            </div>
                        </div>
                        <a href="{{ route('order.index') }}" class="dashboard-card__action" aria-label="Lihat semua order" title="Lihat semua order">
                            <i class='bx bx-right-arrow-alt' aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover dashboard-table">
                            <thead>
                                <tr>
                                    <th class="border-0">Tanggal</th>
                                    <th class="border-0" style="width: 45%;">Konsumen</th>
                                    <th class="border-0 text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($orderTerbesarOffline as $order)
                                    <tr>
                                        <td class="align-middle">
                                            <span class="dashboard-table__date">{{ date('d/m', strtotime($order->created_at)) }}</span>
                                        </td>
                                        <td class="align-middle">
                                            <a href="{{ route('order.detail', $order->id) }}"
                                                class="text-decoration-none fw-medium">
                                                {{ \Illuminate\Support\Str::limit($order->kontak->nama ?? '-', 18, '...') }}
                                            </a>
                                        </td>
                                        <td class="align-middle text-end">
                                            <span class="fw-bold dashboard-table__value">{{ number_format($order->total, 0, ',', '.') }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            <i class='bx bx-package bx-lg mb-2 d-block'></i>
                                            Tidak ada data
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Penjualan Terbaik Pekanan --}}
        <div class="col-lg-4">
            <div class="card dashboard-card dashboard-card--amber border-0 shadow-sm h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="dashboard-card__heading d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dashboard-card__icon"><i class='bx bx-trending-up' aria-hidden="true"></i></div>
                            <div>
                                <span class="dashboard-card__label">7 HARI TERAKHIR</span>
                                <h3 class="h6 fw-bold mb-0">Penjualan Terbaik</h3>
                            </div>
                        </div>
                        <a href="{{ route('order.omzet') }}" class="dashboard-card__action" aria-label="Lihat rincian omzet" title="Lihat rincian omzet">
                            <i class='bx bx-right-arrow-alt' aria-hidden="true"></i>
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover dashboard-table">
                            <thead>
                                <tr>
                                    <th class="border-0">Produk</th>
                                    <th class="border-0 text-end">Omzet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($produkTerlaris as $produk)
                                    <tr>
                                        <td class="align-middle">
                                            <span
                                                class="fw-medium">{{ \Illuminate\Support\Str::limit($produk['nama_produk'] ?? '-', 24, '...') }}</span>
                                        </td>
                                        <td class="align-middle text-end">
                                            <span class="fw-bold dashboard-table__value">{{ number_format($produk['omzet'] ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-4">
                                            <i class='bx bx-cart bx-lg mb-2 d-block'></i>
                                            Tidak ada data
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Order Terbesar Hari Ini --}}
        <div class="col-lg-4">
            <div class="card dashboard-card dashboard-card--blue border-0 shadow-sm h-100">
                <div class="card-body p-3 p-md-4">
                    <div class="dashboard-card__heading d-flex align-items-center justify-content-between gap-2 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="dashboard-card__icon"><i class='bx bx-time-five' aria-hidden="true"></i></div>
                            <div>
                                <span class="dashboard-card__label">{{ now()->translatedFormat('d F Y') }}</span>
                                <h3 class="h6 fw-bold mb-0">Order Terbesar Hari Ini</h3>
                            </div>
                        </div>
                        <span class="dashboard-card__live"><span aria-hidden="true"></span> Live</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover dashboard-table">
                            <thead>
                                <tr>
                                    <th class="border-0">Produk</th>
                                    <th class="border-0 text-end">Omzet</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($orderTerbesarHariIni as $order)
                                    <tr>
                                        <td class="align-middle">
                                            <span
                                                class="fw-medium">{{ \Illuminate\Support\Str::limit($order['nama_produk'] ?? '-', 24, '...') }}</span>
                                        </td>
                                        <td class="align-middle text-end">
                                            <span class="fw-bold dashboard-table__value">{{ number_format($order['omzet'] ?? 0, 0, ',', '.') }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-4">
                                            <i class='bx bx-calendar-x bx-lg mb-2 d-block'></i>
                                            Belum ada order hari ini
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
@endsection

@push('after-scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script type="text/javascript">
        @php
            // Prepare data Omzet Offline
            $sortedOffline = collect($omzetOffline ?? [])->sortKeys();
            $today = now()->format('Y-m-d');
            $yesterday = now()->subDay()->format('Y-m-d');

            $offlineDates = $sortedOffline->keys()->toArray();
            $formattedDatesOffline = [];
            foreach ($offlineDates as $date) {
                if ($date === $today) {
                    $formattedDatesOffline[] = 'hari ini';
                } elseif ($date === $yesterday) {
                    $formattedDatesOffline[] = 'kemarin';
                } else {
                    $formattedDatesOffline[] = date('j M', strtotime($date));
                }
            }

            // Prepare data Omzet Online
            $sortedOnline = collect($omzetOnline ?? [])->sortKeys();
            // Jangan ambil dari item pertama (bisa kosong). Kumpulkan dari semua hari.
            $mpList = $sortedOnline
                ->flatMap(function ($row) {
                    $vars = is_object($row) ? get_object_vars($row) : (array) $row;
                    return array_keys($vars);
                })
                ->filter(fn ($key) => $key !== 'date')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $mpNames = collect($marketplaces ?? [])->mapWithKeys(function ($nama, $id) {
                return ['mp_' . $id => $nama];
            });

            $onlineDates = $sortedOnline->keys()->toArray();
            $formattedDatesOnline = [];
            foreach ($onlineDates as $date) {
                if ($date === $today) {
                    $formattedDatesOnline[] = 'hari ini';
                } elseif ($date === $yesterday) {
                    $formattedDatesOnline[] = 'kemarin';
                } else {
                    $formattedDatesOnline[] = date('j M', strtotime($date));
                }
            }
        @endphp

        // Data untuk chart offline
        var datesOffline = [];
        var valuesOffline = [];

        @foreach ($sortedOffline as $date => $data)
            datesOffline.push('{{ $formattedDatesOffline[array_search($date, $offlineDates)] }}');
            valuesOffline.push({{ $data->offline ?? 0 }});
        @endforeach

        // Data untuk chart online
        var datesOnline = [];
        @foreach ($mpList as $mp)
            var values_mp_{{ $mp }} = [];
        @endforeach

        @foreach ($sortedOnline as $date => $data)
            datesOnline.push('{{ $formattedDatesOnline[array_search($date, $onlineDates)] }}');
            @foreach ($mpList as $mp)
                values_mp_{{ $mp }}.push({{ $data->$mp ?? 0 }});
            @endforeach
        @endforeach

        function getThemeMode() {
            return (document.documentElement.getAttribute('data-theme') || 'dark') === 'light' ? 'light' : 'dark';
        }

        function getChartThemeOptions() {
            var isDark = getThemeMode() === 'dark';
            return {
                theme: { mode: isDark ? 'dark' : 'light' },
                grid: { borderColor: isDark ? '#334155' : '#f1f1f1' },
                xaxis: {
                    labels: {
                        style: {
                            fontSize: '11px',
                            colors: isDark ? '#94a3b8' : '#64748b'
                        }
                    }
                },
                yaxis: {
                    labels: {
                        style: {
                            fontSize: '11px',
                            colors: isDark ? '#94a3b8' : '#64748b'
                        }
                    }
                },
                legend: {
                    labels: { colors: isDark ? '#94a3b8' : '#64748b' }
                }
            };
        }

        function applyChartTheme(options) {
            var themeOpts = getChartThemeOptions();
            options.theme = themeOpts.theme;
            options.grid = themeOpts.grid;
            options.xaxis.labels.style = Object.assign(
                options.xaxis.labels.style || {},
                themeOpts.xaxis.labels.style
            );
            options.yaxis.labels.style = Object.assign(
                options.yaxis.labels.style || {},
                themeOpts.yaxis.labels.style
            );
            if (options.legend) {
                options.legend.labels = themeOpts.legend.labels;
            }
            return options;
        }

        // Chart Offline
        var optionsOffline = {
            chart: {
                type: 'area',
                height: 320,
                toolbar: {
                    show: false
                },
                fontFamily: 'inherit'
            },
            colors: ['#00b894'],
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    if (val >= 1000000) {
                        return Math.round(val / 1000000) + 'jt'
                    } else if (val >= 1000) {
                        return Math.round(val / 1000) + 'rb'
                    }
                    return val
                },
                style: {
                    fontSize: '11px',
                    fontWeight: 600
                }
            },
            stroke: {
                curve: 'smooth',
                width: 3
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.5,
                    opacityTo: 0.1,
                    stops: [0, 90, 100]
                }
            },
            series: [{
                name: 'Omzet Offline',
                data: valuesOffline
            }],
            xaxis: {
                categories: datesOffline,
                labels: {
                    style: {
                        fontSize: '11px'
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(value) {
                        if (value >= 1000000) {
                            return Math.round(value / 1000000) + 'jt'
                        } else if (value >= 1000) {
                            return Math.round(value / 1000) + 'rb'
                        }
                        return value
                    },
                    style: {
                        fontSize: '11px'
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID')
                    }
                }
            }
        };
        applyChartTheme(optionsOffline);

        // Chart Online (Marketplace)
        var optionsOnline = {
            chart: {
                type: 'bar',
                height: 320,
                stacked: true,
                toolbar: {
                    show: false
                },
                fontFamily: 'inherit'
            },
            colors: ['#6c5ce7', '#a29bfe', '#fd79a8', '#fdcb6e', '#74b9ff', '#00cec9', '#fab1a0'],
            dataLabels: {
                enabled: true,
                formatter: function(val) {
                    if (val >= 1000000) {
                        return Math.round(val / 1000000) + 'jt'
                    } else if (val >= 1000) {
                        return Math.round(val / 1000) + 'rb'
                    } else if (val > 0) {
                        return val
                    }
                    return ''
                },
                style: {
                    fontSize: '10px',
                    fontWeight: 600
                }
            },
            plotOptions: {
                bar: {
                    borderRadius: 4,
                    columnWidth: '60%'
                }
            },
            series: [
                @foreach ($mpList as $mp)
                    {
                        name: @json($mpNames[$mp] ?? $mp),
                        data: values_mp_{{ $mp }}
                    },
                @endforeach
            ],
            xaxis: {
                categories: datesOnline,
                labels: {
                    style: {
                        fontSize: '11px'
                    }
                }
            },
            yaxis: {
                labels: {
                    formatter: function(value) {
                        if (value >= 1000000) {
                            return Math.round(value / 1000000) + 'jt'
                        } else if (value >= 1000) {
                            return Math.round(value / 1000) + 'rb'
                        }
                        return value
                    },
                    style: {
                        fontSize: '11px'
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return 'Rp ' + value.toLocaleString('id-ID')
                    }
                }
            },
            legend: {
                position: 'top',
                horizontalAlign: 'left',
                fontSize: '12px'
            }
        };
        applyChartTheme(optionsOnline);

        var chartOffline = new ApexCharts(document.querySelector("#chart-offline"), optionsOffline);
        var chartOnline = new ApexCharts(document.querySelector("#chart-online"), optionsOnline);

        chartOffline.render();
        chartOnline.render();

        function updateDashboardChartsTheme() {
            var themeOpts = getChartThemeOptions();
            chartOffline.updateOptions({
                theme: themeOpts.theme,
                grid: themeOpts.grid,
                xaxis: { labels: { style: themeOpts.xaxis.labels.style } },
                yaxis: { labels: { style: themeOpts.yaxis.labels.style } }
            });
            chartOnline.updateOptions({
                theme: themeOpts.theme,
                grid: themeOpts.grid,
                xaxis: { labels: { style: themeOpts.xaxis.labels.style } },
                yaxis: { labels: { style: themeOpts.yaxis.labels.style } },
                legend: { labels: themeOpts.legend.labels }
            });
        }

        document.getElementById('theme-toggle')?.addEventListener('click', function() {
            setTimeout(updateDashboardChartsTheme, 50);
        });
    </script>
@endpush

@push('after-styles')
    <style>
        .dashboard-page__eyebrow {
            display: block;
            margin-bottom: 5px;
            color: #818cf8;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
        }

        .dashboard-page__period {
            display: inline-flex;
            align-items: center;
            padding: 9px 14px;
            border: 1px solid var(--app-border);
            border-radius: 10px;
            background: var(--app-bg);
            color: var(--app-text-muted);
            font-size: 13px;
        }

        .dashboard-page__section h2 { color: var(--app-text); }

        .dashboard-card {
            --dashboard-accent: #10b981;
            --dashboard-tint: rgba(16, 185, 129, .12);
            border-radius: 18px;
            overflow: hidden;
        }

        .dashboard-card--purple { --dashboard-accent: #818cf8; --dashboard-tint: rgba(129, 140, 248, .14); }
        .dashboard-card--pink { --dashboard-accent: #ec4899; --dashboard-tint: rgba(236, 72, 153, .12); }
        .dashboard-card--amber { --dashboard-accent: #f59e0b; --dashboard-tint: rgba(245, 158, 11, .12); }
        .dashboard-card--blue { --dashboard-accent: #3b82f6; --dashboard-tint: rgba(59, 130, 246, .12); }

        .dashboard-card__heading { min-height: 50px; }
        .dashboard-card__heading h3 { color: var(--app-text); }

        .dashboard-card__label {
            display: block;
            margin-bottom: 3px;
            color: var(--app-text-muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
        }

        .dashboard-card__icon, .dashboard-card__action {
            display: grid;
            place-items: center;
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: var(--dashboard-tint);
            color: var(--dashboard-accent);
            font-size: 21px;
        }

        .dashboard-card__action {
            flex-basis: 34px;
            width: 34px;
            height: 34px;
            font-size: 23px;
            text-decoration: none;
            transition: transform .2s ease, background-color .2s ease;
        }

        .dashboard-card__action:hover, .dashboard-card__action:focus-visible {
            color: var(--dashboard-accent);
            background: var(--app-primary-light);
            transform: translateX(2px);
        }

        .dashboard-card__live {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--dashboard-accent);
            font-size: 12px;
            font-weight: 700;
        }

        .dashboard-card__live span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: currentColor;
        }

        .dashboard-table { margin-bottom: 0; font-size: 13px; }
        .dashboard-table thead th { color: var(--app-text-muted); font-size: 11px; font-weight: 600; }
        .dashboard-table td { padding-top: 12px; padding-bottom: 12px; }
        .dashboard-table tbody tr:last-child td { border-bottom: 0; }
        .dashboard-table__value { color: var(--dashboard-accent); white-space: nowrap; }
        .dashboard-table__date {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 7px;
            background: var(--dashboard-tint);
            color: var(--dashboard-accent);
            white-space: nowrap;
        }

        .dashboard-table .text-muted > .bx { color: var(--dashboard-accent); opacity: .7; }

        .payroll-reminder {
            border-radius: 18px;
            overflow: hidden;
        }

        .payroll-reminder__header {
            padding: 22px 26px;
            background: linear-gradient(115deg, #312e81, #4f46e5 65%, #6366f1);
            color: #fff;
        }

        .payroll-reminder__eyebrow {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .12em;
            color: #c7d2fe;
        }

        .payroll-reminder__date { color: #e0e7ff; font-size: 13px; }

        .payroll-reminder__icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: rgba(255, 255, 255, .15);
            font-size: 26px;
        }

        .payroll-reminder__count {
            display: flex;
            align-items: baseline;
            gap: 7px;
            padding: 10px 16px;
            border-radius: 12px;
            background: rgba(255, 255, 255, .15);
        }

        .payroll-reminder__count strong { font-size: 24px; line-height: 1; }
        .payroll-reminder__count span { font-size: 13px; }

        .payroll-reminder__row {
            padding: 13px 16px;
            border: 1px solid rgba(99, 102, 241, .16);
            border-radius: 12px;
        }

        .payroll-reminder__row + .payroll-reminder__row { margin-top: 10px; }

        .payroll-reminder__avatar {
            display: grid;
            place-items: center;
            flex: 0 0 40px;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(99, 102, 241, .13);
            color: #818cf8;
            font-weight: 700;
        }

        .payroll-reminder__link {
            background: #4f46e5;
            color: #fff;
            border-radius: 9px;
            padding: 7px 12px;
        }

        .payroll-reminder__link:hover, .payroll-reminder__link:focus-visible {
            background: #3730a3;
            color: #fff;
        }

        .payroll-reminder__empty > i { font-size: 32px; color: #818cf8; }

        @media (max-width: 575px) {
            .payroll-reminder__header { padding: 18px; }
            .payroll-reminder__link { width: 100%; text-align: center; }
        }

        .dashboard-page .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dashboard-page .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--app-shadow-lg) !important;
        }

        .dashboard-page .dashboard-table tbody tr {
            transition: background-color 0.2s ease;
        }

        [data-theme="light"] .dashboard-page .dashboard-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        :root:not([data-theme="light"]) .dashboard-page .dashboard-table tbody tr:hover,
        [data-theme="dark"] .dashboard-page .dashboard-table tbody tr:hover {
            background-color: rgba(99, 102, 241, .08);
        }
    </style>
@endpush
