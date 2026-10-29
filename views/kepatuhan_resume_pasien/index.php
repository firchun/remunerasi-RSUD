<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$pageTitle = 'Kepatuhan Resume Pasien - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<style>
  table.dataTable td { color: #1f2937 !important; } table.dataTable thead th { color: #ffffff !important; }
  #tabelResumeRanap th, #tabelResumeRanap td,
  #tabelResumeRalan th, #tabelResumeRalan td { white-space: nowrap; }
  #tabelResumeRanap tbody td, #tabelResumeRalan tbody td { padding: 4px 6px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: middle !important; }
  table.dataTable { width: auto !important; }
  .dt-buttons { margin-bottom: 10px; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="flex items-center gap-2 mb-3 border-b border-gray-300">
  <button id="tabBtnRanap" onclick="switchTab('ranap')"
    class="px-5 py-3 font-semibold border-b-4 border-green-700 text-green-800 bg-green-50 flex items-center gap-2 transition">
    <i class="fas fa-bed"></i> Rawat Inap
  </button>
  <button id="tabBtnRalan" onclick="switchTab('ralan')"
    class="px-5 py-3 font-semibold border-b-4 border-transparent text-gray-500 hover:text-green-700 flex items-center gap-2 transition">
    <i class="fas fa-user-doctor"></i> Rawat Jalan
  </button>
</div>

<div id="panelRanap">
  <div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
    <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
      <i class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
      Filter Pencarian Rawat Inap
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Bulan (Tgl Masuk)</label>
        <select id="ranap_bulan"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
        <select id="ranap_tahun"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
          <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Status Resume</label>
        <select id="ranap_status"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
          <option value="semua" selected>Semua</option>
          <option value="ada">Ada Resume</option>
          <option value="tidak">Tidak Ada Resume</option>
        </select>
      </div>
    </div>
    <div class="mt-4 flex gap-2">
      <button onclick="reloadTable('ranap')" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-xl transition">
        <i class="fas fa-search mr-2"></i>Cari Data
      </button>
      <button onclick="resetFilter('ranap')"
        class="border border-gray-600 text-gray-600 p-2 rounded-xl hover:bg-gray-200 transition">
        <i class="fas fa-redo mr-2"></i>Reset
      </button>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-green-700 p-3">
    <div class="overflow-x-auto">
      <table id="tabelResumeRanap" class="display w-full">
        <thead class="bg-green-800 text-white">
          <tr>
            <th class="px-2 text-left">No</th>
            <th class="px-2 text-left">No. Rawat</th>
            <th class="px-2 text-left">No. RM</th>
            <th class="px-2 text-left">Nama Pasien</th>
            <th class="px-2 text-left">Bangsal / Kamar</th>
            <th class="px-2 text-left">Nama Dokter (DPJP)</th>
            <th class="px-2 text-center">Resume</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<div id="panelRalan" class="hidden">
  <div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
    <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
      <i class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
      Filter Pencarian Rawat Jalan
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Bulan (Tgl Registrasi)</label>
        <select id="ralan_bulan"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
          <?php for ($m = 1; $m <= 12; $m++): ?>
            <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
        <select id="ralan_tahun"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
          <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
            <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Status Resume</label>
        <select id="ralan_status"
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
          <option value="semua" selected>Semua</option>
          <option value="ada">Ada Resume</option>
          <option value="tidak">Tidak Ada Resume</option>
        </select>
      </div>
    </div>
    <div class="mt-4 flex gap-2">
      <button onclick="reloadTable('ralan')" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-xl transition">
        <i class="fas fa-search mr-2"></i>Cari Data
      </button>
      <button onclick="resetFilter('ralan')"
        class="border border-gray-600 text-gray-600 p-2 rounded-xl hover:bg-gray-200 transition">
        <i class="fas fa-redo mr-2"></i>Reset
      </button>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-green-700 p-3">
    <div class="overflow-x-auto">
      <table id="tabelResumeRalan" class="display w-full">
        <thead class="bg-green-800 text-white">
          <tr>
            <th class="px-2 text-left">No</th>
            <th class="px-2 text-left">No. Rawat</th>
            <th class="px-2 text-left">No. RM</th>
            <th class="px-2 text-left">Nama Pasien</th>
            <th class="px-2 text-left">Poli</th>
            <th class="px-2 text-left">Nama Dokter</th>
            <th class="px-2 text-center">Resume</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
  let tableRanap = null;
  let tableRalan = null;

  const TAB_ACTIVE = ['border-green-700', 'text-green-800', 'bg-green-50'];
  const TAB_INACTIVE = ['border-transparent', 'text-gray-500'];

  function columnsConfig(ruangField, ruangLabel) {
    return [
      { data: null, className: 'text-center', render: function (data, type, row, meta) { return meta.row + 1; } },
      { data: 'no_rawat', className: 'font-semibold' },
      { data: 'no_rkm_medis' },
      { data: 'nm_pasien' },
      { data: ruangField, title: ruangLabel },
      { data: 'nm_dokter', defaultContent: '-' },
      {
        data: 'status_resume',
        className: 'text-center',
        render: function (v) {
          if (v === 'Ada') {
            return '<span class="inline-block px-2 py-0.5 text-xs rounded-lg border bg-green-100 text-green-700 border-green-400 font-semibold">Ada</span>';
          }
          return '<span class="inline-block px-2 py-0.5 text-xs rounded-lg border bg-red-100 text-red-700 border-red-400 font-semibold">Tidak Ada</span>';
        }
      }
    ];
  }

  function tableConfig(url, params, title, exportUrl, ruangField, ruangLabel) {
    return {
      processing: true,
      serverSide: true,
      scrollY: "500px",
      scrollX: true,
      scrollCollapse: true,
      autoWidth: false,
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{
        text: 'Export Excel',
        className: 'buttons-excel buttons-html5',
        action: function () {
          const p = params();
          const qs = new URLSearchParams({
            bulan: p.bulan,
            tahun: p.tahun,
            status_resume: p.status_resume
          });
          window.location.href = exportUrl + '?' + qs.toString();
        }
      }, {
        extend: 'pdfHtml5',
        text: 'Export PDF',
        filename: function () {
          const p = params();
          return title.replace(/\s+/g, '_') + '_' + p.bulan + '_' + p.tahun;
        },
        orientation: 'landscape',
        pageSize: 'A4',
        title: function () {
          const p = params();
          return 'Laporan ' + title + ' - ' + p.bulan_label + ' ' + p.tahun;
        },
        customize: function (doc) {
          doc.defaultStyle.fontSize = 8;
          doc.styles.tableHeader.fontSize = 9;
          doc.styles.tableHeader.fillColor = '#166534';
        }
      }],
      lengthMenu: [
        [10, 25, 50, 100, 300, 1000, 5000],
        [10, 25, 50, 100, 300, 1000, 5000]
      ],
      pageLength: 25,
      order: [[1, 'desc']],
      columns: columnsConfig(ruangField, ruangLabel),
      ajax: {
        url: url,
        type: 'POST',
        data: function (d) {
          Object.assign(d, params());
          d.search_value = d.search.value;
        }
      },
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
      }
    };
  }

  function ranapParams() {
    return {
      bulan: $('#ranap_bulan').val(),
      bulan_label: $('#ranap_bulan option:selected').text(),
      tahun: $('#ranap_tahun').val(),
      status_resume: $('#ranap_status').val()
    };
  }

  function ralanParams() {
    return {
      bulan: $('#ralan_bulan').val(),
      bulan_label: $('#ralan_bulan option:selected').text(),
      tahun: $('#ralan_tahun').val(),
      status_resume: $('#ralan_status').val()
    };
  }

  function initRanap() {
    if (tableRanap) return;
    tableRanap = $('#tabelResumeRanap').DataTable(tableConfig(
      window.BASE_URL + '/api/get_data_kepatuhan_resume_ranap.php',
      ranapParams,
      'Kepatuhan Resume Pasien Rawat Inap',
      window.BASE_URL + '/api/export_kepatuhan_resume_ranap.php',
      'kamar',
      'Bangsal / Kamar'
    ));
  }

  function initRalan() {
    if (tableRalan) return;
    tableRalan = $('#tabelResumeRalan').DataTable(tableConfig(
      window.BASE_URL + '/api/get_data_kepatuhan_resume_ralan.php',
      ralanParams,
      'Kepatuhan Resume Pasien Rawat Jalan',
      window.BASE_URL + '/api/export_kepatuhan_resume_ralan.php',
      'nm_poli',
      'Poli'
    ));
  }

  function switchTab(tab) {
    const isRanap = tab === 'ranap';

    $('#panelRanap').toggleClass('hidden', !isRanap);
    $('#panelRalan').toggleClass('hidden', isRanap);

    $('#tabBtnRanap').toggleClass(TAB_ACTIVE.join(' '), isRanap).toggleClass(TAB_INACTIVE.join(' '), !isRanap);
    $('#tabBtnRalan').toggleClass(TAB_ACTIVE.join(' '), !isRanap).toggleClass(TAB_INACTIVE.join(' '), isRanap);

    if (isRanap) {
      initRanap();
      if (tableRanap) tableRanap.columns.adjust().draw(false);
    } else {
      initRalan();
      if (tableRalan) tableRalan.columns.adjust().draw(false);
    }
  }

  function reloadTable(tab) {
    if (tab === 'ranap') {
      initRanap();
      tableRanap.ajax.reload();
    } else {
      initRalan();
      tableRalan.ajax.reload();
    }
  }

  function resetFilter(tab) {
    const now = new Date();
    if (tab === 'ranap') {
      $('#ranap_bulan').val(String(now.getMonth() + 1));
      $('#ranap_tahun').val(String(now.getFullYear()));
      $('#ranap_status').val('semua');
    } else {
      $('#ralan_bulan').val(String(now.getMonth() + 1));
      $('#ralan_tahun').val(String(now.getFullYear()));
      $('#ralan_status').val('semua');
    }
    reloadTable(tab);
  }

  $(document).ready(function () {
    initRanap();
  });
</script>
<?php require_once '../layouts/footer.php'; ?>
