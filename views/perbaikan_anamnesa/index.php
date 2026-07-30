<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();
?>
<?php
$pageTitle = 'Perbaikan Anamnesa Sama - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
table.dataTable td { color: #1f2937 !important; }
table.dataTable thead th { color: #ffffff !important; background: #166534 !important; padding: 8px 6px !important; font-size: 12px !important; border: 1px solid #065f46 !important; }
.dataTable tbody td { padding: 4px 6px !important; border: 0.5px solid #d1d5db; vertical-align: middle !important; font-size: 13px !important; }
table.dataTable { width: 100% !important; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.badge-primer { background: #dbeafe; color: #1e40af; }
.badge-sekunder { background: #fef3c7; color: #92400e; }
.dup-high { background: #fee2e2; color: #991b1b; font-weight: 700; }
.dup-medium { background: #fef3c7; color: #92400e; font-weight: 700; }
.dup-low { background: #dbeafe; color: #1e40af; font-weight: 700; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>

<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter Pencarian
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Awal</label>
      <input type="date" id="tgl_awal" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Akhir</label>
      <input type="date" id="tgl_akhir" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Cari Anamnesa / No Rawat</label>
      <input type="text" id="tcari" placeholder="Kata kunci..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
    </div>
    <div class="flex items-end gap-2">
      <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-xl transition text-sm">
        <i class="fas fa-search mr-1"></i>Cari
      </button>
      <button onclick="resetFilter()" class="border border-gray-600 text-gray-600 px-4 py-2 rounded-xl hover:bg-gray-200 transition text-sm">
        <i class="fas fa-redo mr-1"></i>Reset
      </button>
      <button onclick="exportExcel()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl transition text-sm">
        <i class="fas fa-file-excel mr-1"></i>Excel
      </button>
    </div>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <div class="flex justify-between items-center mb-3">
    <h3 class="text-lg font-bold text-green-800">
      <i class="fas fa-copy mr-2"></i>Data Anamnesa Sama
    </h3>
    <span id="infoCount" class="text-sm text-gray-500"></span>
  </div>

  <div class="overflow-x-auto">
    <table id="tableAnamnesa" class="display w-full">
      <thead>
        <tr>
          <th>No</th>
          <th>Anamnesa / Keluhan Utama</th>
          <th>Jumlah</th>
          <th>Sumber</th>
          <th>No. Rawat</th>
          <th>Detail</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div id="modalDetail" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center" onclick="if(event.target===this)closeDetail()">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
    <div class="bg-gradient-to-r from-blue-700 to-blue-800 px-5 py-4 flex justify-between items-center rounded-t-2xl">
      <h3 class="text-white font-bold text-lg"><i class="fas fa-list mr-2"></i>Detail Anamnesa Sama</h3>
      <button onclick="closeDetail()" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
    </div>
    <div class="p-5">
      <div id="detailContent"></div>
    </div>
  </div>
</div>

<script>
  let table;
  let allData = [];

  function exportExcel() {
    let params = 'tgl_awal=' + $('#tgl_awal').val() +
                 '&tgl_akhir=' + $('#tgl_akhir').val() +
                 '&tcari=' + $('#tcari').val();
    window.location.href = window.BASE_URL + '/api/export_perbaikan_anamnesa.php?' + params;
  }

  $(document).ready(function () {
    table = $('#tableAnamnesa').DataTable({
      dom: '<"flex justify-between items-center mb-2"l>rtip',
      lengthMenu: [[25, 50, 100, 500], [25, 50, 100, 500]],
      pageLength: 50,
      scrollX: true,
      autoWidth: false,
      ordering: true,
      searching: true,
      info: true,
      columns: [
        { data: 'no', className: 'text-center font-bold' },
        {
          data: 'anamesa_text',
          className: 'text-left',
          render: function(d) { return d || '-'; }
        },
        {
          data: 'jumlah',
          className: 'text-center',
          render: function(d) {
            var cls = 'dup-low';
            if (d >= 5) cls = 'dup-high';
            else if (d >= 3) cls = 'dup-medium';
            return '<span class="badge ' + cls + '">' + d + ' data</span>';
          }
        },
        {
          data: 'sumber_list',
          className: 'text-center',
          render: function(d) {
            var sources = d.split(', ');
            var counts = {};
            sources.forEach(function(s) { counts[s] = (counts[s] || 0) + 1; });
            var html = '';
            if (counts['PRIMER']) html += '<span class="badge badge-primer mr-1">Primer: ' + counts['PRIMER'] + '</span>';
            if (counts['SEKUNDER']) html += '<span class="badge badge-sekunder">Sekunder: ' + counts['SEKUNDER'] + '</span>';
            return html;
          }
        },
        {
          data: 'no_rawat_list',
          className: 'text-left font-mono text-xs',
          render: function(d) {
            if (!d) return '-';
            var items = d.split(', ');
            if (items.length <= 3) return d;
            return items.slice(0, 3).join(', ') + ', ... (' + items.length + ' total)';
          }
        },
        {
          data: null,
          className: 'text-center',
          sortable: false,
          render: function(d) {
            return '<button onclick="showDetail(' + d.no + ')" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-xs transition"><i class="fas fa-eye mr-1"></i>Detail</button>';
          }
        }
      ]
    });

    loadData();
  });

  function loadData() {
    var tahun = new Date().getFullYear();
    if (!$('#tgl_awal').val()) $('#tgl_awal').val(tahun + '-01-01');
    if (!$('#tgl_akhir').val()) $('#tgl_akhir').val(tahun + '-12-31');

    $.ajax({
      url: window.BASE_URL + '/api/get_data_perbaikan_anamnesa.php',
      type: 'POST',
      data: {
        tgl_awal: $('#tgl_awal').val(),
        tgl_akhir: $('#tgl_akhir').val(),
        tcari: $('#tcari').val()
      },
      success: function (response) {
        var res = JSON.parse(response);
        if (res.success) {
          allData = res.data;
          var data = res.data.map(function (item, idx) {
            item.no = idx + 1;
            return item;
          });
          table.clear();
          table.rows.add(data);
          table.draw();
          $('#infoCount').text('Total: ' + data.length + ' kelompok anamnesa sama');
        } else {
          alert('Gagal: ' + (res.error || 'Unknown error'));
        }
      },
      error: function (xhr) {
        alert('Error: ' + xhr.statusText);
      }
    });
  }

  function showDetail(no) {
    var item = allData[no - 1];
    if (!item) return;

    var html = '<div class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">';
    html += '<p class="text-sm text-gray-500 mb-1">Anamnesa / Keluhan Utama:</p>';
    html += '<p class="text-base font-semibold text-gray-800">' + (item.anamesa_text || '-') + '</p>';
    html += '</div>';

    html += '<table class="w-full text-sm border-collapse">';
    html += '<thead><tr class="bg-gray-100">';
    html += '<th class="px-3 py-2 border text-left">No</th>';
    html += '<th class="px-3 py-2 border text-left">No. Rawat</th>';
    html += '<th class="px-3 py-2 border text-left">No. RM</th>';
    html += '<th class="px-3 py-2 border text-left">Pasien</th>';
    html += '<th class="px-3 py-2 border text-left">Tgl</th>';
    html += '<th class="px-3 py-2 border text-left">Sumber</th>';
    html += '</tr></thead><tbody>';

    item.items.forEach(function(r, i) {
      var sumberBadge = r.sumber === 'PRIMER'
        ? '<span class="badge badge-primer">Primer</span>'
        : '<span class="badge badge-sekunder">Sekunder</span>';
      html += '<tr class="' + (i % 2 === 0 ? 'bg-white' : 'bg-gray-50') + '">';
      html += '<td class="px-3 py-2 border">' + (i + 1) + '</td>';
      html += '<td class="px-3 py-2 border font-mono">' + r.no_rawat + '</td>';
      html += '<td class="px-3 py-2 border font-mono">' + (r.no_rkm_medis || '-') + '</td>';
      html += '<td class="px-3 py-2 border">' + (r.nm_pasien || '-') + '</td>';
      html += '<td class="px-3 py-2 border">' + (r.tgl ? r.tgl.substring(0, 10) : '-') + '</td>';
      html += '<td class="px-3 py-2 border text-center">' + sumberBadge + '</td>';
      html += '</tr>';
    });

    html += '</tbody></table>';
    $('#detailContent').html(html);
    $('#modalDetail').removeClass('hidden');
  }

  function closeDetail() {
    $('#modalDetail').addClass('hidden');
  }

  function resetFilter() {
    $('#tgl_awal').val('');
    $('#tgl_akhir').val('');
    $('#tcari').val('');
    loadData();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>
