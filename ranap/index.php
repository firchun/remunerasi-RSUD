<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$query_bangsal = "SELECT * FROM bangsal where status = '1' ORDER BY nm_bangsal";
$result_bangsal = mysqli_query($koneksi, $query_bangsal);

$query_penjab = "SELECT * FROM penjab where status = '1' ORDER BY png_jawab";
$result_penjab = mysqli_query($koneksi, $query_penjab);

?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Remunerasi RANAP RSUD MERAUKE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
  <script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <style>
  #tabelTindakan td,
  #tabelTindakan th {
    color: #1f2937 !important;
  }

  table.dataTable.stripe tbody tr.odd,
  table.dataTable.stripe tbody tr.even {
    color: #1f2937 !important;
  }

  table {
    color: #1f2937 !important;
  }

  table.dataTable.hover tbody tr:hover {
    color: #1f2937 !important;
  }

  #tabelTindakan th,
  #tabelTindakan td {
    white-space: nowrap;
  }

  table.dataTable {
    width: auto !important;
  }

  .dt-buttons {
    margin-bottom: 10px;
  }

  #tabelTindakan tbody td {
    padding: 2px 4px !important;
    margin: 0 !important;
    line-height: 1.4 !important;
    height: auto;
    border: 0.5px solid #d1d5db;
    vertical-align: top !important;
    text-align: left !important;
  }

  .dt-button.buttons-excel.buttons-html5 {
    background-color: #16a34a !important;
    color: white !important;
    border: none !important;
    padding: 16px 20px !important;
    border-radius: 8px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer;
    transition: 0.25s ease-in-out;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  .dt-button.buttons-pdf.buttons-html5 {
    background-color: rgb(250, 40, 40) !important;
    color: white !important;
    border: none !important;
    padding: 16px 20px !important;
    border-radius: 8px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer;
    transition: 0.25s ease-in-out;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }

  /* Menjaga z-index agar kolom yang di-freeze tetap di atas */
  .DTFC_LeftWrapper table,
  .DTFC_RightWrapper table {
    background-color: white !important;
    margin-bottom: 0 !important;
  }

  /* Memastikan border pada kolom yang di-freeze tetap terlihat */
  th.dtfc-fixed-left,
  td.dtfc-fixed-left {
    background-color: white !important;
    /* Gunakan warna solid agar data di bawahnya tidak tembus */
    border-right: 1px solid #d1d5db !important;
    z-index: 10;
  }

  /* Pastikan header yang di-freeze tetap hijau */
  thead tr>.dtfc-fixed-left {
    background-color: #166534 !important;
    /* bg-green-800 */
  }
  </style>
</head>

<body class="bg-gray-100">
  <div class="flex h-screen overflow-hidden">
    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="fixed top-0 left-0 w-full z-50 backdrop-blur-md bg-white/60 shadow-sm">
        <div class="flex items-center px-4 py-3">
          <a href="../index.php">
            <img src="https://absenrsudmerauke.rifill.id/assetsdata/img/logorsud.png" alt="Logo RSUD Merauke"
              class="w-16 h-16 mr-4">
          </a>
          <div>
            <h2 class="text-xl font-bold text-green-800">
              Remunerasi Tindakan RANAP - RSUD MERAUKE
            </h2>

          </div>

        </div>
      </header>

      <main class="flex-1 overflow-y-auto px-6 pb-6 pt-[100px]">

        <div class="flex justify-end my-3">
          <button class="px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700"
            onclick="document.getElementById('modalKolom').classList.remove('hidden')">
            Pilih Kolom
          </button>

        </div>
        <div class="bg-white rounded-2xl border border-green-700 p-6 mb-6">

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
                <label class="block text-sm font-medium text-gray-700 mb-2">Kamar</label>
                <select id="kd_bangsal"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                  <option value="">Semua Kamar</option>
                  <?php while ($row = mysqli_fetch_assoc($result_bangsal)): ?>
                  <option value="<?= $row['kd_bangsal'] ?>"> <?= $row['nm_bangsal'] ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Status Pulang</label>
                <select id="status_pulang"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                  <option value="belum_pulang" selected>Belum Pulang</option>
                  <option value="sudah_pulang">Sudah Pulang</option>
                  <option value="semua">Semua</option>
                </select>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Gedung</label>
                <select id="gedung"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                  <option value="">Semua Gedung</option>
                  <option value="boha">Boha</option>
                  <option value="maleo">Maleo</option>
                  <option value="mambruk">Mambruk</option>
                  <option value="rusa i">Rusa I</option>
                  <option value="rusa ii">Rusa II</option>
                  <option value="kangguru">Kangguru</option>
                  <option value="cenderawasih">Cenderawasih</option>
                  <option value="kuskus">Kuskus</option>
                  <option value="urip">Urip</option>
                  <option value="icu">ICU</option>
                  <option value="picu">PICU</option>
                  <option value="nicu">NICU</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Bayar</label>
                <select id="kd_pj"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                  <option value="">Semua Jenis</option>
                  <?php while ($row = mysqli_fetch_assoc($result_penjab)): ?>
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
              <button onclick="loadData()"
                class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition">
                <i class="fas fa-search mr-2"></i>Cari Data
              </button>
              <button onclick="resetFilter()"
                class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition">
                <i class="fas fa-redo mr-2"></i>Reset
              </button>
            </div>
          </div>
        </div>
        <div class="bg-white rounded-2xl border border-green-700 p-6">
          <div class="overflow-x-auto">
            <table id="tabelTindakan" class="display w-full">
              <thead class="bg-green-800 text-white p-1 m-0">
                <tr>
                  <th class="px-2 text-left">No.</th>
                  <th class="px-2  text-left">No.Rawat</th>
                  <th class="px-2  text-left">No.SEP</th>
                  <th class="px-2  text-left">No.RM</th>
                  <th class="px-2  text-left">Jenis</th>
                  <th class="px-2  text-left">DPJP</th>
                  <th class="px-2  text-left">Dokter Menangani</th>
                  <th class="px-2  text-left">Kamar</th>
                  <th class="px-2  text-left">Riwayat Kamar</th>
                  <th class="px-2  text-left">Jasa perawat </th>
                  <th class="px-2  text-left">Jasa dokter</th>
                  <th class="px-2  text-right">Lama</th>
                  <th class="px-2  text-right">Tarif Kamar</th>
                  <th class="px-2  text-left">Status Pulang</th>
                  <!-- tindakan -->
                  <!-- <th class="text-right">Perawat</th>
                  <th class="text-right">Dokter</th> -->
                  <th class="px-2  text-right">Sarana (T)</th>
                  <th class="px-2  text-right">Dokter (T)</th>
                  <th class="px-2  text-right">Perawat (T)</th>
                  <th class="px-2  text-right">non-medis (T)</th>
                  <th class="px-2  text-right">Total Ranap (T)</th>
                  <th class="px-2  text-right">Total Rajal (T)</th>
                  <th class="px-2  text-right">Ranap DrPr (T)</th>
                  <th class="px-2  text-right">Rajal DrPr (T)</th>
                  <!-- operasi -->
                  <th class="px-2  text-right">Operasi</th>
                  <!-- <th class="px-2  text-right">Petugas</th> -->
                  <th class="px-2  text-right">Anastesi</th>
                  <th class="px-2  text-right"> Sarana (OP)</th>
                  <th class="px-2  text-right"> Perina (OP)</th>
                  <th class="px-2  text-right"> onloop (OP)</th>
                  <th class="px-2  text-right"> bidan (OP)</th>
                  <th class="px-2  text-right"> Dokter anestesi (OP)</th>
                  <th class="px-2  text-right"> asisten anestesi (OP)</th>
                  <th class="px-2  text-right"> asisten operator (OP)</th>
                  <th class="px-2  text-right"> operator (OP)</th>
                  <th class="px-2  text-right"> Total (OP)</th>
                  <!-- obat -->
                  <th class="text-right">Racikan (Obat)</th>
                  <th class="px-2  text-right">No.resep racikan</th>
                  <th class="text-right">Non-racikan (obat)</th>
                  <th class="px-2  text-right">No.resep non-racikan</th>
                  <th class="text-right">Operasi (obat)</th>
                  <th class="px-2  text-right">No.resep Operasi</th>
                  <th class="text-right">Jasa Farmasi</th>
                  <th class="px-2  text-right">Total Obat</th>
                  <!-- obat pulang -->
                  <th class="px-2  text-right">jasa Farmasi Pulang</th>
                  <th class="px-2  text-right">Total obat Pulang</th>

                  <!-- lab -->
                  <!-- <th class="px-2  text-right">Pemeriksaan (Lab)</th> -->
                  <th class="px-2  text-right">sarana (Lab)</th>
                  <th class="px-2  text-right">dokter (Lab)</th>
                  <th class="px-2  text-right">petugas (Lab)</th>
                  <th class="px-2  text-right">non-medis (Lab)</th>
                  <th class="px-2  text-right">Total (Lab)</th>
                  <!-- radiologi -->
                  <th class="px-2  text-right">Radiologi</th>
                  <th class="px-2  text-right">sarana (R)</th>
                  <th class="px-2  text-right">dokter (R)</th>
                  <th class="px-2  text-right">petugas (R)</th>
                  <th class="px-2  text-right">non-medis (R)</th>
                  <th class="px-2  text-right">Total (R)</th>
                  <!-- total -->
                  <th class="px-2  text-right">Total Bayar</th>
                  <th class="px-2  text-right">Total BPJS</th>
                  <!-- pembagian -->
                  <!-- <th class="text-right">Total Jasa</th>
                  <th class="text-right">Medis 85%</th>
                  <th class="text-right">Dokter 60%</th>
                  <th class="text-right">Perawat 40%</th>
                  <th class="text-right">Non-medis 15%</th>
                  <th class="text-right">% Dokter</th>
                  <th class="text-right">% Perawat</th>
                  <th class="text-right">% non-medis</th> -->
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot class="bg-green-800 font-bold text-white p-1 m-0">
                <tr>
                  <th colspan="12" class="text-right">TOTAL</th>
                  <th class="text-right">Kamar</th>
                  <th class="text-right">Status Pulang</th>
                  <!-- Footer nilai total -->
                  <th class="text-right">Sarana (tindakan)</th>
                  <th class="text-right">JM Dokter (tindakan)</th>
                  <th class="text-right">JM Perawat (tindakan)</th>
                  <th class="text-right">non-medis (tindakan)</th>
                  <th class="text-right">Total (Tindakan)</th>
                  <th class="text-right">Total Rajal (Tindakan)</th>
                  <th class="px-2  text-right">Ranap DrPr (T)</th>
                  <th class="px-2  text-right">Rajal DrPr (T)</th>
                  <!-- operasi -->
                  <th class="px-2  text-right">Operasi</th>
                  <!-- <th class="px-2  text-right">Petugas</th> -->
                  <th class="px-2  text-right">Anastesi</th>
                  <th class="px-2  text-right">Total Sarana (Operasi)</th>
                  <th class="px-2  text-right">Total perina (Operasi)</th>
                  <th class="px-2  text-right">Total onloop (Operasi)</th>
                  <th class="px-2  text-right">Total bidan (Operasi)</th>
                  <th class="px-2  text-right">Total Dokter anestesi (Operasi)</th>
                  <th class="px-2  text-right">Total asisten anestesi (Operasi)</th>
                  <th class="px-2  text-right">Total
                    asisten operator (Operasi)</th>
                  <th class="px-2  text-right">Total operator (Operasi)</th>
                  <th class="px-2  text-right">Total (Operasi)</th>
                  <!-- obat -->
                  <th class="text-right">Racikan (Obat)</th>
                  <th class="px-2  text-right">No.resep racikan</th>
                  <th class="text-right">Non-racikan (obat)</th>
                  <th class="px-2  text-right">No.resep non-racikan</th>
                  <th class="text-right">Operasi (obat)</th>
                  <th class="px-2  text-right">No.resep Operasi</th>
                  <th class="text-right">Jasa Farmasi</th>
                  <th class="px-2  text-right">Total Obat</th>
                  <!-- obat pulang -->
                  <th class="px-2  text-right">jasa Farmasi Pulang</th>
                  <th class="px-2  text-right">Total obat Pulang</th>

                  <!-- lab -->
                  <!-- <th class="px-2  text-right">Pemeriksaan (Lab)</th> -->
                  <th class="px-2  text-right">sarana (Lab)</th>
                  <th class="px-2  text-right">dokter (Lab)</th>
                  <th class="px-2  text-right">petugas (Lab)</th>
                  <th class="px-2  text-right">non-petugas (Lab)</th>
                  <th class="px-2  text-right">Total Lab</th>
                  <!-- radiologi -->
                  <th class="px-2  text-right">Radiologi</th>
                  <th class="px-2  text-right">sarana (radiologi)</th>
                  <th class="px-2  text-right">dokter (radiologi)</th>
                  <th class="px-2  text-right">petugas (radiologi)</th>
                  <th class="px-2  text-right">non-petugas (radiologi)</th>
                  <th class="px-2  text-right">Total radiologi</th>
                  <!-- total -->
                  <th class="text-right">Total Bayar</th>
                  <th class="text-right">Total BPJS</th>
                  <!-- pembagian -->
                  <!-- <th class="text-right">Total Jasa</th>
                  <th class="text-right">Medis 85%</th>
                  <th class="text-right">Dokter 60%</th>
                  <th class="text-right">Perawat 40%</th>
                  <th class="text-right">Non-medis 15%</th>
                  <th class="text-right">% Dokter</th>
                  <th class="text-right">% Perawat</th>
                  <th class="text-right">% non-medis</th> -->
                </tr>

              </tfoot>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>
  <?php include 'modal.php' ?>
  <?php include 'script.php' ?>
</body>

</html>