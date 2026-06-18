# Sistem Informasi Remunerasi RSUD Merauke

Aplikasi manajemen data tindakan dan remunerasi untuk RSUD Merauke.

## Tech Stack

| Komponen | Teknologi |
|---|---|
| **Frontend** | Tailwind CSS, Chart.js, DataTables, jQuery, Font Awesome |
| **Backend** | PHP 8+ (native, no framework) |
| **Database** | MySQL (`merauke_db`) |
| **Server** | Apache / XAMPP |

## Struktur Folder

```
remon/
├── index.php                  # Front controller (router)
├── .htaccess                  # Catch-all rewrite → index.php
├── login.php                  # Halaman login (standalone HTML+CSS)
├── .env                       # Konfigurasi database & aplikasi (tidak di-commit)
├── .env-contoh                # Template .env untuk developer lain
├── .gitignore                 # Mengecualikan .env
├── api/                       # Endpoint JSON untuk DataTables & Chart.js
│   ├── bpjs.php
│   ├── dashboard_kepatuhan.php
│   ├── export_ralan.php
│   ├── export_ranap.php
│   ├── export_jasaraharja.php
│   ├── get_data_ralan.php
│   ├── get_data_ranap.php
│   ├── get_detail_tindakan.php
│   ├── get_detail_tindakan_ranap.php
│   ├── get_detail_lab.php
│   ├── get_detail_lab_ranap.php
│   ├── get_detail_obat.php
│   ├── get_detail_obat_ranap.php
│   ├── get_monthly_report.php
│   ├── get_monthly_report_ranap.php
│   ├── get_report_gabungan.php
│   ├── jasa_petugas.php
│   ├── upload_bpjs.php
│   ├── upload_bpjs_ranap.php
│   ├── upload_bpjs_verifikasi.php  # Upload PDF BPJS Verifikasi
│   ├── save_bpjs_verifikasi.php    # Simpan hasil OCR
│   ├── get_bpjs_verifikasi.php     # Ambil data BPJS Verifikasi
│   ├── delete_bpjs_verifikasi.php  # Hapus data BPJS Verifikasi
│   ├── get_data_hitung_jasa_ralan.php  # DataTables Hitung Jasa Ralan
│   ├── export_hitung_jasa_ralan.php    # Export Excel Hitung Jasa Ralan
│   ├── get_data_hitung_jasa_ranap.php  # DataTables Hitung Jasa Ranap
│   └── export_hitung_jasa_ranap.php    # Export Excel Hitung Jasa Ranap
├── scripts/                    # Utility scripts
│   └── parse_bpjs_pdf.py       # OCR parser untuk PDF BPJS (Tesseract)
├── config/                    # Konfigurasi & autoload
│   ├── app.php                # BASE_URL, APP_NAME, TIMEZONE
│   ├── autoload.php           # PSR-0-like autoloader
│   ├── conf.php               # Koneksi database (fungsi bukakoneksi)
│   ├── env.php                # Loader .env → $_ENV + putenv()
│   ├── ErrorHandler.php
│   ├── logout.php
│   └── proses_login.php
├── controllers/               # MVC Controllers (partial)
│   ├── BaseController.php     # render(), renderRaw(), redirect()
│   ├── DashboardController.php
│   └── ErrorController.php
├── models/                    # MVC Models
│   └── Database.php           # Singleton koneksi database
├── public/                    # Entry point MVC alternate
│   ├── .htaccess
│   └── index.php
├── routes/                    # Routing MVC
│   └── web.php
├── vendor/                    # Composer dependencies
└── views/                     # Semua file tampilan
    ├── bpjs/index.php         # Data BPJS
    ├── bpjs_verifikasi/index.php # BPJS Verifikasi (upload PDF + OCR)
    ├── hitung_jasa_ralan/index.php # Hitung Jasa Rawat Jalan
    ├── hitung_jasa_ranap/index.php # Hitung Jasa Rawat Inap
    ├── bulanan_rajal/index.php # Laporan bulanan rawat jalan
    ├── bulanan_ranap/index.php # Laporan bulanan rawat inap
    ├── cari_petugas/index.php # Cari data paramedis/dokter
    ├── dashboard.php          # Konten dashboard (grafik kepatuhan)
    ├── errors/
    │   ├── 404.php
    │   └── 500.php
    ├── jasaraharja/index.php  # Tagihan Jasa Raharja
    ├── laporan_gabungan/index.php # Gabungan Ralan/Ranap + Lab/Radiologi/Farmasi
    ├── layouts/
    │   ├── header.php         # Sidebar, navbar, CSS/JS global
    │   └── footer.php         # Script toggle sidebar
    ├── rajal/
    │   ├── index.php          # Data tindakan rawat jalan
    │   ├── modal.php          # Modal upload CSV
    │   └── script.php         # DataTables + detail AJAX
    ├── ranap/
    │   ├── index.php          # Data tindakan rawat inap
    │   ├── modal.php          # Modal upload CSV
    │   └── script.php         # DataTables + detail AJAX
    └── tunsus.php             # Laporan tunjangan dokter spesialis DTPK
```

## Arsitektur

Aplikasi menggunakan **PHP front controller** di root `index.php` dengan `.htaccess` catch-all rewrite.

```
Browser → /remon/rajal
            ↓
.htaccess rewrite → /remon/index.php (apabila bukan file/directory nyata)
            ↓
index.php (router)
  ├── cocokkan path → require views/rajal/index.php + layouts
  └── fallback → dashboard (header + views/dashboard.php + footer)
```

### Rute

| URL | File |
|---|---|
| `/remon/` | `views/dashboard.php` (via `index.php`) |
| `/remon/rajal` | `views/rajal/index.php` |
| `/remon/ranap` | `views/ranap/index.php` |
| `/remon/bpjs` | `views/bpjs/index.php` |
| `/remon/bpjs-verifikasi` | `views/bpjs_verifikasi/index.php` |
| `/remon/hitung-jasa-ralan` | `views/hitung_jasa_ralan/index.php` |
| `/remon/hitung-jasa-ranap` | `views/hitung_jasa_ranap/index.php` |
| `/remon/bulanan-rajal` | `views/bulanan_rajal/index.php` |
| `/remon/bulanan-ranap` | `views/bulanan_ranap/index.php` |
| `/remon/cari-petugas` | `views/cari_petugas/index.php` |
| `/remon/jasaraharja` | `views/jasaraharja/index.php` |
| `/remon/laporan-gabungan` | `views/laporan_gabungan/index.php` |
| `/remon/tunsus` | `views/tunsus.php` |

### Alur Dashboard

```
Browser → /remon/
  ↓
index.php (router — path kosong, fallback ke dashboard)
  ├── require config/conf.php
  ├── set $pageTitle, $rootPath='', $extraHead (Chart.js CDN)
  ├── views/layouts/header.php  (sidebar, navbar)
  ├── views/dashboard.php       (grafik kepatuhan Chart.js)
  │     └── fetch → api/dashboard_kepatuhan.php
  └── views/layouts/footer.php
```

## Database

| Item | Detail |
|---|---|
| **Host** | `localhost` |
| **Database** | `sik` |
| **User** | `rsud` |
| **Charset** | `utf8` |
| **Timezone** | `Asia/Jayapura` |

> Kredensial diambil dari `.env` melalui `config/env.php`.

## Konfigurasi

| File | Fungsi |
|---|---|
| `.env` | Kredensial database & lingkungan |
| `config/app.php` | `BASE_URL`, `APP_NAME`, `APP_ENV`, `TIMEZONE` |
| `config/env.php` | Loader `.env` → `$_ENV` + `putenv()` |
| `config/conf.php` | Koneksi database via `bukakoneksi()` |
| `config/autoload.php` | Autoload controller & model |
| `config/ErrorHandler.php` | Error/exception handler |
| `config/proses_login.php` | Auth endpoint |
| `config/logout.php` | Session destroy |

## API Endpoints

Semua endpoint mengembalikan JSON dan dipanggil dari JavaScript (DataTables / Chart.js / fetch).

| Endpoint (relative) | Digunakan Oleh |
|---|---|
| `api/bpjs.php` | `views/bpjs/index.php` |
| `api/dashboard_kepatuhan.php` | `views/dashboard.php` (grafik) |
| `api/export_ralan.php` | `views/rajal/script.php` |
| `api/export_ranap.php` | `views/ranap/script.php` |
| `api/export_jasaraharja.php` | `views/jasaraharja/index.php` |
| `api/get_data_ralan.php` | `views/rajal/script.php` |
| `api/get_data_ranap.php` | `views/ranap/script.php` |
| `api/get_detail_tindakan.php` | `views/rajal/script.php` |
| `api/get_detail_tindakan_ranap.php` | `views/ranap/script.php` |
| `api/get_detail_lab.php` | `views/rajal/script.php` |
| `api/get_detail_lab_ranap.php` | `views/ranap/script.php` |
| `api/get_detail_obat.php` | `views/rajal/script.php` |
| `api/get_detail_obat_ranap.php` | `views/ranap/script.php` |
| `api/get_monthly_report.php` | `views/bulanan_rajal/index.php` |
| `api/get_monthly_report_ranap.php` | `views/bulanan_ranap/index.php` |
| `api/get_report_gabungan.php` | `views/laporan_gabungan/index.php` |
| `api/jasa_petugas.php` | `views/cari_petugas/index.php` |
| `api/upload_bpjs.php` | `views/rajal/modal.php`, `views/ranap/modal.php`, `views/bpjs/index.php` |
| `api/upload_bpjs_ranap.php` | `views/rajal/modal.php`, `views/ranap/modal.php`, `views/bpjs/index.php` |
| `api/upload_bpjs_verifikasi.php` | `views/bpjs_verifikasi/index.php` |
| `api/save_bpjs_verifikasi.php` | `views/bpjs_verifikasi/index.php` |
| `api/get_bpjs_verifikasi.php` | `views/bpjs_verifikasi/index.php` |
| `api/delete_bpjs_verifikasi.php` | `views/bpjs_verifikasi/index.php` |
| `api/get_data_hitung_jasa_ralan.php` | `views/hitung_jasa_ralan/index.php` |
| `api/export_hitung_jasa_ralan.php` | `views/hitung_jasa_ralan/index.php` |
| `api/get_data_hitung_jasa_ranap.php` | `views/hitung_jasa_ranap/index.php` |
| `api/export_hitung_jasa_ranap.php` | `views/hitung_jasa_ranap/index.php` |

## Fitur per Modul

| Modul | Deskripsi |
|---|---|
| **Dashboard** | Grafik kepatuhan 6 bulan (Rajal per Poliklinik, Ranap per Bangsal, Radiologi, Laboratorium) — filter bulan & tahun |
| **Rawat Jalan** | Data tindakan per poli, filter tanggal/poli/penjamin, detail lab/obat/tindakan, export Excel |
| **Rawat Inap** | Data tindakan per bangsal, filter tanggal/bangsal/penjamin, detail lab/obat/tindakan, export Excel |
| **RALAN Per-Bulan** | Rekapitulasi pendapatan rawat jalan per bulan per poli |
| **RANAP Per-Bulan** | Rekapitulasi pendapatan rawat inap per bulan per bangsal |
| **Laporan Gabungan** | Gabungan Ralan/Ranap + Lab + Radiologi + Farmasi |
| **BPJS** | Data total BPJS diterima, upload CSV |
| **BPJS Verifikasi** | Upload PDF verifikasi BPJS (scanned), OCR otomatis via Tesseract, preview & edit data, simpan ke database |
| **Hitung Jasa Ralan** | Perhitungan jasa rawat jalan: integrasi Total BPJS, 44% multiplier, kolom % + Jumlah per petugas, export Excel multi-sheet per poli |
| **Hitung Jasa Ranap** | Perhitungan jasa rawat inap (berdasarkan tgl_keluar): sama dengan Ralan + Sisa BPJS, filter grup bangsal, export Excel per bangsal |
| **Cari Paramedis/Dokter** | Cari data tindakan paramedis/dokter berdasarkan periode |
| **Jasa Raharja** | Tagihan Jasa Raharja per pasien, export |
| **Tunjangan Susila** | Laporan usulan tunjangan khusus dokter spesialis DTPK (PHPExcel) |

## Catatan

- Semua modul diakses via **clean URL** berkat `.htaccess` + PHP router di `index.php`
- `.env` menyimpan kredensial database (tidak di-commit; lihat `.env-contoh`)
- CSS/JS global via CDN (Tailwind, Font Awesome, jQuery, Chart.js, DataTables)
- Layout tunggal di `views/layouts/header.php` dan `views/layouts/footer.php`
- `$rootPath` diset sesuai kebutuhan: `''` untuk dashboard, `'../'` untuk modul
- OCR BPJS Verifikasi menggunakan **Tesseract** (CLI) via Python `pytesseract` + `pdf2image` — lihat `scripts/parse_bpjs_pdf.py`
- Hitung Jasa Ralan/Ranap menggunakan perhitungan sementara: `(persen/100) × (total_bpjs × 0.44)` — belum final
