<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$pageTitle = 'Kepatuhan BPJS Rawat Jalan - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  table.dataTable td, table.dataTable th { color: #1f2937 !important; }
  #tabelKepatuhan th, #tabelKepatuhan td { white-space: nowrap; }
  #tabelKepatuhan tbody td { padding: 2px 4px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: top !important; }
  table.dataTable { width: auto !important; }
  .dt-buttons { margin-bottom: 10px; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 p-6 mb-6">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i
      class="fas fa-file-invoice mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter Pencarian
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
      <select id="filter_bulan"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
      <select id="filter_tahun"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
          <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>
  <div class="mt-4 flex gap-2">
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition">
      <i class="fas fa-search mr-2"></i>Cari Data
    </button>
    <button onclick="resetFilter()"
      class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition">
      <i class="fas fa-redo mr-2"></i>Reset
    </button>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-6">
  <div class="overflow-x-auto">
    <table id="tabelKepatuhan" class="display w-full">
      <thead class="bg-green-800">
        <tr class="text-white">
          <th class="px-2 text-left text-white">No</th>
          <th class="px-2 text-left">Nama Poliklinik</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Total Pasien</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Pasien BPJS</th>
          <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Surat Kontrol</th>
          <th class="px-2 text-right" style="background:#1d4ed8;color:#fff">SEP Terbit</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Terlayani</th>
          <th class="px-2 text-right" style="background:#b45309;color:#fff">Belum Dilayani</th>
          <th class="px-2 text-right" style="background:#dc2626;color:#fff">Batal Periksa</th>
          <th class="px-2 text-right" style="background:#1d4ed8;color:#fff">% SEP</th>
          <th class="px-2 text-right" style="background:#7c3aed;color:#fff">% Surat Kontrol</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot class="bg-green-800 font-bold text-white">
        <tr>
          <th colspan="2" class="text-right px-2">TOTAL AKHIR :</th>
          <th class="text-right px-2"></th>
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

<script>
  let table;
  const tableConfig = {
    processing: true,
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    dom: '<"flex justify-between items-center mb-4"lB>rtip',
    buttons: [{
      extend: 'excel',
      text: 'Export Excel',
      filename: 'kepatuhan_bpjs'
    }],
    lengthMenu: [
      [10, 25, 50, 100, 300, 1000, 5000],
      [10, 25, 50, 100, 300, 1000, 5000]
    ],
    pageLength: 25,
    autoWidth: false,
    columns: [
      { data: null, className: 'text-center', render: function (data, type, row, meta) { return meta.row + 1; } },
      { data: 'nm_poli' },
      { data: 'total_pasien', className: 'num', render: function (v) { return (v || 0).toLocaleString('id-ID'); } },
      { data: 'pasien_bpjs', className: 'num', render: function (v) { return (v || 0).toLocaleString('id-ID'); } },
      { data: 'surat_kontrol', className: 'num', render: function (v) { return (v || 0).toLocaleString('id-ID'); } },
      { data: 'sep_terbit', className: 'num', render: function (v) { return (v || 0).toLocaleString('id-ID'); } },
      { data: 'terlayani', className: 'num', render: function (v) { return (v || 0).toLocaleString('id-ID'); } },
      { data: 'belum_dilayani', className: 'num', render: function (v) { return (v || 0).toLocaleString('id-ID'); } },
      { data: 'batal_periksa', className: 'num', render: function (v) { return (v || 0).toLocaleString('id-ID'); } },
      { data: 'pct_sep', className: 'num', render: function (v) { return (v || 0).toFixed(2) + '%'; } },
      { data: 'pct_surat_kontrol', className: 'num', render: function (v) { return (v || 0).toFixed(2) + '%'; } },
    ],
    order: [
      [2, 'desc']
    ],
    language: {
      processing: "Memuat data...",
      lengthMenu: "Tampilkan _MENU_ data per halaman",
      zeroRecords: "Data tidak ditemukan",
      info: "Menampilkan halaman _PAGE_ dari _PAGES_",
      infoEmpty: "Tidak ada data tersedia",
      infoFiltered: "(difilter dari _MAX_ total data)",
      search: "Cari:",
      paginate: {
        first: "Pertama",
        last: "Terakhir",
        next: "Selanjutnya",
        previous: "Sebelumnya"
      }
    },
    footerCallback: function (row, data, start, end, display) {
      const api = this.api();
      const sumData = (prop) => data.map(r => parseInt(r[prop]) || 0).reduce((a, b) => a + b, 0);
      const fmt = (x) => Math.round(x).toLocaleString('id-ID');
      const pct = (num, den) => den > 0 ? (num / den * 100).toFixed(2) + '%' : '0%';

      const sbpjs = sumData('pasien_bpjs');

      $(api.column(2).footer()).html(fmt(sumData('total_pasien')));
      $(api.column(3).footer()).html(fmt(sbpjs));
      $(api.column(4).footer()).html(fmt(sumData('surat_kontrol')));
      $(api.column(5).footer()).html(fmt(sumData('sep_terbit')));
      $(api.column(6).footer()).html(fmt(sumData('terlayani')));
      $(api.column(7).footer()).html(fmt(sumData('belum_dilayani')));
      $(api.column(8).footer()).html(fmt(sumData('batal_periksa')));
      $(api.column(9).footer()).html(pct(sumData('sep_terbit'), sbpjs));
      $(api.column(10).footer()).html(pct(sumData('surat_kontrol'), sbpjs));
    }
  };

  $(document).ready(function () {
    table = $('#tabelKepatuhan').DataTable(tableConfig);
  });

  function loadData() {
    if (table) table.destroy();
    table = $('#tabelKepatuhan').DataTable({
      ...tableConfig,
      serverSide: true,
      ajax: {
        url: window.BASE_URL + '/api/get_data_kepatuhan_bpjs.php',
        type: 'POST',
        dataSrc: function (json) {
          return json.data || json;
        },
        data: function (d) {
          d.bulan = $('#filter_bulan').val();
          d.tahun = $('#filter_tahun').val();
          d.search_value = d.search.value;
        }
      }
    });
  }

  function resetFilter() {
    const now = new Date();
    $('#filter_bulan').val(String(now.getMonth() + 1));
    $('#filter_tahun').val(String(now.getFullYear()));
    loadData();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>