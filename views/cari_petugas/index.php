<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();
$pageTitle = 'Cari Paramedis/Dokter - RSUD MERAUKE';
$extraHead = '
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <style>
  /* Custom scrollbar agar lebih tipis dan modern */
  ::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }

  ::-webkit-scrollbar-track {
    background: #f1f1f1;
  }

  ::-webkit-scrollbar-thumb {
    background: #16a34a;
    border-radius: 4px;
  }

  ::-webkit-scrollbar-thumb:hover {
    background: #15803d;
  }

  .sticky-header th {
    position: sticky;
    top: 0;
    z-index: 10;
    background-color: #166534;
    /* green-800 */
  }

  table.dataTable {
    width: auto !important;
  }
  </style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
      <div class="bg-white rounded-2xl border border-green-200 p-5 mb-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Nama Petugas</label>
            <input type="text" id="nama_petugas"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-green-500 outline-none"
              placeholder="Cari nama...">
          </div>
          <div>
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Bulan</label>
            <input type="month" id="bulan" class="w-full border rounded-lg px-3 py-2 outline-none">
          </div>
          <div>
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Jenis Rawat</label>
            <select id="jenis_rawat" class="w-full border rounded-lg px-3 py-2 outline-none">
              <option value="ralan">Rawat Jalan</option>
              <option value="ranap">Rawat Inap</option>
              <option value="operasi">Operasi</option>
            </select>
          </div>
        </div>
        <div class="flex gap-2">
          <button onclick="loadDataManual()"
            class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg font-semibold flex items-center gap-2 transition">
            <i class="fas fa-search"></i> Cari Data
          </button>
          <button onclick="exportToExcel()"
            class="bg-emerald-100 text-emerald-700 px-5 py-2 rounded-lg font-semibold hover:bg-emerald-200 transition">
            <i class="fas fa-file-excel"></i> Excel
          </button>
        </div>
      </div>

      <div id="petugasInfo" class="hidden bg-white border-l-4 border-green-600 p-4 mb-4 rounded-r-xl shadow-sm"></div>

      <div id="resultSection" class="hidden">
        <div class="bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden">
          <div class="overflow-x-auto">
            <div class="max-h-[500px] overflow-y-auto">
              <table id="tabelData" class="w-full text-sm text-left border-collapse">
                <thead class="sticky-header text-white uppercase text-xs">
                  <tr>
                    <th class="p-4 border-b">No</th>
                    <th class="p-4 border-b">No. Rawat</th>
                    <th class="p-4 border-b">Tgl. Registrasi</th>
                    <th class="p-4 border-b">No. SEP</th>
                    <th class="p-4 border-b">Pasien</th>
                    <th class="p-4 border-b min-w-[300px]">Tindakan/Peran</th>
                    <th class="p-4 border-b text-right">Jasa</th>
                    <th class="p-4 border-b text-right">Total</th>
                  </tr>
                </thead>
                <tbody id="tbodyManual" class="divide-y divide-gray-100">
                </tbody>
              </table>
            </div>
          </div>
          <div class="bg-green-800 p-4 text-white flex justify-between items-center font-bold">
            <span>TOTAL KESELURUHAN:</span>
            <span id="grandTotal" class="text-lg">Rp 0</span>
          </div>
        </div>
      </div>

      <div id="loading" class="hidden py-10 text-center">
        <i class="fas fa-circle-notch fa-spin text-4xl text-green-600"></i>
        <p class="mt-2 text-gray-500 italic">Mengambil data dari server...</p>
      </div>

  <script>
  function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0
    }).format(angka);
  }

  function loadDataManual() {
    const nama = $('#nama_petugas').val();
    const bulan = $('#bulan').val();
    const jenis = $('#jenis_rawat').val();

    if (!nama || !bulan) {
      alert('Nama dan Bulan wajib diisi!');
      return;
    }

    $('#loading').removeClass('hidden');
    $('#resultSection, #petugasInfo').addClass('hidden');
    $('#tbodyManual').empty();

    $.ajax({
      url: '../api/jasa_petugas.php',
      type: 'POST',
      data: {
        nama_petugas: nama,
        bulan: bulan,
        jenis_rawat: jenis,
        // Karena kita tidak pakai DataTables, kita bypass parameter start/length 
        // atau menyesuaikan API agar mengirim semua data
        draw: 1,
        start: 0,
        length: 9999
      },
      dataType: 'json',
      success: function(response) {
        $('#loading').addClass('hidden');
        $('#resultSection, #petugasInfo').removeClass('hidden');

        // Render Info Petugas
        if (response.petugas_info) {
          const pi = response.petugas_info;
          $('#petugasInfo').html(`
              <div class="flex flex-wrap gap-6 items-center">
                <div><span class="text-xs text-gray-400 block">PETUGAS</span><b class="text-green-800">${pi.nama} (${pi.kode})</b></div>
                <div><span class="text-xs text-gray-400 block">DITEMUKAN</span><b>${response.recordsFiltered} Data</b></div>
                <div class="ml-auto"><span class="text-xs text-gray-400 block text-right">GRAND TOTAL JASA</span><b class="text-2xl text-green-700">${formatRupiah(response.grand_total)}</b></div>
              </div>
            `);
        }

        // Render Body Tabel
        if (response.data && response.data.length > 0) {
          let rows = '';
          response.data.forEach((item, index) => {
            rows += `
                <tr class="hover:bg-green-50 transition border-b border-gray-50">
                  <td class="p-4 text-center">${index + 1}</td>
                  <td class="p-4 align-top font-mono text-xs text-blue-600">${item.no_rawat}</td>
                  <td class="p-4 align-top whitespace-nowrap">${item.tgl_registrasi}</td>
                  <td class="p-4 align-top text-gray-500">${item.no_sep || '-'}</td>
                  <td class="p-4 align-top font-semibold">${item.nm_pasien}</td>
                  <td class="p-4  italic text-gray-600">${item.daftar_tindakan}</td>
                  <td class="p-4 align-top text-right text-gray-500">${item.daftar_jasa}</td>
                  <td class="p-4 align-top text-right font-bold text-green-700">${formatRupiah(item.total_jasa)}</td>
                </tr>
              `;
          });
          $('#tbodyManual').html(rows);
          $('#grandTotal').text(formatRupiah(response.grand_total));
        } else {
          $('#tbodyManual').html(
            '<tr><td colspan="8" class="p-10 text-center text-gray-400">Data tidak ditemukan untuk periode ini.</td></tr>'
          );
        }
      },
      error: function() {
        alert('Terjadi kesalahan saat mengambil data.');
        $('#loading').addClass('hidden');
      }
    });
  }

  function exportToExcel() {
    const table = document.getElementById("tabelData");
    const wb = XLSX.utils.table_to_book(table, {
      sheet: "Data Tindakan"
    });
    XLSX.writeFile(wb, `Jasa_${$('#nama_petugas').val()}_${$('#bulan').val()}.xlsx`);
  }

  $(document).ready(function() {
    const today = new Date();
    $('#bulan').val(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`);
  });
  </script>
<?php require_once '../layouts/footer.php'; ?>