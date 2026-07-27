<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();
?>
<?php
$pageTitle = 'Perbaikan Triase - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
table.dataTable td { color: #1f2937 !important; }
table.dataTable thead th { color: #ffffff !important; background: #166534 !important; padding: 8px 6px !important; font-size: 12px !important; border: 1px solid #065f46 !important; }
.dataTable tbody td { padding: 4px 6px !important; border: 0.5px solid #d1d5db; vertical-align: middle !important; font-size: 13px !important; white-space: nowrap; }
table.dataTable { width: 100% !important; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; }
.badge-s1 { background: #dc2626; color: #fff; }
.badge-s2 { background: #f97316; color: #fff; }
.badge-s3 { background: #eab308; color: #000; }
.badge-s4 { background: #22c55e; color: #fff; }
.badge-s5 { background: #6b7280; color: #fff; }
.badge-s0 { background: #e5e7eb; color: #6b7280; }
.status-gd { background: #d1fae5; color: #065f46; font-weight: 600; }
.status-non-gd { background: #fee2e2; color: #991b1b; font-weight: 600; }
.status-urgent-label { background: #fef3c7; color: #92400e; font-weight: 600; }
.status-tanpa { background: #f3f4f6; color: #6b7280; font-style: italic; }
.filter-btn { padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 500; cursor: pointer; border: 1px solid #d1d5db; background: #fff; transition: all 0.2s; }
.filter-btn:hover { background: #f3f4f6; }
.filter-btn.active { background: #166534; color: #fff; border-color: #166534; }
.filter-btn.active-orange { background: #dc2626; color: #fff; border-color: #dc2626; }
.match-yes { color: #16a34a; font-weight: 700; }
.match-no { color: #dc2626; font-weight: 700; }
.bpjs-flag { background: #dbeafe; color: #1e40af; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; }
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
      <label class="block text-sm font-medium text-gray-700 mb-2">Cari</label>
      <input type="text" id="tcari" placeholder="No. Rawat / Nama / No. RM" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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

  <div class="flex flex-wrap gap-2 mt-2">
    <button class="filter-btn active active-orange" data-filter="" onclick="setFilter(this, '')">Tidak Didukung Vital</button>
    <button class="filter-btn" data-filter="all" onclick="setFilter(this, 'all')">Semua</button>
    <button class="filter-btn" data-filter="sesuai_gd" onclick="setFilter(this, 'sesuai_gd')">Sesuai GD</button>
    <button class="filter-btn" data-filter="urgent" onclick="setFilter(this, 'urgent')">Urgent</button>
    <button class="filter-btn" data-filter="tidak_sesuai_gd" onclick="setFilter(this, 'tidak_sesuai_gd')">Tidak Sesuai GD</button>
    <button class="filter-btn" data-filter="tanpa_skala" onclick="setFilter(this, 'tanpa_skala')">Tanpa Skala</button>
    <button class="filter-btn" data-filter="bpjs" onclick="setFilter(this, 'bpjs')">BPJS</button>
    <button class="filter-btn" data-filter="bpjs_non_gd" onclick="setFilter(this, 'bpjs_non_gd')">BPJS Non GD</button>
    <button class="filter-btn" data-filter="discrepancy" onclick="setFilter(this, 'discrepancy')">Tidak Cocok Skala</button>
    <button class="filter-btn" data-filter="bpjs_discrepancy" onclick="setFilter(this, 'bpjs_discrepancy')">BPJS Tdk Cocok</button>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <div class="flex justify-between items-center mb-3">
    <h3 class="text-lg font-bold text-green-800">
      <i class="fas fa-notes-medical mr-2"></i>Data Triase IGD
    </h3>
    <span id="infoCount" class="text-sm text-gray-500"></span>
  </div>

  <div class="overflow-x-auto">
    <table id="tableTriase" class="display w-full">
      <thead>
        <tr>
          <th>No</th>
          <th>No. Rawat</th>
          <th>Tgl</th>
          <th>Pasien</th>
          <th>Cara Bayar</th>
          <th>No. SEP</th>
          <th>TD</th>
          <th>Nadi</th>
          <th>RR</th>
          <th>Suhu</th>
          <th>SpO2</th>
          <th>Nyeri</th>
          <th>Skala (DB)</th>
          <th>Skala (Vital)</th>
          <th>Dukung Vital</th>
          <th>Status</th>
          <th>Catatan</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div id="modalEdit" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center" onclick="if(event.target===this)closeEdit()">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
    <div class="bg-gradient-to-r from-blue-700 to-blue-800 px-5 py-4 flex justify-between items-center rounded-t-2xl">
      <h3 class="text-white font-bold text-lg"><i class="fas fa-edit mr-2"></i>Edit Triase</h3>
      <button onclick="closeEdit()" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
    </div>
    <div class="p-5 space-y-4">
      <input type="hidden" id="edit_no_rawat">

      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1">No. Rawat</label>
        <input type="text" id="edit_no_rawat_display" class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg text-sm font-mono" readonly>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">TD</label>
          <input type="text" id="edit_td" placeholder="120/80" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Nadi</label>
          <input type="text" id="edit_nadi" placeholder="80" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">RR</label>
          <input type="text" id="edit_rr" placeholder="20" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Suhu</label>
          <input type="text" id="edit_suhu" placeholder="36.5" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">SpO2</label>
          <input type="text" id="edit_spo2" placeholder="98" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1">Nyeri</label>
          <input type="text" id="edit_nyeri" placeholder="0-10" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>
      </div>

      <div id="edit_preview" class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-800 hidden">
        <strong>Skala berdasar vital sign:</strong> <span id="edit_preview_text"></span>
      </div>

      <div class="flex justify-end gap-2 pt-2 border-t border-gray-200">
        <button onclick="closeEdit()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100">Batal</button>
        <button onclick="saveEdit()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm"><i class="fas fa-save mr-1"></i>Simpan</button>
      </div>
    </div>
  </div>
</div>

<div class="mt-4 bg-white rounded-2xl border border-green-700 p-4">
  <h4 class="text-md font-bold text-green-800 mb-3"><i class="fas fa-info-circle mr-2"></i>Kriteria Triase Berdasarkan Vital Sign</h4>
  <div class="grid grid-cols-1 md:grid-cols-5 gap-3 text-xs">
    <div class="border border-red-300 rounded-lg p-3 bg-red-50">
      <strong class="text-red-700">Skala 1 - Resusitasi</strong>
      <ul class="mt-1 text-red-600 list-disc pl-4">
        <li>Suhu ≤35°C / ≥39.1°C</li>
        <li>Nyeri 9-10</li>
        <li>TD ≤90 / ≥220 mmHg</li>
        <li>Nadi ≤40 / ≥131</li>
        <li>SpO2 ≤91%</li>
        <li>RR ≤8 / ≥25</li>
      </ul>
    </div>
    <div class="border border-orange-300 rounded-lg p-3 bg-orange-50">
      <strong class="text-orange-700">Skala 2 - Emergent</strong>
      <ul class="mt-1 text-orange-600 list-disc pl-4">
        <li>Suhu ≤35°C / ≥38.1°C</li>
        <li>Nyeri 7-10</li>
        <li>TD ≤100 / ≥220 mmHg</li>
        <li>Nadi ≤50 / ≥111</li>
        <li>SpO2 ≤93%</li>
        <li>RR ≤8 / ≥21</li>
      </ul>
    </div>
    <div class="border border-yellow-300 rounded-lg p-3 bg-yellow-50">
      <strong class="text-yellow-700">Skala 3 - Urgent</strong>
      <ul class="mt-1 text-yellow-600 list-disc pl-4">
        <li>Suhu ≤36°C / ≥38.1°C</li>
        <li>TD ≤110 / ≥220 mmHg</li>
        <li>Nadi ≤50 / ≥91</li>
        <li>SpO2 ≤95%</li>
        <li>RR ≤11 / ≥21</li>
      </ul>
    </div>
    <div class="border border-green-300 rounded-lg p-3 bg-green-50">
      <strong class="text-green-700">Skala 4 - Semi Urgent</strong>
      <ul class="mt-1 text-green-600 list-disc pl-4">
        <li>Suhu ≤36°C / ≥38.1°C</li>
        <li>TD ≤110 / ≥220 mmHg</li>
        <li>Nadi ≤50 / ≥91</li>
        <li>SpO2 ≤95%</li>
        <li>RR ≤11 / ≥21</li>
      </ul>
    </div>
    <div class="border border-gray-300 rounded-lg p-3 bg-gray-50">
      <strong class="text-gray-700">Skala 5 - Non Urgent</strong>
      <ul class="mt-1 text-gray-600 list-disc pl-4">
        <li>Semua nilai normal</li>
      </ul>
    </div>
  </div>
</div>

<script>
  let table;
  let currentFilter = '';

  function setFilter(btn, filter) {
    document.querySelectorAll('.filter-btn').forEach(b => {
      b.classList.remove('active');
      b.classList.remove('active-orange');
    });
    if (filter === '') btn.classList.add('active', 'active-orange');
    else btn.classList.add('active');
    currentFilter = filter;
    loadData();
  }

  function exportExcel() {
    let params = 'tgl_awal=' + $('#tgl_awal').val() +
                 '&tgl_akhir=' + $('#tgl_akhir').val() +
                 '&filter_status=' + currentFilter +
                 '&tcari=' + $('#tcari').val();
    window.location.href = window.BASE_URL + '/api/export_perbaikan_triase.php?' + params;
  }

  $(document).ready(function () {
    table = $('#tableTriase').DataTable({
      dom: '<"flex justify-between items-center mb-2"l>rtip',
      lengthMenu: [[25, 50, 100, 500], [25, 50, 100, 500]],
      pageLength: 50,
      scrollX: true,
      autoWidth: false,
      ordering: true,
      searching: true,
      info: true,
      columns: [
        { data: 'no', className: 'text-center' },
        { data: 'no_rawat', className: 'text-left font-mono text-xs' },
        { data: 'tgl_kunjungan', className: 'text-center text-xs' },
        {
          data: null,
          className: 'text-left',
          render: function(d) { return d.nm_pasien + '<br><span class="text-xs text-gray-500 font-mono">' + d.no_rkm_medis + '</span>'; }
        },
        {
          data: null,
          className: 'text-center',
          render: function(d) {
            var html = d.png_jawab || '-';
            if (d.is_bpjs) html += ' <span class="bpjs-flag">BPJS</span>';
            return html;
          }
        },
        { data: 'no_sep', className: 'text-center font-mono text-xs', render: d => d || '-' },
        { data: 'tekanan_darah', className: 'text-center', render: d => d || '-' },
        { data: 'nadi', className: 'text-center', render: d => d || '-' },
        { data: 'pernapasan', className: 'text-center', render: d => d || '-' },
        { data: 'suhu', className: 'text-center', render: d => d || '-' },
        { data: 'saturasi_o2', className: 'text-center', render: d => d || '-' },
        { data: 'nyeri', className: 'text-center', render: d => d || '-' },
        {
          data: null,
          className: 'text-center',
          render: function(d) {
            var s = parseInt(d.actual_skala);
            return '<span class="badge badge-s' + s + '">' + d.actual_skala_nama + '</span>';
          }
        },
        {
          data: null,
          className: 'text-center',
          render: function(d) {
            var s = parseInt(d.expected_skala);
            return '<span class="badge badge-s' + s + '">' + d.expected_skala_nama + '</span>';
          }
        },
        {
          data: null,
          className: 'text-center',
          render: function(d) {
            if (d.actual_skala == 0) return '<span class="match-no">TIDAK ADA</span>';
            if (d.is_supported) return '<span class="match-yes">ADA</span>';
            return '<span class="match-no">TIDAK</span>';
          }
        },
        {
          data: null,
          className: 'text-center',
          render: function(d) {
            var actualLabel = d.actual_status || '';
            var cls = 'status-gd';
            if (actualLabel === 'TIDAK SESUAI GD') cls = 'status-non-gd';
            else if (actualLabel === 'URGENT') cls = 'status-urgent-label';
            else if (actualLabel === 'TANPA SKALA') cls = 'status-tanpa';
            var arrow = d.expected_gd ? '→ GD' : '→ NON GD';
            return '<div><span class="px-2 py-1 rounded-lg text-xs ' + cls + '">' + actualLabel + '</span></div>' +
                   '<div class="text-xs text-gray-500 mt-1">' + arrow + '</div>';
          }
        },
        {
          data: 'bpjs_note',
          className: 'text-center text-xs',
          render: function(d) {
            if (!d) return '-';
            var cls = d.includes('HARUSNYA') ? 'text-red-600 font-bold' : d.includes('SESUAI') ? 'text-green-600' : 'text-blue-600';
            return '<span class="' + cls + '">' + d + '</span>';
          }
        },
        {
          data: null,
          className: 'text-center',
          sortable: false,
          render: function(d) {
            return '<button onclick="openEdit(\'' + d.no_rawat + '\')" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-xs transition"><i class="fas fa-edit mr-1"></i>Edit</button>';
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
      url: window.BASE_URL + '/api/get_data_perbaikan_triase.php',
      type: 'POST',
      data: {
        tgl_awal: $('#tgl_awal').val(),
        tgl_akhir: $('#tgl_akhir').val(),
        tcari: $('#tcari').val(),
        filter_status: currentFilter
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
          $('#infoCount').text('Total: ' + data.length + ' data');
        } else {
          alert('Gagal: ' + (res.error || 'Unknown error'));
        }
      },
      error: function (xhr) {
        alert('Error: ' + xhr.statusText);
      }
    });
  }

  // ─── Edit Modal ───────────────────────────────────────────────────
  var editData = null;

  function openEdit(noRawat) {
    // Cari data dari tabel
    var allData = table.rows().data().toArray();
    var row = allData.find(function(r) { return r.no_rawat === noRawat; });
    if (!row) { alert('Data tidak ditemukan'); return; }
    editData = row;

    $('#edit_no_rawat').val(row.no_rawat);
    $('#edit_no_rawat_display').val(row.no_rawat);
    $('#edit_td').val(row.tekanan_darah && row.tekanan_darah !== '-' ? row.tekanan_darah : '');
    $('#edit_nadi').val(row.nadi && row.nadi !== '-' ? row.nadi : '');
    $('#edit_rr').val(row.pernapasan && row.pernapasan !== '-' ? row.pernapasan : '');
    $('#edit_suhu').val(row.suhu && row.suhu !== '-' ? row.suhu : '');
    $('#edit_spo2').val(row.saturasi_o2 && row.saturasi_o2 !== '-' ? row.saturasi_o2 : '');
    $('#edit_nyeri').val(row.nyeri && row.nyeri !== '-' ? row.nyeri : '');
    $('#edit_preview').addClass('hidden');
    $('#modalEdit').removeClass('hidden');
  }

  function closeEdit() {
    $('#modalEdit').addClass('hidden');
    editData = null;
  }

  function hitungPreviewSkala() {
    var td = $('#edit_td').val() || '-';
    var nadi = $('#edit_nadi').val() || '-';
    var rr = $('#edit_rr').val() || '-';
    var suhu = $('#edit_suhu').val() || '-';
    var spo2 = $('#edit_spo2').val() || '-';
    var nyeri = $('#edit_nyeri').val() || '-';

    $.ajax({
      url: window.BASE_URL + '/api/hitung_skala_vital.php',
      type: 'POST',
      data: { td: td, nadi: nadi, rr: rr, suhu: suhu, spo2: spo2, nyeri: nyeri },
      success: function(res) {
        var r = JSON.parse(res);
        if (r.success) {
          $('#edit_preview').removeClass('hidden');
          $('#edit_preview_text').text(r.skala_nama);
          $('#edit_preview').removeClass('bg-blue-50 border-blue-200 text-blue-800 bg-yellow-50 border-yellow-200 text-yellow-800 bg-red-50 border-red-200 text-red-800');
          if (r.skala <= 2) $('#edit_preview').addClass('bg-red-50 border-red-200 text-red-800');
          else if (r.skala == 3) $('#edit_preview').addClass('bg-yellow-50 border-yellow-200 text-yellow-800');
          else $('#edit_preview').addClass('bg-blue-50 border-blue-200 text-blue-800');
        }
      }
    });
  }

  $('#edit_td, #edit_nadi, #edit_rr, #edit_suhu, #edit_spo2, #edit_nyeri').on('input', hitungPreviewSkala);

  function saveEdit() {
    var noRawat = $('#edit_no_rawat').val();
    var td = $('#edit_td').val();
    var nadi = $('#edit_nadi').val();
    var rr = $('#edit_rr').val();
    var suhu = $('#edit_suhu').val();
    var spo2 = $('#edit_spo2').val();
    var nyeri = $('#edit_nyeri').val();

    if (!confirm('Simpan perubahan triase untuk ' + noRawat + '?')) return;

    $.ajax({
      url: window.BASE_URL + '/api/update_triase.php',
      type: 'POST',
      data: {
        no_rawat: noRawat,
        tekanan_darah: td,
        nadi: nadi,
        pernapasan: rr,
        suhu: suhu,
        saturasi_o2: spo2,
        nyeri: nyeri,
        skala: skala
      },
      success: function(res) {
        var r = JSON.parse(res);
        if (r.success) {
          alert('Data berhasil disimpan!');
          closeEdit();
          loadData();
        } else {
          alert('Gagal: ' + (r.message || 'Unknown error'));
        }
      },
      error: function(xhr) {
        alert('Error: ' + xhr.statusText);
      }
    });
  }

  function resetFilter() {
    $('#tgl_awal').val('');
    $('#tgl_akhir').val('');
    $('#tcari').val('');
    document.querySelectorAll('.filter-btn').forEach(b => {
      b.classList.remove('active');
      b.classList.remove('active-orange');
    });
    document.querySelector('.filter-btn[data-filter=""]').classList.add('active', 'active-orange');
    currentFilter = '';
    loadData();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>
