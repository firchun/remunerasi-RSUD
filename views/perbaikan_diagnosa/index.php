<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();
?>
<?php
$pageTitle = 'Perbaikan Diagnosa Pasien - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
table.dataTable td { color: #1f2937 !important; }
table.dataTable thead th { color: #ffffff !important; background: #166534 !important; padding: 8px 6px !important; font-size: 12px !important; border: 1px solid #065f46 !important; }
.dataTable tbody td { padding: 4px 6px !important; border: 0.5px solid #d1d5db; vertical-align: middle !important; font-size: 13px !important; white-space: nowrap; }
table.dataTable { width: 100% !important; }
.nav-tabs .nav-link { color: #4b5563; font-weight: 500; padding: 12px 20px; border-bottom: 2px solid transparent; transition: all 0.2s; cursor: pointer; }
.nav-tabs .nav-link:hover { color: #10b981; border-color: #d1fae5; background: #ecfdf5; }
.nav-tabs .nav-link.active { color: #047857; border-color: #10b981; background: #f0fdf4; }
.btn-diagnosa { background: #2563eb; color: #fff; border: none; border-radius: 6px; padding: 4px 12px; font-size: 12px; cursor: pointer; transition: background 0.2s; }
.btn-diagnosa:hover { background: #1d4ed8; }
.result-item { padding: 6px 10px; cursor: pointer; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
.result-item:hover { background: #eff6ff; }
#modalSearchResults { max-height: 250px; overflow-y: auto; border: 1px solid #d1d5db; border-radius: 8px; display: none; position: absolute; background: #fff; z-index: 50; width: 100%; }
/* modal overlay background */
.modal-overlay { background: rgba(0,0,0,0.5); }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';

$bulan_ini = date('m');
$tahun_ini = date('Y');
?>

<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i class="fas fa-filter mr-2"></i>Filter Pencarian
  </h3>
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
      <select id="filter_bulan"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        <?php for ($m = 1; $m <= 12; $m++): ?>
          <option value="<?= sprintf('%02d', $m) ?>" <?= $m == $bulan_ini ? 'selected' : '' ?>>
            <?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
      <select id="filter_tahun"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
        <?php for ($y = $tahun_ini; $y >= $tahun_ini - 5; $y--): ?>
          <option value="<?= $y ?>" <?= $y == $tahun_ini ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Cari Pasien</label>
      <input type="text" id="tcari" placeholder="No. Rawat / Nama / No. RM"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
    </div>
    <div class="flex items-end gap-2">
      <button onclick="loadData()"
        class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-xl transition text-sm">
        <i class="fas fa-search mr-1"></i>Cari
      </button>
    </div>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <div class="flex border-b border-gray-200 nav-tabs mb-3" id="diagnosaTabs">
    <div class="nav-link active" data-target="#tab-ralan" onclick="switchTab('Ralan')">Rawat Jalan</div>
    <div class="nav-link" data-target="#tab-ranap" onclick="switchTab('Ranap')">Rawat Inap</div>
  </div>

  <div class="flex justify-between items-center mb-3">
    <h3 class="text-lg font-bold text-green-800">
      <i class="fas fa-stethoscope mr-2"></i>Pasien Tanpa Diagnosa
    </h3>
    <span id="infoCount" class="text-sm text-gray-500"></span>
  </div>

  <div class="overflow-x-auto">
    <table id="tableDiagnosa" class="display w-full">
      <thead>
        <tr>
          <th>No</th>
          <th>No. Rawat</th>
          <th>Tgl Masuk</th>
          <th>Jam</th>
          <th>No. RM</th>
          <th>Nama Pasien</th>
          <th>JK</th>
          <th>Tgl Lahir</th>
          <th>Cara Bayar</th>
          <th>Poliklinik</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<!-- Modal Input Diagnosa -->
<div id="modalDiagnosa" class="hidden fixed inset-0 z-50 flex items-start justify-center pt-10 modal-overlay">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center bg-green-700 text-white px-5 py-3 rounded-t-2xl">
      <h3 class="text-lg font-bold"><i class="fas fa-stethoscope mr-2"></i>Input Diagnosa Pasien</h3>
      <button onclick="closeModal()" class="text-white hover:text-gray-200 text-xl leading-none">&times;</button>
    </div>
    <div class="p-5" id="modalBody">
      <div id="modalLoading" class="text-center py-8 text-gray-500"><i class="fas fa-spinner fa-spin mr-2"></i>Memuat
        data...</div>
      <div id="modalContent" class="hidden">
        <!-- Info Pasien -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-sm grid grid-cols-2 gap-x-4 gap-y-1">
          <div><span class="text-gray-500">No. Rawat:</span> <strong class="font-mono" id="mNoRawat"></strong></div>
          <div><span class="text-gray-500">No. RM:</span> <strong id="mNoRM"></strong></div>
          <div><span class="text-gray-500">Nama:</span> <strong id="mNama"></strong></div>
          <div><span class="text-gray-500">JK:</span> <strong id="mJK"></strong></div>
          <div><span class="text-gray-500">Tgl Masuk:</span> <strong id="mTglMasuk"></strong></div>
          <div><span class="text-gray-500">Cara Bayar:</span> <strong id="mCaraBayar"></strong></div>
        </div>

        <!-- Diagnosa Terdaftar -->
        <div class="mb-4">
          <h4 class="text-sm font-bold text-green-800 mb-2"><i class="fas fa-list mr-1"></i>Diagnosa Terdaftar</h4>
          <div id="listDiagnosa" class="text-sm text-gray-500">Belum ada diagnosa</div>
        </div>

        <hr class="mb-4">

        <!-- Input Baru -->
        <h4 class="text-sm font-bold text-blue-800 mb-2"><i class="fas fa-plus-circle mr-1"></i>Tambah Diagnosa Baru
        </h4>
        <div class="mb-3 relative">
          <label class="block text-sm font-semibold text-gray-700 mb-1">Cari Diagnosa (ICD-10)</label>
          <input type="text" id="modalSearchDiagnosa" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"
            placeholder="Ketik kode atau nama penyakit..." autocomplete="off">
          <div id="modalSearchResults"></div>
          <input type="hidden" id="mKdPenyakit">
          <input type="hidden" id="mStatusLanjut">
        </div>

        <div id="modalSelectedDiagnosa" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3 text-sm">
          <strong>Dipilih:</strong> <span id="modalSelectedText"></span>
          <button type="button" onclick="clearPilihan()" class="text-red-600 ml-2"><i class="fas fa-times"></i></button>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Prioritas</label>
            <select id="mPrioritas" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option value="1">1 - Utama</option>
              <option value="2">2 - Sekunder</option>
              <option value="3">3 - Tersier</option>
              <option value="4">4</option>
              <option value="5">5</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Status Penyakit</label>
            <select id="mStatusPenyakit" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
              <option value="Baru">Baru</option>
              <option value="Lama">Lama</option>
            </select>
          </div>
        </div>

        <div class="flex justify-end gap-2">
          <button onclick="closeModal()"
            class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-100">Tutup</button>
          <button onclick="simpanDiagnosa()"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm" id="btnSimpanModal"
            disabled><i class="fas fa-save mr-1"></i>Simpan</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  let table;
  let currentStatus = 'Ralan';
  let modalSearchTimer;
  let currentNoRawat = '';

  function switchTab(status) {
    currentStatus = status;
    document.querySelectorAll('.nav-tabs .nav-link').forEach(el => el.classList.remove('active'));
    document.querySelector('.nav-tabs .nav-link[data-target="#tab-' + status.toLowerCase() + '"]').classList.add('active');
    loadData();
  }

  $(document).ready(function () {
    table = $('#tableDiagnosa').DataTable({
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
        { data: 'tgl_registrasi', className: 'text-center text-xs' },
        { data: 'jam_reg', className: 'text-center text-xs' },
        { data: 'no_rkm_medis', className: 'text-center font-mono text-xs' },
        { data: 'nm_pasien', className: 'text-left' },
        { data: 'jk', className: 'text-center' },
        { data: 'tgl_lahir', className: 'text-center text-xs', render: d => d || '-' },
        { data: 'png_jawab', className: 'text-center', render: d => d || '-' },
        { data: 'nm_poli', className: 'text-left text-xs', render: d => d || '-' },
        {
          data: null,
          className: 'text-center',
          sortable: false,
          render: function (d) {
            return '<button onclick="openModal(\'' + d.no_rawat + '\')" class="btn-diagnosa"><i class="fas fa-plus-circle mr-1"></i>Input Diagnosa</button>';
          }
        }
      ]
    });

    loadData();
  });

  function loadData() {
    var bulan = $('#filter_bulan').val();
    var tahun = $('#filter_tahun').val();
    var cari = $('#tcari').val();

    $.ajax({
      url: window.BASE_URL + '/api/get_data_perbaikan_diagnosa.php',
      type: 'POST',
      data: {
        bulan: bulan,
        tahun: tahun,
        status_lanjut: currentStatus,
        tcari: cari
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
          $('#infoCount').text('Total: ' + data.length + ' / ' + (res.total || 0) + ' pasien tanpa diagnosa');
        } else {
          alert('Gagal: ' + (res.error || 'Unknown error'));
        }
      },
      error: function (xhr) {
        alert('Error: ' + xhr.statusText);
      }
    });
  }

  function openModal(noRawat) {
    currentNoRawat = noRawat;
    $('#modalLoading').removeClass('hidden');
    $('#modalContent').addClass('hidden');
    $('#mKdPenyakit').val('');
    $('#mStatusLanjut').val(currentStatus);
    clearPilihan();
    $('#modalDiagnosa').removeClass('hidden');

    $.ajax({
      url: window.BASE_URL + '/api/get_diagnosa_pasien.php',
      type: 'POST',
      data: { no_rawat: noRawat, status: currentStatus },
      success: function (res) {
        var r = JSON.parse(res);
        if (r.success) {
          var p = r.pasien;
          $('#mNoRawat').text(p.no_rawat);
          $('#mNoRM').text(p.no_rkm_medis);
          $('#mNama').text(p.nm_pasien);
          $('#mJK').text(p.jk);
          $('#mTglMasuk').text(p.tgl_registrasi + ' ' + (p.jam_reg || '').substr(0, 5));
          $('#mCaraBayar').text(p.png_jawab || '-');
          renderDiagnosaList(r.diagnosa);
          $('#modalLoading').addClass('hidden');
          $('#modalContent').removeClass('hidden');
        } else {
          alert('Gagal: ' + (r.message || 'Unknown error'));
          closeModal();
        }
      },
      error: function () {
        alert('Error server');
        closeModal();
      }
    });
  }

  function closeModal() {
    $('#modalDiagnosa').addClass('hidden');
    currentNoRawat = '';
  }

  function renderDiagnosaList(diagnosa) {
    if (!diagnosa || diagnosa.length === 0) {
      $('#listDiagnosa').html('<span class="text-gray-500">Belum ada diagnosa</span>');
      return;
    }
    var html = '<table class="w-full text-sm border-collapse">' +
      '<thead><tr class="bg-green-100">' +
      '<th class="p-1.5 text-left" style="width: 14%;">Prioritas</th>' +
      '<th class="p-1.5 text-left" style="width: 18%;">Kode</th>' +
      '<th class="p-1.5 text-left">Nama Diagnosa</th>' +
      '<th class="p-1.5 text-left" style="width: 14%;">Status</th>' +
      '<th class="p-1.5 text-center" style="width: 10%;">Aksi</th>' +
      '</tr></thead><tbody>';
    diagnosa.forEach(function (d) {
      html += '<tr class="border-b border-gray-200">' +
        '<td class="p-1.5">' + d.prioritas + '</td>' +
        '<td class="p-1.5 font-mono">' + d.kd_penyakit + '</td>' +
        '<td class="p-1.5">' + (d.nm_penyakit || '-') + '</td>' +
        '<td class="p-1.5">' + (d.status_penyakit || '-') + '</td>' +
        '<td class="p-1.5 text-center">' +
        '<button onclick="hapusDiagnosa(\'' + d.kd_penyakit + '\')" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>' +
        '</td></tr>';
    });
    html += '</tbody></table>';
    $('#listDiagnosa').html(html);
  }

  function hapusDiagnosa(kdPenyakit) {
    if (!confirm('Hapus diagnosa ini?')) return;
    $.ajax({
      url: window.BASE_URL + '/api/hapus_diagnosa_pasien.php',
      type: 'POST',
      data: { no_rawat: currentNoRawat, kd_penyakit: kdPenyakit, status: currentStatus },
      success: function (res) {
        var r = JSON.parse(res);
        if (r.success) {
          loadModalDiagnosa();
        } else {
          alert('Gagal: ' + (r.message || 'Unknown error'));
        }
      },
      error: function () { alert('Error server'); }
    });
  }

  function loadModalDiagnosa() {
    $.ajax({
      url: window.BASE_URL + '/api/get_diagnosa_pasien.php',
      type: 'POST',
      data: { no_rawat: currentNoRawat, status: currentStatus },
      success: function (res) {
        var r = JSON.parse(res);
        if (r.success) renderDiagnosaList(r.diagnosa);
      }
    });
  }

  // Search diagnosis in modal
  $('#modalSearchDiagnosa').on('input', function () {
    clearTimeout(modalSearchTimer);
    var q = $(this).val().trim();
    if (q.length < 2) {
      $('#modalSearchResults').hide();
      return;
    }
    modalSearchTimer = setTimeout(function () {
      $.ajax({
        url: window.BASE_URL + '/api/cari_penyakit.php',
        type: 'POST',
        data: { q: q },
        success: function (res) {
          var r = JSON.parse(res);
          var $results = $('#modalSearchResults');
          if (r.success && r.data.length > 0) {
            var html = '';
            r.data.forEach(function (item) {
              html += '<div class="result-item" onclick="pilihDiagnosa(\'' + item.kd_penyakit + '\', \'' + item.nm_penyakit.replace(/'/g, "\\'") + '\')">';
              html += '<strong>' + item.kd_penyakit + '</strong> - ' + item.nm_penyakit;
              html += '</div>';
            });
            $results.html(html).show();
          } else {
            $results.html('<div class="p-2 text-gray-500 text-sm">Tidak ditemukan</div>').show();
          }
        }
      });
    }, 300);
  });

  function pilihDiagnosa(kd, nm) {
    $('#mKdPenyakit').val(kd);
    $('#modalSelectedText').text(kd + ' - ' + nm);
    $('#modalSelectedDiagnosa').removeClass('hidden');
    $('#modalSearchResults').hide();
    $('#modalSearchDiagnosa').val(kd + ' - ' + nm);
    $('#btnSimpanModal').prop('disabled', false);
    $('#modalSearchDiagnosa').focus();
  }

  function clearPilihan() {
    $('#mKdPenyakit').val('');
    $('#modalSelectedDiagnosa').addClass('hidden');
    $('#modalSearchDiagnosa').val('');
    $('#btnSimpanModal').prop('disabled', true);
    $('#modalSearchResults').hide();
  }

  function simpanDiagnosa() {
    var kd = $('#mKdPenyakit').val();
    if (!kd) { alert('Pilih diagnosa terlebih dahulu'); return; }
    $.ajax({
      url: window.BASE_URL + '/views/perbaikan_diagnosa/simpan_diagnosa.php',
      type: 'POST',
      data: {
        no_rawat: currentNoRawat,
        kd_penyakit: kd,
        status: currentStatus,
        prioritas: $('#mPrioritas').val(),
        status_penyakit: $('#mStatusPenyakit').val()
      },
      success: function (res) {
        var r = JSON.parse(res);
        if (r.success) {
          clearPilihan();
          loadModalDiagnosa();
          $('#mPrioritas').val('1');
          $('#mStatusPenyakit').val('Baru');
        } else {
          alert('Gagal: ' + (r.message || 'Unknown error'));
        }
      },
      error: function () { alert('Error server'); }
    });
  }

  // Click outside modal to close
  $('#modalDiagnosa').on('click', function (e) {
    if (e.target === this) closeModal();
  });
</script>
<?php require_once '../layouts/footer.php'; ?>