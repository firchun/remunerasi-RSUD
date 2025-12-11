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
              Remon Tindakan RANAP - RSUD MERAUKE
            </h2>
            <p class="text-sm text-green-600">
              Monitoring tindakan rawat inap yang ditangani oleh dokter dan petugas
            </p>
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
                <label class="block text-sm font-medium text-gray-700 mb-2">Gedung</label>
                <select id="gedung"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                  <option value="">Semua Gedung</option>
                  <option value="boha">boha</option>
                  <option value="maleo">maleo</option>
                  <option value="mambruk">mambruk</option>
                  <option value="rusa I.">rusa I</option>
                  <option value="rusa II.">rusa II.</option>
                  <option value="kangguru">kangguru</option>
                  <option value="cenderawasih">cenderawasih</option>
                  <option value="kuskus">kuskus</option>
                  <option value="urip">urip</option>
                  <option value="icu">icu</option>
                  <option value="picu">picu</option>
                  <option value="nicu">nicu</option>
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
              <thead class="bg-green-800 text-white">
                <tr>
                  <th class="px-2 text-left">No.</th>
                  <th class="px-2  text-left">No.Rawat</th>
                  <th class="px-2  text-left">No.SEP</th>
                  <th class="px-2  text-left">No.RM</th>
                  <th class="px-2  text-left">Jenis</th>
                  <th class="px-2  text-left">Dokter</th>
                  <th class="px-2  text-left">Kamar</th>
                  <!-- tindakan -->
                  <th class="px-2  text-right">Sarana (Tindakan)</th>
                  <th class="px-2  text-right">Dokter (Tindakan)</th>
                  <th class="px-2  text-right">Perawat (Tindakan)</th>
                  <th class="px-2  text-right">non-medis (Tindakan)</th>
                  <th class="px-2  text-right">Total (Tindakan)</th>
                  <!-- operasi -->
                  <th class="px-2  text-right">Total Perina (Operasi)</th>
                  <th class="px-2  text-right">Total onloop (Operasi)</th>
                  <th class="px-2  text-right">Total bidan (Operasi)</th>
                  <th class="px-2  text-right">Total anestesi (Operasi)</th>
                  <th class="px-2  text-right">Total asisten operator (Operasi)</th>
                  <th class="px-2  text-right">Total operator (Operasi)</th>
                  <th class="px-2  text-right">Total (Operasi)</th>
                  <!-- obat -->
                  <th class="text-right">Racikan (Obat)</th>
                  <th class="text-right">Non-racikan (obat)</th>
                  <th class="text-right">Operasi (obat)</th>
                  <th class="text-right">Jasa Farmasi</th>
                  <th class="px-2  text-right">Total Obat (11%)</th>
                  <!-- kmar -->
                  <th class="px-2  text-right">Lama</th>
                  <th class="px-2  text-right">Tarif Kamar</th>
                  <!-- lab -->
                  <th class="px-2  text-right">sarana (Lab)</th>
                  <th class="px-2  text-right">dokter (Lab)</th>
                  <th class="px-2  text-right">petugas (Lab)</th>
                  <th class="px-2  text-right">non-medis (Lab)</th>
                  <th class="px-2  text-right">Total (Lab)</th>
                  <!-- radiologi -->
                  <th class="px-2  text-right">sarana (radiologi)</th>
                  <th class="px-2  text-right">dokter (radiologi)</th>
                  <th class="px-2  text-right">petugas (radiologi)</th>
                  <th class="px-2  text-right">non-medis (radiologi)</th>
                  <th class="px-2  text-right">Total (radiologi)</th>
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
              <tfoot class="bg-green-800 font-bold text-white">
                <tr>
                  <th colspan="7" class="text-right">TOTAL</th>
                  <!-- Footer nilai total -->
                  <th class="text-right">Sarana (tindakan)</th>
                  <th class="text-right">JM Dokter (tindakan)</th>
                  <th class="text-right">JM Perawat (tindakan)</th>
                  <th class="text-right">non-medis (tindakan)</th>
                  <th class="text-right">Total Tindakan</th>
                  <!-- operasi -->
                  <th class="px-2  text-right">Total onloop (Operasi)</th>
                  <th class="px-2  text-right">Total bidan (Operasi)</th>
                  <th class="px-2  text-right">Total anestesi (Operasi)</th>
                  <th class="px-2  text-right">Total asisten operator (Operasi)</th>
                  <th class="px-2  text-right">Total operator (Operasi)</th>
                  <th class="px-2  text-right">Total (Operasi)</th>
                  <!-- obat -->
                  <th class="text-right">Racikan (Obat)</th>
                  <th class="text-right">Non-racikan (obat)</th>
                  <th class="text-right">Operasi (obat)</th>
                  <th class="text-right">Jasa Farmasi</th>
                  <th class="text-right">Total Obat (11%)</th>
                  <!-- kmar -->
                  <th class="px-2  text-right">Lama</th>
                  <th class="px-2  text-right">Tarif Kamar</th>
                  <!-- lab -->
                  <th class="px-2  text-right">sarana (Lab)</th>
                  <th class="px-2  text-right">dokter (Lab)</th>
                  <th class="px-2  text-right">petugas (Lab)</th>
                  <th class="px-2  text-right">non-petugas (Lab)</th>
                  <th class="px-2  text-right">Total Lab</th>
                  <!-- radiologi -->
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
                <tr>
                  <th colspan="7" class="text-right">TOTAL</th>
                  <!-- Footer nilai total -->
                  <th class="text-right">Sarana (tindakan)</th>
                  <th class="text-right">JM Dokter (tindakan)</th>
                  <th class="text-right">JM Perawat (tindakan)</th>
                  <th class="text-right">non-medis (tindakan)</th>
                  <th class="text-right">Total Tindakan</th>
                  <!-- operasi -->
                  <th class="px-2  text-right">Total onloop (Operasi)</th>
                  <th class="px-2  text-right">Total bidan (Operasi)</th>
                  <th class="px-2  text-right">Total anestesi (Operasi)</th>
                  <th class="px-2  text-right">Total asisten operator (Operasi)</th>
                  <th class="px-2  text-right">Total operator (Operasi)</th>
                  <th class="px-2  text-right">Total (Operasi)</th>
                  <!-- obat -->
                  <th class="text-right">Racikan (Obat)</th>
                  <th class="text-right">Non-racikan (obat)</th>
                  <th class="text-right">Operasi (obat)</th>
                  <th class="text-right">Jasa Farmasi</th>
                  <th class="text-right">Total Obat (11%)</th>
                  <!-- kmar -->
                  <th class="px-2  text-right">Lama</th>
                  <th class="px-2  text-right">Tarif Kamar</th>
                  <!-- lab -->
                  <th class="px-2  text-right">sarana (Lab)</th>
                  <th class="px-2  text-right">dokter (Lab)</th>
                  <th class="px-2  text-right">petugas (Lab)</th>
                  <th class="px-2  text-right">non-petugas (Lab)</th>
                  <th class="px-2  text-right">Total Lab</th>
                  <!-- radiologi -->
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