<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

require_once 'config/conf.php';
$koneksi = bukakoneksi();

$query_poli = "SELECT * FROM poliklinik WHERE status = '1' ORDER BY nm_poli";
$res_poli = mysqli_query($koneksi, $query_poli);

$query_pj = "SELECT * FROM penjab WHERE status = '1' ORDER BY kd_pj";
$res_pj = mysqli_query($koneksi, $query_pj);

$current_month = date('m');
$current_year = date('Y');

$months = [
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni',
    '07' => 'Juli',
    '08' => 'Agustus',
    '09' => 'September',
    '10' => 'Oktober',
    '11' => 'November',
    '12' => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kepatuhan Inputan SIMRS</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f5f7ff',
                            100: '#ebf0ff',
                            200: '#d6e0ff',
                            300: '#adc2ff',
                            400: '#85a4ff',
                            500: '#5c86ff',
                            600: '#3368ff',
                            700: '#0a4aff',
                            800: '#003be6',
                            900: '#002eb3',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        body {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: #0f172a;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(15, 23, 42, 0.08);
            box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.04);
        }

        .glass-card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card-hover:hover {
            transform: translateY(-4px);
            background: rgba(255, 255, 255, 0.95);
            border-color: rgba(99, 102, 241, 0.2);
            box-shadow: 0 20px 40px -15px rgba(99, 102, 241, 0.12);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(241, 245, 249, 0.6);
        }

        ::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.2);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.4);
        }
    </style>
</head>

<body class="antialiased overflow-x-hidden font-sans" x-data="auditApp()">

    <!-- Top Decorative Light Rays -->
    <div class="absolute top-0 left-1/4 w-96 h-96 bg-brand-500/5 rounded-full blur-[100px] pointer-events-none"></div>
    <div class="absolute top-20 right-1/4 w-96 h-96 bg-indigo-500/5 rounded-full blur-[100px] pointer-events-none">
    </div>

    <div class="container mx-auto px-4 py-8 relative z-10 max-w-7xl">

        <!-- Header -->
        <header
            class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8 pb-6 border-b border-slate-200">
            <div>

                <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900">
                    Kepatuhan Penginputan SIMRS
                </h1>
                <p class="text-slate-600 text-sm mt-1">
                    Sistem pemantauan kepatuhan berkas administratif SIMRS Rawat Jalan.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <?php if ($is_logged_in): ?>
                    <a href="./"
                        class="px-4 py-2 text-xs font-semibold text-slate-700 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg shadow-sm transition">
                        <i class="fa-solid fa-chart-line mr-2"></i> Ke Dashboard
                    </a>
                <?php else: ?>
                    <a href="./"
                        class="px-4 py-2 text-xs font-semibold text-slate-700 hover:text-slate-900 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg shadow-sm transition">
                        <i class="fa-solid fa-arrow-right-to-bracket mr-2"></i> Log In Admin
                    </a>
                <?php endif; ?>
            </div>
        </header>

        <!-- Filters Section -->
        <section class="glass-card rounded-2xl p-6 mb-8 shadow-xl">
            <h2 class="text-sm font-semibold tracking-wide text-slate-700 uppercase mb-4 flex items-center gap-2">
                <i class="fa-solid fa-sliders text-indigo-500"></i> Filter
            </h2>
            <form @submit.prevent="fetchData()" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                <!-- Poliklinik -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Poliklinik</label>
                    <select x-model="filters.kd_poli"
                        class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        <option value="">-- Semua Poliklinik --</option>
                        <?php while ($p = mysqli_fetch_assoc($res_poli)): ?>
                            <option value="<?= $p['kd_poli']; ?>"><?= $p['nm_poli']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Cara Bayar -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Cara Bayar</label>
                    <select x-model="filters.kd_pj"
                        class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        <option value="">-- Semua Cara Bayar --</option>
                        <?php while ($pj = mysqli_fetch_assoc($res_pj)): ?>
                            <option value="<?= $pj['kd_pj']; ?>"><?= $pj['png_jawab']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Bulan -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Bulan</label>
                    <select x-model="filters.bulan"
                        class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        <?php foreach ($months as $k => $v): ?>
                            <option value="<?= $k; ?>" <?= $k == $current_month ? 'selected' : ''; ?>><?= $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tahun -->
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Tahun</label>
                    <select x-model="filters.tahun"
                        class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                        <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                            <option value="<?= $y; ?>" <?= $y == $current_year ? 'selected' : ''; ?>><?= $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <!-- Action Button -->
                <div>
                    <button type="submit"
                        class="w-full bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-semibold text-sm rounded-xl px-5 py-3 transition duration-150 flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20">
                        <i class="fa-solid fa-rotate-right" :class="loading ? 'animate-spin' : ''"></i> Tampilkan Data
                    </button>
                </div>
            </form>
        </section>

        <!-- Loading Indicator -->
        <template x-if="loading">
            <div class="flex flex-col items-center justify-center py-16 gap-3">
                <div class="w-12 h-12 border-4 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                <p class="text-sm font-medium text-slate-600">Memproses berkas kepatuhan...</p>
            </div>
        </template>

        <!-- No Data State -->
        <template x-if="!loading && !hasData">
            <div class="glass-card rounded-2xl p-12 text-center shadow-lg border border-dashed border-slate-300">
                <div
                    class="w-16 h-16 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-200">
                    <i class="fa-solid fa-database text-slate-500 text-xl"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-800">Tidak ada data terdeteksi</h3>
                <p class="text-slate-600 text-sm mt-1 max-w-md mx-auto">
                    Silakan tentukan parameter filter di atas dan klik tombol "Tampilkan Data".
                </p>
            </div>
        </template>

        <!-- Main Dashboard Section -->
        <template x-if="!loading && hasData">
            <div>
                <!-- Period Info -->
                <div class="mb-6 flex justify-between items-center">
                    <div class="text-sm text-slate-600">
                        Periode Filter: <span class="font-semibold text-slate-800" x-text="periode"></span>
                    </div>
                </div>

                <!-- Grid of Cards (1 to 6) -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">

                    <!-- Card 1: Pasien Tanpa SEP -->
                    <div class="glass-card glass-card-hover rounded-2xl p-6 relative overflow-hidden cursor-pointer"
                        :class="activeCategory === 'tanpa_sep' ? 'border-rose-500/40 bg-white' : ''"
                        @click="selectCategory('tanpa_sep')">
                        <div class="absolute top-0 right-0 w-24 h-24 bg-rose-500/5 rounded-bl-full pointer-events-none">
                        </div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-shield-halved text-rose-400 text-lg"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-extrabold text-rose-400" x-text="summary.tanpa_sep.count">0
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Pasien</div>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1" x-text="summary.tanpa_sep.title">Pasien
                            Tanpa SEP</h3>
                        <p class="text-slate-500 text-xs line-clamp-2" x-text="summary.tanpa_sep.description">Deskripsi
                        </p>
                        <div class="mt-4 flex items-center justify-between text-xs font-semibold text-rose-400">
                            <span>Buka Detail Pasien</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"
                                :class="activeCategory === 'tanpa_sep' ? 'rotate-90' : ''"></i>
                        </div>
                    </div>

                    <!-- Card 2: Resep Tanpa SEP -->
                    <div class="glass-card glass-card-hover rounded-2xl p-6 relative overflow-hidden cursor-pointer"
                        :class="activeCategory === 'resep_tanpa_sep' ? 'border-orange-500/40 bg-white' : ''"
                        @click="selectCategory('resep_tanpa_sep')">
                        <div
                            class="absolute top-0 right-0 w-24 h-24 bg-orange-500/5 rounded-bl-full pointer-events-none">
                        </div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-receipt text-orange-400 text-lg"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-extrabold text-orange-400"
                                    x-text="summary.resep_tanpa_sep.count">0</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Pasien</div>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1" x-text="summary.resep_tanpa_sep.title">Resep
                            Tanpa SEP</h3>
                        <p class="text-slate-500 text-xs line-clamp-2" x-text="summary.resep_tanpa_sep.description">
                            Deskripsi</p>
                        <div class="mt-4 flex items-center justify-between text-xs font-semibold text-orange-400">
                            <span>Buka Detail Pasien</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"
                                :class="activeCategory === 'resep_tanpa_sep' ? 'rotate-90' : ''"></i>
                        </div>
                    </div>

                    <!-- Card 3: Tanpa Tindakan -->
                    <div class="glass-card glass-card-hover rounded-2xl p-6 relative overflow-hidden cursor-pointer"
                        :class="activeCategory === 'tanpa_tindakan' ? 'border-amber-500/40 bg-white' : ''"
                        @click="selectCategory('tanpa_tindakan')">
                        <div
                            class="absolute top-0 right-0 w-24 h-24 bg-amber-500/5 rounded-bl-full pointer-events-none">
                        </div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-user-minus text-amber-400 text-lg"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-extrabold text-amber-400"
                                    x-text="summary.tanpa_tindakan.count">0</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Pasien</div>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1" x-text="summary.tanpa_tindakan.title">Tanpa
                            Tindakan</h3>
                        <p class="text-slate-500 text-xs line-clamp-2" x-text="summary.tanpa_tindakan.description">
                            Deskripsi</p>
                        <div class="mt-4 flex items-center justify-between text-xs font-semibold text-amber-400">
                            <span>Buka Detail Pasien</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"
                                :class="activeCategory === 'tanpa_tindakan' ? 'rotate-90' : ''"></i>
                        </div>
                    </div>

                    <!-- Card 4: Resep Tanpa Tindakan -->
                    <div class="glass-card glass-card-hover rounded-2xl p-6 relative overflow-hidden cursor-pointer"
                        :class="activeCategory === 'resep_tanpa_tindakan' ? 'border-yellow-500/40 bg-white' : ''"
                        @click="selectCategory('resep_tanpa_tindakan')">
                        <div
                            class="absolute top-0 right-0 w-24 h-24 bg-yellow-500/5 rounded-bl-full pointer-events-none">
                        </div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-yellow-500/10 border border-yellow-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-file-invoice text-yellow-400 text-lg"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-extrabold text-yellow-400"
                                    x-text="summary.resep_tanpa_tindakan.count">0</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Pasien</div>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1" x-text="summary.resep_tanpa_tindakan.title">
                            Resep Tanpa Tindakan</h3>
                        <p class="text-slate-500 text-xs line-clamp-2"
                            x-text="summary.resep_tanpa_tindakan.description">Deskripsi</p>
                        <div class="mt-4 flex items-center justify-between text-xs font-semibold text-yellow-400">
                            <span>Buka Detail Pasien</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"
                                :class="activeCategory === 'resep_tanpa_tindakan' ? 'rotate-90' : ''"></i>
                        </div>
                    </div>

                    <!-- Card 5: Pasien Tidak Dilayani -->
                    <div class="glass-card glass-card-hover rounded-2xl p-6 relative overflow-hidden cursor-pointer"
                        :class="activeCategory === 'tidak_dilayani' ? 'border-purple-500/40 bg-white' : ''"
                        @click="selectCategory('tidak_dilayani')">
                        <div
                            class="absolute top-0 right-0 w-24 h-24 bg-purple-500/5 rounded-bl-full pointer-events-none">
                        </div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-clock-rotate-left text-purple-400 text-lg"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-extrabold text-purple-400"
                                    x-text="summary.tidak_dilayani.count">0</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Pasien</div>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1" x-text="summary.tidak_dilayani.title">Tidak
                            Dilayani</h3>
                        <p class="text-slate-500 text-xs line-clamp-2" x-text="summary.tidak_dilayani.description">
                            Deskripsi</p>
                        <div class="mt-4 flex items-center justify-between text-xs font-semibold text-purple-400">
                            <span>Buka Detail Pasien</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"
                                :class="activeCategory === 'tidak_dilayani' ? 'rotate-90' : ''"></i>
                        </div>
                    </div>

                    <!-- Card 6: Status Salah -->
                    <div class="glass-card glass-card-hover rounded-2xl p-6 relative overflow-hidden cursor-pointer"
                        :class="activeCategory === 'status_salah' ? 'border-indigo-500/40 bg-white' : ''"
                        @click="selectCategory('status_salah')">
                        <div
                            class="absolute top-0 right-0 w-24 h-24 bg-indigo-500/5 rounded-bl-full pointer-events-none">
                        </div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-triangle-exclamation text-indigo-400 text-lg"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-extrabold text-indigo-400"
                                    x-text="summary.status_salah.count">0</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Pasien</div>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1" x-text="summary.status_salah.title">Status
                            Salah</h3>
                        <p class="text-slate-500 text-xs line-clamp-2" x-text="summary.status_salah.description">
                            Deskripsi</p>
                        <div class="mt-4 flex items-center justify-between text-xs font-semibold text-indigo-400">
                            <span>Buka Detail Pasien</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"
                                :class="activeCategory === 'status_salah' ? 'rotate-90' : ''"></i>
                        </div>
                    </div>

                    <!-- Card 7: Pasien Tanpa Surat Kontrol -->
                    <div class="glass-card glass-card-hover rounded-2xl p-6 relative overflow-hidden cursor-pointer"
                        :class="activeCategory === 'tanpa_surat_kontrol' ? 'border-teal-500/40 bg-white' : ''"
                        @click="selectCategory('tanpa_surat_kontrol')">
                        <div
                            class="absolute top-0 right-0 w-24 h-24 bg-teal-500/5 rounded-bl-full pointer-events-none">
                        </div>
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <div
                                class="w-12 h-12 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center">
                                <i class="fa-solid fa-file-signature text-teal-500 text-lg"></i>
                            </div>
                            <div class="text-right">
                                <div class="text-3xl font-extrabold text-teal-500"
                                    x-text="summary.tanpa_surat_kontrol.count">0</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Pasien</div>
                            </div>
                        </div>
                        <h3 class="text-base font-bold text-slate-800 mb-1" x-text="summary.tanpa_surat_kontrol.title">Tanpa Surat Kontrol</h3>
                        <p class="text-slate-500 text-xs line-clamp-2" x-text="summary.tanpa_surat_kontrol.description">Deskripsi</p>
                        <div class="mt-4 flex items-center justify-between text-xs font-semibold text-teal-500">
                            <span>Buka Detail Pasien</span>
                            <i class="fa-solid fa-chevron-right text-[10px]"
                                :class="activeCategory === 'tanpa_surat_kontrol' ? 'rotate-90' : ''"></i>
                        </div>
                    </div>

                </div>

                <!-- Detailed Expandable List Container -->
                <div x-show="activeCategory" x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="glass-card rounded-2xl p-6 shadow-xl border border-slate-200 mb-12">

                    <!-- Table Header Controls -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-slate-800" x-text="summary[activeCategory].title">
                                Detail Pasien
                            </h3>
                            <p class="text-xs text-slate-600 mt-0.5" x-text="summary[activeCategory].description">
                                Penjelasan kriteria kepatuhan berkas.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Search Field -->
                            <div class="relative w-full sm:w-64">
                                <input type="text" x-model="searchTerm" placeholder="Cari Pasien..."
                                    class="w-full bg-white border border-slate-300 rounded-xl pl-9 pr-4 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition">
                                <i
                                    class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            </div>

                            <!-- Export to CSV -->
                            <button @click="exportToCSV()"
                                class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-950 font-semibold text-xs rounded-xl border border-slate-350 transition flex items-center gap-1.5 shadow-sm">
                                <i class="fa-solid fa-file-csv text-slate-500"></i> Export CSV
                            </button>
                        </div>
                    </div>

                    <!-- Scrollable Table -->
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr
                                    class="border-b border-slate-200 text-slate-500 font-semibold tracking-wider bg-slate-100/60">
                                    <th class="py-3.5 px-4 rounded-l-xl">No. Rawat</th>
                                    <th class="py-3.5 px-4">No. RM</th>
                                    <th class="py-3.5 px-4">Nama Pasien</th>
                                    <th class="py-3.5 px-4">Poliklinik</th>
                                    <th class="py-3.5 px-4">Tgl Registrasi</th>
                                    <th class="py-3.5 px-4">Cara Bayar</th>
                                    <th class="py-3.5 px-4">No. SEP</th>
                                    <th class="py-3.5 px-4">No. Resep</th>
                                    <th class="py-3.5 px-4">No. Surat Kontrol</th>
                                    <th class="py-3.5 px-4 rounded-r-xl">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="pat in getFilteredPatients()" :key="pat.no_rawat">
                                    <tr class="border-b border-slate-100 hover:bg-slate-50/80 transition">
                                        <td class="py-3 px-4 font-mono text-slate-600" x-text="pat.no_rawat"></td>
                                        <td class="py-3 px-4 text-slate-600" x-text="pat.no_rkm_medis"></td>
                                        <td class="py-3 px-4 font-semibold text-slate-850" x-text="pat.nm_pasien"></td>
                                        <td class="py-3 px-4 text-slate-600" x-text="pat.nm_poli"></td>
                                        <td class="py-3 px-4 text-slate-600" x-text="pat.tgl_registrasi"></td>
                                        <td class="py-3 px-4 text-slate-600" x-text="pat.png_jawab"></td>
                                        <td class="py-3 px-4 font-mono"
                                            :class="pat.no_sep === '-' ? 'text-slate-400' : 'text-indigo-600 font-semibold'"
                                            x-text="pat.no_sep"></td>
                                        <td class="py-3 px-4 font-mono"
                                            :class="pat.no_resep === '-' ? 'text-slate-400' : 'text-indigo-600 font-semibold'"
                                            x-text="pat.no_resep"></td>
                                        <td class="py-3 px-4 font-mono"
                                            :class="pat.no_surat_kontrol === '-' ? 'text-slate-400' : 'text-indigo-600 font-semibold'"
                                            x-text="pat.no_surat_kontrol"></td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold" :class="{
                                                      'bg-emerald-500/10 text-emerald-700 border border-emerald-500/20': pat.stts === 'Sudah',
                                                      'bg-amber-500/10 text-amber-700 border border-amber-500/20': pat.stts === 'Belum',
                                                      'bg-red-500/10 text-red-700 border border-red-500/20': pat.stts === 'Batal',
                                                  }" x-text="pat.stts">
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <template x-if="getFilteredPatients().length === 0">
                                    <tr>
                                        <td colspan="10" class="text-center py-8 text-slate-500 font-medium">
                                            Tidak ada data pasien ditemukan.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Footer -->
    <footer
        class="w-full text-center py-6 border-t border-slate-200 text-xs text-slate-500 relative z-10 mt-16 bg-white/40">
        &copy; <?= date('Y'); ?> PIT RSUD MERAUKE
    </footer>

    <script>
        function auditApp() {
            return {
                filters: {
                    kd_poli: '',
                    kd_pj: '',
                    bulan: '<?= $current_month; ?>',
                    tahun: '<?= $current_year; ?>'
                },
                loading: false,
                hasData: false,
                periode: '',
                searchTerm: '',
                activeCategory: '',
                summary: {
                    tanpa_sep: { title: 'Pasien Tanpa SEP', description: '', count: 0, patients: [] },
                    resep_tanpa_sep: { title: 'Resep Tanpa SEP', description: '', count: 0, patients: [] },
                    tanpa_tindakan: { title: 'Tanpa Tindakan', description: '', count: 0, patients: [] },
                    resep_tanpa_tindakan: { title: 'Resep Tanpa Tindakan', description: '', count: 0, patients: [] },
                    tidak_dilayani: { title: 'Pasien Tidak Dilayani', description: '', count: 0, patients: [] },
                    status_salah: { title: 'Status Salah', description: '', count: 0, patients: [] },
                    tanpa_surat_kontrol: { title: 'Tanpa Surat Kontrol', description: '', count: 0, patients: [] }
                },

                async fetchData() {
                    this.loading = true;
                    this.hasData = false;
                    this.activeCategory = '';
                    this.searchTerm = '';

                    try {
                        const formData = new FormData();
                        formData.append('kd_poli', this.filters.kd_poli);
                        formData.append('kd_pj', this.filters.kd_pj);
                        formData.append('bulan', this.filters.bulan);
                        formData.append('tahun', this.filters.tahun);

                        const response = await fetch('api/get_kepatuhan_remunerasi_public.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();
                        if (result.success) {
                            this.summary = result.data;
                            this.periode = result.periode;
                            this.hasData = true;
                            // Select first category by default
                            this.activeCategory = 'tanpa_sep';
                        } else {
                            alert('Gagal mengambil data: ' + result.error);
                        }
                    } catch (error) {
                        console.error('Fetch error:', error);
                        alert('Terjadi kesalahan jaringan atau server.');
                    } finally {
                        this.loading = false;
                    }
                },

                selectCategory(cat) {
                    if (this.activeCategory === cat) {
                        this.activeCategory = ''; // toggle collapse
                    } else {
                        this.activeCategory = cat;
                    }
                    this.searchTerm = '';
                },

                getFilteredPatients() {
                    if (!this.activeCategory || !this.summary[this.activeCategory]) return [];
                    const patients = this.summary[this.activeCategory].patients;
                    if (!this.searchTerm) return patients;

                    const term = this.searchTerm.toLowerCase();
                    return patients.filter(p => {
                        return (p.no_rawat && p.no_rawat.toLowerCase().includes(term)) ||
                            (p.no_rkm_medis && p.no_rkm_medis.toLowerCase().includes(term)) ||
                            (p.nm_pasien && p.nm_pasien.toLowerCase().includes(term)) ||
                            (p.nm_poli && p.nm_poli.toLowerCase().includes(term)) ||
                            (p.png_jawab && p.png_jawab.toLowerCase().includes(term)) ||
                            (p.no_sep && p.no_sep.toLowerCase().includes(term)) ||
                            (p.no_resep && p.no_resep.toLowerCase().includes(term)) ||
                            (p.no_surat_kontrol && p.no_surat_kontrol.toLowerCase().includes(term));
                    });
                },

                exportToCSV() {
                    const patients = this.getFilteredPatients();
                    if (patients.length === 0) return;

                    const categoryTitle = this.summary[this.activeCategory].title;
                    const headers = ['No. Rawat', 'No. RM', 'Nama Pasien', 'Poliklinik', 'Tgl Registrasi', 'Cara Bayar', 'No. SEP', 'No. Resep', 'No. Surat Kontrol', 'Status'];

                    let csvContent = "data:text/csv;charset=utf-8,\uFEFF";
                    csvContent += headers.join(';') + "\r\n";

                    patients.forEach(p => {
                        const row = [
                            p.no_rawat,
                            p.no_rkm_medis,
                            p.nm_pasien,
                            p.nm_poli,
                            p.tgl_registrasi,
                            p.png_jawab,
                            p.no_sep,
                            p.no_resep,
                            p.no_surat_kontrol,
                            p.stts
                        ].map(val => `"${val.replace(/"/g, '""')}"`);
                        csvContent += row.join(';') + "\r\n";
                    });

                    const encodedUri = encodeURI(csvContent);
                    const link = document.createElement("a");
                    link.setAttribute("href", encodedUri);

                    const filename = `${categoryTitle.toLowerCase().replace(/\s+/g, '_')}_${this.filters.bulan}_${this.filters.tahun}.csv`;
                    link.setAttribute("download", filename);
                    document.body.appendChild(link);

                    link.click();
                    document.body.removeChild(link);
                }
            }
        }
    </script>
</body>

</html>
<?php
mysqli_close($koneksi);
?>