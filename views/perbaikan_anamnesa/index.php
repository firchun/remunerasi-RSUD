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
          <th>Jumlah Pasien Sama</th>
          <th>Sumber</th>
          <th>No. Rawat</th>
          <th>No. RM</th>
          <th>Pasien</th>
          <th>Dokter</th>
          <th>Tanggal</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
  let table;

  function escapeHtml(value) {
    return String(value ?? '-').replace(/[&<>"']/g, function(char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
      }[char];
    });
  }

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
          data: 'anamnesa',
          className: 'text-left',
          render: function(d) { return escapeHtml(d); }
        },
        {
          data: 'jumlah',
          className: 'text-center',
          render: function(d) {
            return '<span class="badge dup-medium">' + d + ' pasien</span>';
          }
        },
        {
          data: 'sumber',
          className: 'text-center',
          render: function(d) {
            var cls = d === 'PRIMER' ? 'badge-primer' : 'badge-sekunder';
            return '<span class="badge ' + cls + '">' + escapeHtml(d) + '</span>';
          }
        },
        {
          data: 'no_rawat',
          className: 'text-left font-mono text-xs',
          render: function(d) { return escapeHtml(d); }
        },
        {
          data: 'no_rkm_medis',
          className: 'text-left font-mono text-xs',
          render: function(d) { return escapeHtml(d); }
        },
        {
          data: 'nm_pasien',
          className: 'text-left',
          render: function(d) { return escapeHtml(d); }
        },
        {
          data: 'nm_dokter',
          className: 'text-left',
          render: function(d) { return escapeHtml(d); }
        },
        {
          data: 'tgl',
          className: 'text-center',
          render: function(d) { return escapeHtml(d ? d.substring(0, 10) : '-'); }
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
          var data = res.data.map(function (item, idx) {
            item.no = idx + 1;
            return item;
          });
          table.clear();
          table.rows.add(data);
          table.draw();
          $('#infoCount').text('Total: ' + data.length + ' pasien dari ' + res.total_kelompok + ' kelompok anamnesa sama');
        } else {
          alert('Gagal: ' + (res.error || 'Unknown error'));
        }
      },
      error: function (xhr) {
        alert('Error: ' + xhr.statusText);
      }
    });
  }

  function resetFilter() {
    $('#tgl_awal').val('');
    $('#tgl_akhir').val('');
    $('#tcari').val('');
    loadData();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>
