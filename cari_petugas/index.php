<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pencarian Tindakan Petugas/Dokter - RSUD MERAUKE</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
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

  #tabelTindakan tbody td {
    padding: 4px !important;
    margin: 0 !important;
    height: auto;
    border: 0.5px solid #d1d5db;
    vertical-align: top !important;
    text-align: left !important;
  }

  table.dataTable {
    width: auto !important;
  }

  .dt-buttons {
    margin-bottom: 10px;
  }

  .dt-button.buttons-excel.buttons-html5,
  .dt-button.buttons-pdf.buttons-html5 {
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
  }

  .tindakan-group {
    margin: 1px 0;
    border-bottom: 1px solid rgb(0, 0, 0);
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
              Pencarian Tindakan Petugas/Dokter - RSUD MERAUKE
            </h2>
            <p class="text-sm text-green-600">
              Cari dan lihat detail tindakan yang dilakukan oleh petugas atau dokter
            </p>
          </div>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto px-6 pb-6 pt-[100px]">
        <div class="bg-white rounded-2xl border border-green-700 p-6 mb-6">
          <h3 onclick="toggleFilter()"
            class="text-lg font-semibold mb-4 text-green-800 cursor-pointer flex items-center justify-between">
            <span class="flex items-center justify-center">
              <i
                class="fas fa-filter mr-2 flex items-center justify-center w-[40px] h-[40px] rounded-full bg-green-200"></i>
              Form Pencarian
            </span>
            <i id="filterIcon" class="fas fa-chevron-up text-green-600 rounded-full transition-transform 
              hover:bg-green-200 w-[40px] h-[40px] flex items-center justify-center rotate-180"></i>
          </h3>

          <div id="filterContent" class="transition-all duration-300 overflow-hidden hidden">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nama Petugas/Dokter</label>
                <input type="text" id="nama_petugas" placeholder="Masukkan nama petugas atau dokter..."
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
                <input type="month" id="bulan"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Rawat</label>
                <select id="jenis_rawat"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                  <option value="">Pilih Jenis Rawat</option>
                  <option value="ralan">Rawat Jalan</option>
                  <option value="ranap">Rawat Inap</option>
                </select>
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

        <div id="resultSection" class="bg-white rounded-2xl border border-green-700 p-6 hidden">
          <div class="mb-4">
            <h3 class="text-lg font-semibold text-green-800">
              <i class="fas fa-user-md mr-2"></i>Informasi Petugas/Dokter
            </h3>
            <div id="petugasInfo" class="mt-2 p-4 bg-green-50 rounded-lg"></div>
          </div>

          <div class="overflow-x-auto">
            <table id="tabelTindakan" class="display w-full">
              <thead class="bg-green-800 text-white">
                <tr>
                  <th class="px-2 text-left">No</th>
                  <th class="px-2 text-left">No. Rawat</th>
                  <th class="px-2 text-left">Tgl. Registrasi</th>
                  <th class="px-2 text-left">No. SEP</th>
                  <th class="px-2 text-left">Pasien</th>
                  <th class="px-2 text-left">Tindakan</th>
                  <th class="px-2 text-right">Jasa</th>
                  <th class="px-2 text-right">Total Jasa</th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot class="bg-green-800 font-bold text-white">
                <tr>
                  <th colspan="7" class="text-right px-2">TOTAL KESELURUHAN:</th>
                  <th class="text-right px-2" id="grandTotal">Rp 0</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script>
  let table;

  function toggleFilter() {
    const content = document.getElementById('filterContent');
    const icon = document.getElementById('filterIcon');

    if (content.classList.contains('hidden')) {
      content.classList.remove('hidden');
      icon.classList.remove('rotate-180');
    } else {
      content.classList.add('hidden');
      icon.classList.add('rotate-180');
    }
  }

  function resetFilter() {
    document.getElementById('nama_petugas').value = '';

    if (table) {
      table.destroy();
      $('#tabelTindakan tbody').empty();
    }

    document.getElementById('resultSection').classList.add('hidden');
  }

  function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0
    }).format(angka);
  }

  function loadData() {
    const nama = $('#nama_petugas').val();
    const bulan = $('#bulan').val();
    const jenis = $('#jenis_rawat').val();

    if (!nama || !bulan || !jenis) {
      alert('Mohon lengkapi semua field pencarian!');
      return;
    }

    if (table) {
      table.destroy();
    }

    document.getElementById('resultSection').classList.remove('hidden');

    table = $('#tabelTindakan').DataTable({
      processing: true,
      serverSide: true,
      scrollY: "500px",
      scrollX: true,
      ajax: {
        url: '../api/jasa_petugas.php',
        type: 'POST',
        data: {
          nama_petugas: nama,
          bulan: bulan,
          jenis_rawat: jenis
        },
        dataSrc: function(json) {
          // Update info petugas
          if (json.petugas_info) {
            $('#petugasInfo').html(`
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div>
                    <p class="text-sm text-gray-600">Kode:</p>
                    <p class="font-semibold">${json.petugas_info.kode}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-600">Nama:</p>
                    <p class="font-semibold">${json.petugas_info.nama}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-600">Jenis:</p>
                    <p class="font-semibold">${json.petugas_info.jenis}</p>
                  </div>
                  <div>
                    <p class="text-sm text-gray-600">Total Tindakan:</p>
                    <p class="font-semibold">${json.recordsFiltered} tindakan</p>
                  </div>
                  <div class="md:col-span-4 pt-3 border-t border-green-300">
                    <p class="text-sm text-gray-600">Total Jasa Keseluruhan:</p>
                    <p class="font-bold text-xl text-green-700">${formatRupiah(json.grand_total)}</p>
                  </div>
                </div>
              `);
          }

          // Update grand total
          if (json.grand_total) {
            $('#grandTotal').text(formatRupiah(json.grand_total));
          }

          return json.data;
        }
      },
      columns: [{
          data: null,
          orderable: false,
          render: function(data, type, row, meta) {
            return meta.row + meta.settings._iDisplayStart + 1;
          }
        },
        {
          data: 'no_rawat'
        },
        {
          data: 'tgl_registrasi'
        },
        {
          data: 'no_sep'
        },
        {
          data: 'nm_pasien'
        },
        {
          data: 'daftar_tindakan',
          orderable: false
        },
        {
          data: 'daftar_jasa',
          orderable: false,
          className: 'text-right'
        },
        {
          data: 'total_jasa',
          className: 'text-right',
          render: function(data) {
            return formatRupiah(data);
          }
        }
      ],
      order: [
        [2, 'desc']
      ],
      pageLength: 25,
      dom: 'Bfrtip',
      buttons: [{
          extend: 'excel',
          text: '<i class="fas fa-file-excel mr-2"></i>Export Excel',
          className: 'buttons-excel',
          title: `Tindakan ${nama} - ${bulan}`
        },
        {
          extend: 'pdf',
          text: '<i class="fas fa-file-pdf mr-2"></i>Export PDF',
          className: 'buttons-pdf',
          title: `Tindakan ${nama} - ${bulan}`,
          orientation: 'landscape'
        }
      ],
      language: {
        processing: "Memproses...",
        lengthMenu: "Tampilkan _MENU_ data",
        zeroRecords: "Data tidak ditemukan",
        info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
        infoEmpty: "Menampilkan 0 sampai 0 dari 0 data",
        infoFiltered: "(difilter dari _MAX_ total data)",
        search: "Cari:",
        paginate: {
          first: "Pertama",
          last: "Terakhir",
          next: "Selanjutnya",
          previous: "Sebelumnya"
        }
      }
    });
  }

  $(document).ready(function() {
    // Set bulan default ke bulan ini
    const today = new Date();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const year = today.getFullYear();
    $('#bulan').val(`${year}-${month}`);

    // Buka filter secara default
    toggleFilter();
  });
  </script>
</body>

</html>