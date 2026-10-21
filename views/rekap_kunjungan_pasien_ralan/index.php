<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$poli_query = "SELECT kd_poli, nm_poli FROM poliklinik WHERE status = '1' ORDER BY nm_poli";
$poli_result = mysqli_query($koneksi, $poli_query);
$polis = [];
while ($row = mysqli_fetch_assoc($poli_result)) {
    $polis[] = $row;
}
?>
<?php
$pageTitle = 'Rekap Kunjungan Pasien Ralan - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
<style>
.dt-button.buttons-excel.buttons-html5,
.dt-button.buttons-pdf.buttons-html5,
.dt-button.buttons-colvis { background-color: #16a34a !important; color: white !important; border: none !important; padding: 12px 20px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 600 !important; cursor: pointer; transition: 0.25s ease-in-out; display: inline-flex; align-items: center; gap: 6px; }
.dt-button.buttons-pdf.buttons-html5 { background-color: #dc2626 !important; }
.dt-button.buttons-colvis { background-color: #2563eb !important; }
.dt-button.buttons-colvis:hover { background-color: #1d4ed8 !important; }
div.dt-button-collection { position: absolute !important; background-color: white !important; border: 1px solid #d1d5db !important; border-radius: 0.5rem !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important; padding: 0.5rem !important; z-index: 9999 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 180px !important; }
div.dt-button-collection button.dt-button { background: none !important; color: #374151 !important; border: none !important; padding: 8px 12px !important; text-align: left !important; font-size: 13px !important; font-weight: 500 !important; border-radius: 6px !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: space-between !important; transition: background-color 0.2s !important; }
div.dt-button-collection button.dt-button:hover { background-color: #f3f4f6 !important; }
div.dt-button-collection button.dt-button.active { background-color: #dbeafe !important; color: #1e40af !important; font-weight: 600 !important; }

#tabelRekap tbody td { padding: 4px 6px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: middle !important; font-size: 14px !important; }
#tabelRekap tbody tr.grand-total { background-color: #fef3c7 !important; font-weight: 700; }
#tabelRekap thead th { background-color: #166534 !important; color: white !important; border: 1px solid #065f46 !important; padding: 8px 4px !important; font-weight: bold !important; font-size: 12px !important; white-space: nowrap; }
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate { font-size: 14px; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
      <select id="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <?php
        $currentYear = date('Y');
        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
          echo "<option value=\"$y\">$y</option>";
        }
        ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
      <select id="bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <option value="">Semua Bulan</option>
        <?php
        $bulan_nama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        for ($b = 1; $b <= 12; $b++) {
          echo "<option value=\"$b\">{$bulan_nama[$b]}</option>";
        }
        ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Poliklinik</label>
      <select id="kd_poli" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <option value="">Semua Poliklinik</option>
        <?php foreach ($polis as $pl): ?>
          <option value="<?= htmlspecialchars($pl['kd_poli']) ?>"><?= htmlspecialchars($pl['nm_poli']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="mt-4 flex gap-2">
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition">
      <i class="fas fa-search mr-2"></i>Tampilkan Data
    </button>
    <button onclick="resetFilter()" class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition">
      <i class="fas fa-redo mr-2"></i>Reset
    </button>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
    <h3 id="periodeInfo" class="text-lg font-bold text-green-800">Periode: -</h3>
    <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-lg text-sm">Hanya pasien dengan kunjungan &gt; 2x per bulan</span>
    <div class="flex gap-3 text-sm">
      <span class="bg-green-100 text-green-800 px-3 py-1 rounded-lg">Total Kunjungan: <b id="infoKunjungan">0</b></span>
      <span class="bg-red-100 text-red-800 px-3 py-1 rounded-lg">Batal: <b id="infoBatal">0</b></span>
      <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-lg">Kunjungan/7 Hari: <b id="infoPerminggu">0</b></span>
    </div>
  </div>

  <div class="overflow-x-auto">
    <table id="tabelRekap" class="display w-full">
      <thead>
        <tr>
          <th>Bulan</th>
          <th>No. RM</th>
          <th>Nama Pasien</th>
          <th>Poliklinik</th>
          <th>Dokter</th>
          <th class="text-right">Jumlah Kunjungan</th>
          <th class="text-right">Data Lebih</th>
          <th class="text-right">Batal</th>
          <th class="text-right">Kunjungan/7 Hari</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
  var table;
  var bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  function angka(data, type) {
    if (type === 'display' || type === 'filter') {
      return data != null ? Number(data).toLocaleString('id-ID') : '0';
    }
    return data;
  }

  $(document).ready(function () {
    var now = new Date();
    $('#tahun').val(now.getFullYear());

    table = $('#tabelRekap').DataTable({
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{
        text: '<i class="fas fa-file-excel mr-2"></i>Export Excel',
        className: 'btn-export-excel',
        action: function () {
          var tahun = $('#tahun').val();
          var bulan = $('#bulan').val();
          var kd_poli = $('#kd_poli').val();
          var url = window.BASE_URL + '/api/export_rekap_kunjungan_pasien_ralan.php?tahun=' + tahun + '&bulan=' + bulan + '&kd_poli=' + encodeURIComponent(kd_poli);
          window.location.href = url;
        }
      }, {
        extend: 'pdfHtml5',
        text: '<i class="fas fa-file-pdf mr-2"></i>Export PDF',
        orientation: 'landscape',
        pageSize: 'A4',
        title: 'Rekap Kunjungan Pasien Ralan',
        exportOptions: { columns: ':visible' },
        customize: function (doc) {
          doc.defaultStyle.fontSize = 7;
          doc.styles.tableHeader.fontSize = 8;
          doc.styles.tableHeader.fillColor = '#166534';
        }
      }, {
        extend: 'colvis',
        text: '<i class="fas fa-columns mr-2"></i>Pilih Kolom'
      }],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 25,
      scrollX: true,
      autoWidth: false,
      order: [[3, 'asc'], [0, 'asc'], [5, 'desc']],
      columns: [
        { data: 'bulan', title: 'Bulan', className: 'text-left font-medium', render: function (data, type) { return bulanNama[Number(data)] || data; } },
        { data: 'no_rkm_medis', title: 'No. RM', className: 'text-left' },
        { data: 'nm_pasien', title: 'Nama Pasien', className: 'text-left' },
        { data: 'nm_poli', title: 'Poliklinik', className: 'text-left' },
        { data: 'nm_dokter', title: 'Dokter', className: 'text-left' },
        { data: 'jumlah_kunjungan', title: 'Jumlah Kunjungan', className: 'text-right', render: angka },
        { data: 'data_lebih', title: 'Data Lebih', className: 'text-right', render: angka },
        { data: 'batal_kunjungan', title: 'Batal', className: 'text-right', render: angka },
        { data: 'kunjungan_perminggu', title: 'Kunjungan/7 Hari', className: 'text-right', render: angka }
      ]
    });

    loadData();
  });

  function loadData() {
    var tahun = $('#tahun').val();
    var bulan = $('#bulan').val();
    var kd_poli = $('#kd_poli').val();

    $.ajax({
      url: window.BASE_URL + '/api/get_rekap_kunjungan_pasien_ralan.php',
      type: 'POST',
      data: { tahun: tahun, bulan: bulan, kd_poli: kd_poli },
      success: function (response) {
        var res = JSON.parse(response);

        if (res.success) {
          $('#periodeInfo').text('Periode: ' + res.periode);
          $('#infoKunjungan').text(Number(res.total_kunjungan).toLocaleString('id-ID'));
          $('#infoBatal').text(Number(res.total_batal).toLocaleString('id-ID'));
          $('#infoPerminggu').text(Number(res.total_perminggu).toLocaleString('id-ID'));

          table.clear();
          table.rows.add(res.data);
          table.draw();
        } else {
          alert('Gagal memuat data');
        }
      },
      error: function (xhr) {
        alert('Error loading data: ' + xhr.statusText);
        console.error(xhr.responseText);
      }
    });
  }

  function resetFilter() {
    var now = new Date();
    $('#tahun').val(now.getFullYear());
    $('#bulan').val('');
    $('#kd_poli').val('');
    loadData();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>
