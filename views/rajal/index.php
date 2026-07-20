<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$query_poli = "SELECT * FROM poliklinik where status = '1' ORDER BY nm_poli";
$result_poli = mysqli_query($koneksi, $query_poli);

$query_pj = "SELECT * FROM penjab where status = '1' ORDER BY kd_pj";
$result_pj = mysqli_query($koneksi, $query_pj);

?>

<?php
$pageTitle = 'Rawat Jalan - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  table.dataTable td { color: #1f2937 !important; } table.dataTable thead th { color: #ffffff !important; }
  #tabelTindakan th, #tabelTindakan td { white-space: nowrap; }
  #tabelTindakan tbody td { padding: 2px 4px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: top !important; text-align: left !important; }
  table.dataTable { width: auto !important; }
  .dt-buttons { margin-bottom: 10px; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>

<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">

  <h3 onclick="toggleFilter()"
    class="text-lg font-semibold mb-4 text-green-800 cursor-pointer flex items-center justify-between">
    <span class="flex items-center justify-center">
      <i
        class="fas fa-filter mr-2 flex items-center justify-center  w-[40px] h-[40px] rounded-full bg-green-200"></i>Filter
      Pencarian</span>
    <i id="filterIcon" class="fas fa-chevron-up text-green-600  rounded-full transition-transform 
          hover:bg-green-200 w-[40px] h-[40px] flex items-center justify-center rotate-180">
    </i>
  </h3>

  <div id="filterContent" class="transition-all duration-300 overflow-hidden hidden">

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
        <input type="datetime-local" id="tgl1"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
        <input type="datetime-local" id="tgl2"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Atau Pilih Bulan</label>
        <div class="flex gap-2">
          <select id="filter_bulan"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <option value="">-- Pilih Bulan --</option>
            <option value="1">Januari</option>
            <option value="2">Februari</option>
            <option value="3">Maret</option>
            <option value="4">April</option>
            <option value="5">Mei</option>
            <option value="6">Juni</option>
            <option value="7">Juli</option>
            <option value="8">Agustus</option>
            <option value="9">September</option>
            <option value="10">Oktober</option>
            <option value="11">November</option>
            <option value="12">Desember</option>
          </select>
          <select id="filter_tahun"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            <?php
            $tahun_sekarang = date('Y');
            for ($y = $tahun_sekarang; $y >= $tahun_sekarang - 5; $y--) {
              echo "<option value='$y'" . ($y == $tahun_sekarang ? " selected" : "") . ">$y</option>";
            }
            ?>
          </select>
        </div>
        <p class="text-xs text-gray-400 mt-1">* Jika dipilih, akan menimpa filter tanggal di atas</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Poliklinik</label>
        <select id="kd_poli"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          <option value="">Semua Poliklinik</option>
          <?php while ($row = mysqli_fetch_assoc($result_poli)): ?>
            <option value="<?= $row['kd_poli'] ?>"><?= $row['nm_poli'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Jenis</label>
        <select id="kd_pj"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          <option value="">Semua Jenis</option>
          <?php while ($row = mysqli_fetch_assoc($result_pj)): ?>
            <option value="<?= $row['kd_pj'] ?>"><?= $row['png_jawab'] ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Status SEP</label>
        <select id="filter_sep"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
          <option value="semua" selected>Semua Data</option>
          <option value="ada">Ada SEP</option>
          <option value="tidak_ada">Tidak Ada SEP</option>
        </select>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
        <input type="text" id="tcari" placeholder="Cari No. Rawat, Pasien..."
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
      </div>
    </div>
    <div class="mt-4 flex gap-2">
      <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-xl transition">
        <i class="fas fa-search mr-2"></i>Cari Data
      </button>
      <button onclick="resetFilter()"
        class="border border-gray-600 text-gray-600 p-2 rounded-xl hover:bg-gray-200 transition">
        <i class="fas fa-redo mr-2"></i>Reset
      </button>
      <button onclick="exportCSV()" class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-xl transition">
        <i class="fas fa-file-csv mr-2"></i>Export CSV (Semua Data)
      </button>
    </div>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <div class="overflow-x-auto">
    <table id="tabelTindakan" class="display w-full">
      <thead class="bg-green-800 text-white">
        <tr>
          <th class="px-2 text-left">No.</th>
          <th class="px-2  text-left">No.Rawat</th>
          <th class="px-2  text-left">No.SEP</th>
          <th class="px-2  text-left">No.RM</th>
          <th class="px-2  text-left">Jenis</th>
          <th class="px-2  text-left">Dokter</th>
          <th class="px-2  text-left">Dokter Lain</th>
          <th class="px-2  text-left">Polik</th>
          <!-- tindakan -->
          <th class="px-2  text-right">Sarana (Tindakan)</th>
          <th class="px-2  text-right">Dokter (Tindakan)</th>
          <th class="px-2  text-right">Perawat (Tindakan)</th>
          <th class="px-2  text-right">non-medis (Tindakan)</th>
          <th class="px-2  text-right">Total (Tindakan)</th>
          <!-- operasi -->
          <th class="px-2  text-right">Operasi</th>
          <th class="px-2  text-right">Petugas</th>
          <th class="px-2  text-right">Anastesi</th>
          <th class="px-2  text-right">Total Sarana (Operasi)</th>
          <th class="px-2  text-right">Total Perina (Operasi)</th>
          <th class="px-2  text-right">Total onloop (Operasi)</th>
          <th class="px-2  text-right">Total bidan (Operasi)</th>
          <th class="px-2  text-right">Total Dokter anestesi (Operasi)</th>
          <th class="px-2  text-right">Total asisten anestesi (Operasi)</th>
          <th class="px-2  text-right">Total asisten operator (Operasi)</th>
          <th class="px-2  text-right">Total operator (Operasi)</th>
          <th class="px-2  text-right">Total (Operasi)</th>
          <!-- obat -->
          <th class="text-right">Racikan (Obat)</th>
          <!-- <th class="px-2  text-right">No.resep racikan</th> -->
          <th class="text-right">Non-racikan (obat)</th>
          <!-- <th class="px-2  text-right">No.resep non-racikan</th> -->
          <th class="text-right">Operasi (obat)</th>
          <!-- <th class="px-2  text-right">No.resep Operasi</th> -->
          <th class="text-right">Jasa Farmasi</th>
          <th class="px-2  text-right">Total Obat</th>
          <!-- lab -->
          <!-- <th class="px-2  text-right">Pemeriksaan (Lab)</th> -->
          <th class="px-2  text-right">sarana (Lab)</th>
          <th class="px-2  text-right">dokter (Lab)</th>
          <th class="px-2  text-right">petugas (Lab)</th>
          <th class="px-2  text-right">non-medis (Lab)</th>
          <th class="px-2  text-right">Total (Lab)</th>
          <!-- radiologi -->
          <th class="px-2  text-right">Dokter Radiologi</th>
          <th class="px-2  text-right">Radiologi</th>
          <th class="px-2  text-right">sarana (radiologi)</th>
          <th class="px-2  text-right">dokter (radiologi)</th>
          <th class="px-2  text-right">petugas (radiologi)</th>
          <th class="px-2  text-right">non-medis (radiologi)</th>
          <th class="px-2  text-right">Total (radiologi)</th>
          <!-- total -->
          <th class="px-2  text-right">Total Bayar</th>
          <th class="px-2  text-right">Total BPJS</th>

        </tr>
      </thead>
      <tbody></tbody>
      <tfoot class="bg-green-800 font-bold text-white">
        <tr>
          <th colspan="8" class="text-right px-2">TOTAL AKHIR :</th>

          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="px-2"></th>
          <th class="px-2"></th>
          <th class="px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="px-2"></th>
          <th class="text-right px-2"></th>
          <th class="px-2"></th>
          <th class="text-right px-2"></th>
          <th class="px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <!-- <th class="text-right px-2"></th> -->
          <!-- <th class="text-right px-2"></th> -->
          <!-- <th class="text-right px-2"></th> -->
          <th class="text-right px-2"></th>
          <th class="px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
<?php include 'modal.php' ?>
<?php include 'script.php' ?>
<?php require_once '../layouts/footer.php'; ?>