<?php
$pageTitle = 'Rekap Pasien - RSUD MERAUKE';

$tabs = [
  ['jenis' => 'per_poli', 'label' => 'Per Poliklinik', 'icon' => 'fa-building'],
  ['jenis' => 'per_ruangan', 'label' => 'Per Ruangan', 'icon' => 'fa-bed'],
  ['jenis' => 'per_dokter', 'label' => 'Per Dokter', 'icon' => 'fa-user-doctor'],
];
?>
<?php
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<style>
.tab-btn { padding: 10px 20px; font-weight: 600; font-size: 14px; border-radius: 10px 10px 0 0; transition: all 0.2s; cursor: pointer; border: 1px solid transparent; border-bottom: none; }
.tab-btn.active { background: white; color: #166534; border-color: #166534; }
.tab-btn:not(.active) { background: #e5e7eb; color: #6b7280; }
.tab-btn:not(.active):hover { background: #d1d5db; }
.dt-button.buttons-excel.buttons-html5 { background-color: #16a34a !important; color: white !important; border: none !important; padding: 12px 20px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 600 !important; cursor: pointer; transition: 0.25s ease-in-out; display: inline-flex; align-items: center; gap: 6px; }
#tabelRekap tbody td { padding: 6px 8px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: middle !important; font-size: 14px !important; }
#tabelRekap tbody tr.bulan-header { background-color: #f0fdf4 !important; font-weight: 700; }
#tabelRekap thead th { background-color: #166534 !important; color: white !important; border: 1px solid #065f46 !important; padding: 8px 4px !important; font-weight: bold !important; font-size: 12px !important; white-space: nowrap; }
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter,
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_paginate { font-size: 14px; }
.dataTables_wrapper,
table.dataTable,
.dataTables_scrollHeadInner,
.dataTables_scrollHeadInner table.dataTable,
.dataTables_scrollBody table.dataTable { width: 100% !important; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>



<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
      <select id="tahun"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <?php
        $currentYear = date('Y');
        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
          echo "<option value=\"$y\">$y</option>";
        }
        ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bulan <span class="text-gray-400 text-xs">(kosongkan
          untuk semua bulan)</span></label>
      <select id="bulan"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <option value="">Semua Bulan</option>
        <?php
        $bln = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        for ($m = 1; $m <= 12; $m++) {
          echo "<option value=\"$m\">$bln[$m]</option>";
        }
        ?>
      </select>
    </div>
  </div>

  <div class="mt-4 flex gap-2">
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition">
      <i class="fas fa-search mr-2"></i>Tampilkan Data
    </button>
    <button onclick="resetFilter()"
      class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition">
      <i class="fas fa-redo mr-2"></i>Reset
    </button>
  </div>
</div>
<div class="mb-3">
  <div class="flex gap-1 flex-wrap">
    <?php foreach ($tabs as $i => $tab): ?>
      <button class="tab-btn <?= $i === 0 ? 'active' : '' ?>" data-jenis="<?= $tab['jenis'] ?>"
        onclick="switchTab('<?= $tab['jenis'] ?>', this)">
        <i class="fas <?= $tab['icon'] ?> mr-2"></i>
        <?= $tab['label'] ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>
<div class="bg-white rounded-2xl border border-green-700 p-3">
  <div class="flex justify-between items-center mb-4">
    <h3 id="periodeInfo" class="text-lg font-bold text-green-800">Periode: -</h3>
    <button onclick="exportExcel()"
      class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-xl transition text-sm font-semibold">
      <i class="fas fa-file-excel mr-2"></i>Export Excel (Semua Sheet)
    </button>
  </div>

  <div class="overflow-x-auto">
    <table id="tabelRekap" class="display w-full" style="width:100%">
      <thead>
        <tr>
          <th id="thLabel">Poliklinik</th>
          <th class="text-right">Total</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
  var jenis = 'per_poli';
  var table;

  var labelTitles = {
    per_poli: 'Poliklinik',
    per_ruangan: 'Ruangan',
    per_dokter: 'Dokter'
  };

  function renderTable(cols, data, headerRows) {
    if ($.fn.DataTable.isDataTable('#tabelRekap')) {
      $('#tabelRekap').DataTable().clear().destroy();
      $('#tabelRekap').empty();
    }

    var columns = cols.map(function (col) {
      var newCol = Object.assign({}, col);
      if (newCol.data !== 'label') {
        newCol.render = function (data, type) {
          if (type === 'display') {
            return data != null ? Number(data).toLocaleString('id-ID') : '0';
          }
          return data;
        };
      }
      return newCol;
    });

    if (headerRows && headerRows.length > 0) {
      var $thead = $('<thead></thead>');
      headerRows.forEach(function (row) {
        var $tr = $('<tr></tr>');
        row.forEach(function (cell) {
          var $th = $('<th></th>').text(cell.title);
          if (cell.rowspan) $th.attr('rowspan', cell.rowspan);
          if (cell.colspan) $th.attr('colspan', cell.colspan);
          if (cell.className) $th.addClass(cell.className);
          $tr.append($th);
        });
        $thead.append($tr);
      });
      $('#tabelRekap').append($thead);
    }

    table = $('#tabelRekap').DataTable({
      dom: '<"flex justify-between items-center mb-4"l>rtip',
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
      pageLength: 50,
      autoWidth: false,
      paging: false,
      ordering: true,
      searching: true,
      info: false,
      columns: columns,
      data: data
    });
  }

  function switchTab(newJenis, btn) {
    if (newJenis === jenis) return;
    jenis = newJenis;
    document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    loadData();
  }

  function loadData() {
    var tahun = $('#tahun').val();
    var bulan = $('#bulan').val();

    $.ajax({
      url: window.BASE_URL + '/api/rekap_pasien.php',
      type: 'POST',
      data: { jenis: jenis, tahun: tahun, bulan: bulan },
      success: function (response) {
        var res = JSON.parse(response);
        if (res.success) {
          $('#periodeInfo').text('Periode: ' + res.periode);
          renderTable(res.columns, res.data, res.headerRows);
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

  function exportExcel() {
    var tahun = $('#tahun').val();
    var bulan = $('#bulan').val();
    var url = window.BASE_URL + '/api/export_rekap_pasien.php?tahun=' + tahun + '&bulan=' + bulan;
    window.location.href = url;
  }

  function resetFilter() {
    var now = new Date();
    $('#tahun').val(now.getFullYear());
    $('#bulan').val('');
    if (table) { table.destroy(); table = null; }
    loadData();
  }

  $(document).ready(function () {
    var now = new Date();
    $('#tahun').val(now.getFullYear());
    loadData();
  });
</script>
<?php require_once '../layouts/footer.php'; ?>