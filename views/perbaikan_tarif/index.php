<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$pageTitle = 'Perbaikan Tarif - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  table.dataTable td { color: #1f2937 !important; } table.dataTable thead th { color: #ffffff !important; }
  .dataTable th, .dataTable td { white-space: nowrap; }
  .dataTable tbody td { padding: 8px !important; border: 0.5px solid #d1d5db; vertical-align: middle !important; }
  table.dataTable { width: 100% !important; }
  .nav-tabs .nav-link { color: #4b5563; font-weight: 500; padding: 12px 20px; border-bottom: 2px solid transparent; transition: all 0.2s; cursor: pointer; }
  .nav-tabs .nav-link:hover { color: #10b981; border-color: #d1fae5; background: #ecfdf5; }
  .nav-tabs .nav-link.active { color: #047857; border-color: #10b981; background: #f0fdf4; }
  .btn-perbaiki { background: #047857; color: #fff; border: none; border-radius: 6px; padding: 4px 12px; font-size: 12px; cursor: pointer; transition: background 0.2s; }
  .btn-perbaiki:hover { background: #065f46; }
  /* Modal */
  #modalPerbaiki, #modalEditTarifMaster { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.5); align-items:center; justify-content:center; }
  #modalPerbaiki.show, #modalEditTarifMaster.show { display:flex; }
  .modal-box { background:#fff; border-radius:16px; width:92%; max-width:900px; max-height:90vh; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden; }
  .modal-header { background:#047857; color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center; }
  .modal-header h3 { margin:0; font-size:16px; }
  .modal-close { background:none; border:none; color:#fff; font-size:20px; cursor:pointer; line-height:1; }
  .modal-body { padding:20px; overflow-y:auto; flex:1; }
  .modal-footer { padding:14px 20px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:10px; }
  .form-range { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-bottom:18px; }
  .form-range label { font-size:13px; font-weight:600; color:#374151; display:block; margin-bottom:4px; }
  .form-range input[type=date] { padding:7px 10px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; }
  .btn-cari { background:#2563eb; color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; transition:background .2s; }
  .btn-cari:hover { background:#1d4ed8; }
  .btn-eksekusi { background:#dc2626; color:#fff; border:none; border-radius:8px; padding:8px 20px; font-size:13px; cursor:pointer; transition:background .2s; display:none; }
  .btn-eksekusi:hover { background:#b91c1c; }
  .btn-batal { background:#6b7280; color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer; }
  .preview-info { background:#fef3c7; border:1px solid #f59e0b; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:12px; display:none; }
  .preview-table-wrap { overflow-x:auto; max-height:320px; overflow-y:auto; }
  #previewTable { width:100%; border-collapse:collapse; font-size:13px; }
  #previewTable thead th { background:#047857; color:#fff; padding:8px 10px; text-align:left; position:sticky; top:0; }
  #previewTable tbody td { padding:7px 10px; border-bottom:1px solid #e5e7eb; }
  #previewTable tbody tr:hover { background:#f0fdf4; }
  .selisih-pos { color:#16a34a; font-weight:600; }
  .selisih-neg { color:#dc2626; font-weight:600; }
  .selisih-zero { color:#6b7280; }
  .alert-success { background:#d1fae5; border:1px solid #6ee7b7; border-radius:8px; padding:12px 16px; font-size:13px; color:#065f46; margin-bottom:10px; display:none; }
  .alert-error { background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:12px 16px; font-size:13px; color:#991b1b; margin-bottom:10px; display:none; }
  .search-bar { position:relative; margin-bottom:10px; }
  .search-bar input { width:100%; padding:8px 12px 8px 34px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; box-sizing:border-box; }
  .search-bar .icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:14px; }
  #previewTable tbody tr.selected { background:#ecfdf5 !important; }
  #previewTable tbody td:first-child { text-align:center; }
  #previewTable thead th:first-child { text-align:center; width:36px; }
  .row-cb { cursor:pointer; width:15px; height:15px; accent-color:#047857; }
  #chkAll { cursor:pointer; width:15px; height:15px; accent-color:#047857; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>

<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <!-- Tabs Navigation -->
  <div class="flex border-b border-gray-200 nav-tabs overflow-x-auto" id="tarifTabs">
    <div class="nav-link active" data-target="#tab-ralan">Rawat Jalan</div>
    <div class="nav-link" data-target="#tab-ranap">Rawat Inap</div>
    <div class="nav-link" data-target="#tab-lab">Laboratorium</div>
    <div class="nav-link" data-target="#tab-radiologi">Radiologi</div>
    <div class="nav-link" data-target="#tab-operasi">Operasi</div>
  </div>

  <!-- Tabs Content -->
  <div class="mt-4">
    
    <!-- Tab Rawat Jalan -->
    <div id="tab-ralan" class="tab-pane">
      <div class="overflow-x-auto">
        <table id="tableRalan" class="display w-full dataTable">
          <thead class="bg-green-800 text-white">
            <tr>
              <th class="px-2 text-left">Kode Jenis Perawatan</th>
              <th class="px-2 text-left">Nama Perawatan</th>
              <th class="px-2 text-right">Total Bayar Dokter</th>
              <th class="px-2 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- Tab Rawat Inap -->
    <div id="tab-ranap" class="tab-pane hidden">
      <div class="overflow-x-auto">
        <table id="tableRanap" class="display w-full dataTable">
          <thead class="bg-green-800 text-white">
            <tr>
              <th class="px-2 text-left">Kode Jenis Perawatan</th>
              <th class="px-2 text-left">Nama Perawatan</th>
              <th class="px-2 text-right">Total Bayar Dokter</th>
              <th class="px-2 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- Tab Laboratorium -->
    <div id="tab-lab" class="tab-pane hidden">
      <div class="overflow-x-auto">
        <table id="tableLab" class="display w-full dataTable">
          <thead class="bg-green-800 text-white">
            <tr>
              <th class="px-2 text-left">Kode Jenis Perawatan</th>
              <th class="px-2 text-left">Nama Perawatan</th>
              <th class="px-2 text-right">Total Bayar Dokter</th>
              <th class="px-2 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- Tab Radiologi -->
    <div id="tab-radiologi" class="tab-pane hidden">
      <div class="overflow-x-auto">
        <table id="tableRadiologi" class="display w-full dataTable">
          <thead class="bg-green-800 text-white">
            <tr>
              <th class="px-2 text-left">Kode Jenis Perawatan</th>
              <th class="px-2 text-left">Nama Perawatan</th>
              <th class="px-2 text-right">Total Bayar Dokter</th>
              <th class="px-2 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    <!-- Tab Operasi -->
    <div id="tab-operasi" class="tab-pane hidden">
      <div class="overflow-x-auto">
        <table id="tableOperasi" class="display w-full dataTable">
          <thead class="bg-green-800 text-white">
            <tr>
              <th class="px-2 text-left">Kode Paket</th>
              <th class="px-2 text-left">Nama Perawatan</th>
              <th class="px-2 text-right">Total Biaya</th>
              <th class="px-2 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Modal Perbaiki Tarif -->
<div id="modalPerbaiki">
  <div class="modal-box">
    <div class="modal-header">
      <h3 id="modalTitle">Perbaiki Tarif</h3>
      <button class="modal-close" id="btnCloseModal">&times;</button>
    </div>
    <div class="modal-body">
      <div class="alert-success" id="alertSuccess"></div>
      <div class="alert-error" id="alertError"></div>

      <div class="form-range">
        <div>
          <label for="inputTglMulai">Tanggal Mulai</label>
          <input type="date" id="inputTglMulai">
        </div>
        <div>
          <label for="inputTglAkhir">Tanggal Akhir</label>
          <input type="date" id="inputTglAkhir">
        </div>
        <button class="btn-cari" id="btnCari">&#128269; Cari Data</button>
      </div>

      <div class="preview-info" id="previewInfo"></div>

      <div class="search-bar" id="searchBarWrap" style="display:none">
        <span class="icon">&#128269;</span>
        <input type="text" id="inputSearchNoRawat" placeholder="Cari No Rawat...">
      </div>

      <div class="preview-table-wrap">
        <table id="previewTable">
          <thead>
            <tr>
              <th rowspan="2"><input type="checkbox" id="chkAll" title="Pilih semua"></th>
              <th rowspan="2">No Rawat</th>
              <th rowspan="2">Tgl Perawatan</th>
              <th rowspan="2">Kode Dokter</th>
              <th colspan="2" style="text-align:center">Biaya Rawat (Rp)</th>
              <th colspan="2" style="text-align:center">Material (Rp)</th>
              <th colspan="2" style="text-align:center">Menejemen (Rp)</th>
              <th colspan="2" style="text-align:center">Tindakan Dr (Rp)</th>
              <th colspan="2" style="text-align:center">Tindakan Pr (Rp)</th>
            </tr>
            <tr>
              <th style="text-align:right">Lama</th>
              <th style="text-align:right">Baru</th>
              <th style="text-align:right">Lama</th>
              <th style="text-align:right">Baru</th>
              <th style="text-align:right">Lama</th>
              <th style="text-align:right">Baru</th>
              <th style="text-align:right">Lama</th>
              <th style="text-align:right">Baru</th>
              <th style="text-align:right">Lama</th>
              <th style="text-align:right">Baru</th>
            </tr>
          </thead>
          <tbody id="previewBody">
            <tr><td colspan="14" style="text-align:center;color:#9ca3af;padding:20px">Pilih range tanggal lalu klik Cari Data</td></tr>
          </tbody>
        </table>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn-batal" id="btnBatal">Tutup</button>
      <button class="btn-eksekusi" id="btnEksekusi">&#9888; Perbaiki Tarif</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="modalEditTarifMaster">
  <div class="modal-box" style="max-width: 600px;">
    <div class="modal-header">
      <h3 id="modalEditTitle">Edit Tarif Master</h3>
      <button class="modal-close" id="btnCloseEditModal">&times;</button>
    </div>
    <div class="modal-body">
      <div id="alertEditSuccess" class="alert-success"></div>
      <div id="alertEditError" class="alert-error"></div>
      <form id="formEditTarif">
        <input type="hidden" name="type" id="editType">
        <input type="hidden" name="kd" id="editKd">
        <div id="editFormFields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn-batal" id="btnBatalEdit">Batal</button>
      <button class="btn-edit" id="btnSimpanEdit" style="display:inline-block;">&#128190; Simpan Perubahan</button>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
  // Tab Switching Logic
  $('.nav-link').click(function() {
    $('.nav-link').removeClass('active');
    $(this).addClass('active');
    
    $('.tab-pane').addClass('hidden');
    const target = $(this).data('target');
    $(target).removeClass('hidden');

    // Redraw tables when they become visible
    if(target === '#tab-ranap') tableRanap.columns.adjust().draw();
    if(target === '#tab-lab') tableLab.columns.adjust().draw();
    if(target === '#tab-radiologi') tableRadiologi.columns.adjust().draw();
    if(target === '#tab-operasi') tableOperasi.columns.adjust().draw();
  });

  // Initialize DataTables
  const formatCurrency = (value) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR' }).format(value);
  };

  const tableConfig = {
    processing: true,
    serverSide: false, // Using client side since it's just a lookup
    pageLength: 25,
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json',
    },
    columnDefs: [
      {
        targets: 2,
        render: function(data, type, row) {
          if (type === 'display' || type === 'filter') {
            return formatCurrency(data);
          }
          return data;
        },
        className: 'text-right'
      }
    ]
  };

  const tableRalan = $('#tableRalan').DataTable({
    ...tableConfig,
    ajax: {
      url: window.BASE_URL + '/api/get_data_perbaikan_tarif.php?type=ralan',
      dataSrc: 'data'
    },
    columns: [
      { data: 'kd_jenis_prw' },
      { data: 'nm_perawatan' },
      { data: 'total_byrdrpr' },
      { data: 'action', orderable: false, className: 'text-center' }
    ]
  });

  const tableRanap = $('#tableRanap').DataTable({
    ...tableConfig,
    ajax: {
      url: window.BASE_URL + '/api/get_data_perbaikan_tarif.php?type=ranap',
      dataSrc: 'data'
    },
    columns: [
      { data: 'kd_jenis_prw' },
      { data: 'nm_perawatan' },
      { data: 'total_byrdrpr' },
      { data: 'action', orderable: false, className: 'text-center' }
    ]
  });

  const tableLab = $('#tableLab').DataTable({
    ...tableConfig,
    ajax: {
      url: window.BASE_URL + '/api/get_data_perbaikan_tarif.php?type=lab',
      dataSrc: 'data'
    },
    columns: [
      { data: 'kd_jenis_prw' },
      { data: 'nm_perawatan' },
      { data: 'total_byrdrpr' },
      { data: 'action', orderable: false, className: 'text-center' }
    ]
  });

  const tableRadiologi = $('#tableRadiologi').DataTable({
    ...tableConfig,
    ajax: {
      url: window.BASE_URL + '/api/get_data_perbaikan_tarif.php?type=radiologi',
      dataSrc: 'data'
    },
    columns: [
      { data: 'kd_jenis_prw' },
      { data: 'nm_perawatan' },
      { data: 'total_byrdrpr' },
      { data: 'action', orderable: false, className: 'text-center' }
    ]
  });

  const tableOperasi = $('#tableOperasi').DataTable({
    ...tableConfig,
    ajax: {
      url: window.BASE_URL + '/api/get_data_perbaikan_tarif.php?type=operasi',
      dataSrc: 'data'
    },
    columns: [
      { data: 'kd_jenis_prw' },
      { data: 'nm_perawatan' },
      { data: 'total_byrdrpr' },
      { data: 'action', orderable: false, className: 'text-center' }
    ]
  });

  // =============================================
  // MODAL PERBAIKI TARIF
  // =============================================
  let _modalKd   = '';
  let _modalType = '';
  let _modalNm   = '';
  let _modalCols = 14; // jumlah kolom aktif (termasuk checkbox)

  const fmt = (v) => new Intl.NumberFormat('id-ID').format(v);

  function renderPreviewHeader(type) {
    let thead = '';
    if (type === 'ralan' || type === 'ranap') {
      _modalCols = 14;
      thead = '<tr>'
        + '<th rowspan="2"><input type="checkbox" id="chkAll" title="Pilih semua"></th>'
        + '<th rowspan="2">No Rawat</th><th rowspan="2">Tgl Perawatan</th><th rowspan="2">Kode Dokter</th>'
        + '<th colspan="2" style="text-align:center">Biaya Rawat (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Material (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Menejemen (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Tindakan Dr (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Tindakan Pr (Rp)</th>'
        + '</tr><tr>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '</tr>';
    } else if (type === 'lab') {
      _modalCols = 20;
      thead = '<tr>'
        + '<th rowspan="2"><input type="checkbox" id="chkAll" title="Pilih semua"></th>'
        + '<th rowspan="2">No Rawat</th><th rowspan="2">Tgl Periksa</th><th rowspan="2">Kode Dokter</th>'
        + '<th colspan="2" style="text-align:center">Biaya (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Bagian RS (Rp)</th>'
        + '<th colspan="2" style="text-align:center">BHP (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Perujuk (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Tindakan Dr (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Tindakan Pt (Rp)</th>'
        + '<th colspan="2" style="text-align:center">KSO (Rp)</th>'
        + '<th colspan="2" style="text-align:center">Menejemen (Rp)</th>'
        + '</tr><tr>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '<th style="text-align:right">Lama</th><th style="text-align:right">Baru</th>'
        + '</tr>';
    } else if (type === 'operasi') {
      _modalCols = 6;
      thead = '<tr>'
        + '<th><input type="checkbox" id="chkAll" title="Pilih semua"></th>'
        + '<th>No Rawat</th><th>Tgl Operasi</th><th>Kode Paket</th>'
        + '<th style="text-align:right">Total Lama (Rp)</th>'
        + '<th style="text-align:right">Total Baru (Rp)</th>'
        + '<th style="text-align:right">Selisih (Rp)</th>'
        + '</tr>';
      _modalCols = 7;
    }
    $('#previewTable thead').html(thead);
  }

  function openModal(kd, type, nm) {
    _modalKd   = kd;
    _modalType = type;
    _modalNm   = nm;
    $('#modalTitle').text('Perbaiki Tarif \u2014 ' + nm + ' (' + kd + ')');
    renderPreviewHeader(type);
    $('#previewBody').html('<tr><td colspan="' + _modalCols + '" style="text-align:center;color:#9ca3af;padding:20px">Pilih range tanggal lalu klik Cari Data</td></tr>');
    $('#previewInfo').hide();
    $('#btnEksekusi').hide();
    $('#alertSuccess').hide();
    $('#alertError').hide();
    $('#searchBarWrap').hide();
    $('#inputSearchNoRawat').val('');
    $('#modalPerbaiki').addClass('show');
  }

  function updateSelectedInfo() {
    const total   = $('#previewBody tr[data-no]').length;
    const checked = $('#previewBody input.row-cb:checked').length;
    if (total === 0) return;
    $('#previewInfo').html(
      '<strong>' + total + ' data</strong> ditemukan &mdash; '
      + '<strong id="selectedCount">' + checked + '</strong> dipilih. '
      + 'Klik <strong>Perbaiki Tarif</strong> untuk memperbarui <code>biaya_rawat</code> data yang dicentang.'
    ).show();
    if (checked > 0) $('#btnEksekusi').show(); else $('#btnEksekusi').hide();
  }

  function closeModal() {
    $('#modalPerbaiki').removeClass('show');
  }

  $('#btnCloseModal, #btnBatal').click(closeModal);
  $('#modalPerbaiki').click(function(e) {
    if ($(e.target).is('#modalPerbaiki')) closeModal();
  });

  // Delegasi event klik tombol perbaiki (ralan, ranap, lab, operasi)
  $(document).on('click', '.btn-perbaiki', function() {
    const type = $(this).data('type');
    if (!['ralan','ranap','lab','operasi'].includes(type)) return;
    const kd = $(this).data('kd');
    const nm = $(this).closest('tr').find('td').eq(1).text();
    openModal(kd, type, nm);
  });

  // Cari preview data
  $('#btnCari').click(function() {
    const tglMulai = $('#inputTglMulai').val();
    const tglAkhir = $('#inputTglAkhir').val();
    if (!tglMulai || !tglAkhir) { alert('Isi range tanggal terlebih dahulu.'); return; }
    if (tglMulai > tglAkhir) { alert('Tanggal mulai tidak boleh lebih dari tanggal akhir.'); return; }

    $('#btnCari').text('Memuat...').prop('disabled', true);
    $('#btnEksekusi').hide();
    $('#alertSuccess').hide();
    $('#alertError').hide();
    $('#previewInfo').hide();
    $('#searchBarWrap').hide();
    $('#inputSearchNoRawat').val('');
    $('#chkAll').prop('checked', false);

    // Render header tabel sesuai type sebelum AJAX
    renderPreviewHeader(_modalType);

    $.ajax({
      url: window.BASE_URL + '/api/preview_perbaikan_tarif.php',
      method: 'GET',
      data: { type: _modalType, kd_jenis_prw: _modalKd, tgl_mulai: tglMulai, tgl_akhir: tglAkhir },
      success: function(res) {
        if (!res.success) {
          $('#alertError').text('Gagal: ' + res.message).show();
          return;
        }
        if (res.total === 0) {
          $('#previewBody').html('<tr><td colspan="' + _modalCols + '" style="text-align:center;color:#9ca3af;padding:20px">Tidak ada data pada range tanggal tersebut.</td></tr>');
          $('#previewInfo').text('Tidak ada data ditemukan.').show();
          $('#searchBarWrap').hide();
          return;
        }
        let rows = '';
        res.data.forEach(function(d) {
          const selisih = d.selisih;
          const cls = selisih > 0 ? 'selisih-pos' : (selisih < 0 ? 'selisih-neg' : 'selisih-zero');
          const noEsc = d.no_rawat.replace(/"/g, '&quot;');
          rows += '<tr data-no="' + noEsc + '">';
          rows += '<td><input type="checkbox" class="row-cb" value="' + noEsc + '" checked></td>';
          rows += '<td>' + d.no_rawat + '</td>';
          rows += '<td>' + d.tgl_perawatan + '</td>';

          if (d.mode === 'ralan_ranap') {
            rows += '<td>' + d.kd_dokter + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.tarif_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + cls + '">' + fmt(d.tarif_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.material_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.material_baru !== d.material_lama ? cls : '') + '">' + fmt(d.material_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.menejemen_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.menejemen_baru !== d.menejemen_lama ? cls : '') + '">' + fmt(d.menejemen_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.tindakandr_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.tindakandr_baru !== d.tindakandr_lama ? cls : '') + '">' + fmt(d.tindakandr_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.tindakanpr_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.tindakanpr_baru !== d.tindakanpr_lama ? cls : '') + '">' + fmt(d.tindakanpr_baru) + '</td>';
          } else if (d.mode === 'lab') {
            rows += '<td>' + d.kd_dokter + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.tarif_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + cls + '">' + fmt(d.tarif_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.bagian_rs_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.bagian_rs_baru !== d.bagian_rs_lama ? cls : '') + '">' + fmt(d.bagian_rs_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.bhp_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.bhp_baru !== d.bhp_lama ? cls : '') + '">' + fmt(d.bhp_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.perujuk_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.perujuk_baru !== d.perujuk_lama ? cls : '') + '">' + fmt(d.perujuk_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.tindakan_dr_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.tindakan_dr_baru !== d.tindakan_dr_lama ? cls : '') + '">' + fmt(d.tindakan_dr_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.tindakan_pt_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.tindakan_pt_baru !== d.tindakan_pt_lama ? cls : '') + '">' + fmt(d.tindakan_pt_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.kso_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.kso_baru !== d.kso_lama ? cls : '') + '">' + fmt(d.kso_baru) + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.menejemen_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + (d.menejemen_baru !== d.menejemen_lama ? cls : '') + '">' + fmt(d.menejemen_baru) + '</td>';
          } else if (d.mode === 'operasi') {
            rows += '<td>' + d.kode_paket + '</td>';
            rows += '<td style="text-align:right">' + fmt(d.tarif_lama) + '</td>';
            rows += '<td style="text-align:right" class="' + cls + '">' + fmt(d.tarif_baru) + '</td>';
            rows += '<td style="text-align:right ' + cls + '">' + fmt(d.selisih) + '</td>';
          }

          rows += '</tr>';
        });
        $('#previewBody').html(rows);
        $('#chkAll').prop('checked', true);
        $('#searchBarWrap').show();
        updateSelectedInfo();
      },
      error: function() { $('#alertError').text('Terjadi kesalahan jaringan.').show(); },
      complete: function() { $('#btnCari').text('\uD83D\uDD0D Cari Data').prop('disabled', false); }
    });
  });

  // Select All / Deselect All
  $('#chkAll').on('change', function() {
    const checked = $(this).prop('checked');
    $('#previewBody tr:visible input.row-cb').prop('checked', checked);
    $('#previewBody tr:visible').toggleClass('selected', checked);
    updateSelectedInfo();
  });

  // Per-row checkbox
  $(document).on('change', '.row-cb', function() {
    $(this).closest('tr').toggleClass('selected', $(this).prop('checked'));
    const total   = $('#previewBody tr[data-no]:visible').length;
    const checked = $('#previewBody tr:visible input.row-cb:checked').length;
    $('#chkAll').prop('indeterminate', checked > 0 && checked < total);
    $('#chkAll').prop('checked', checked === total && total > 0);
    updateSelectedInfo();
  });

  // Live search no rawat
  $('#inputSearchNoRawat').on('input', function() {
    const q = $(this).val().toLowerCase().trim();
    $('#previewBody tr[data-no]').each(function() {
      const no = $(this).data('no').toLowerCase();
      if (!q || no.includes(q)) $(this).show(); else $(this).hide();
    });
    // Sync chkAll state
    const total   = $('#previewBody tr[data-no]:visible').length;
    const checked = $('#previewBody tr[data-no]:visible input.row-cb:checked').length;
    if (total === 0) { $('#chkAll').prop('indeterminate', false).prop('checked', false); return; }
    $('#chkAll').prop('indeterminate', checked > 0 && checked < total);
    $('#chkAll').prop('checked', checked === total);
  });

  // Eksekusi perbaikan (hanya no_rawat yang dicentang)
  $('#btnEksekusi').click(function() {
    const selectedNos = [];
    $('#previewBody input.row-cb:checked').each(function() {
      selectedNos.push($(this).val());
    });
    if (selectedNos.length === 0) { alert('Pilih minimal 1 data untuk diperbaiki.'); return; }
    if (!confirm('Yakin ingin memperbaiki tarif ' + selectedNos.length + ' data untuk ' + _modalNm + '?\nAksi ini tidak dapat dibatalkan.')) return;

    $('#btnEksekusi').text('Memproses...').prop('disabled', true);
    $('#alertSuccess').hide();
    $('#alertError').hide();

    $.ajax({
      url: window.BASE_URL + '/api/eksekusi_perbaikan_tarif.php',
      method: 'POST',
      data: { type: _modalType, kd_jenis_prw: _modalKd, no_rawat: selectedNos },
      success: function(res) {
        if (res.success) {
          $('#alertSuccess').text('\u2713 ' + res.message).show();
          $('#btnEksekusi').hide();
          $('#previewBody').html('<tr><td colspan="' + _modalCols + '" style="text-align:center;color:#16a34a;padding:20px;font-weight:600">Tarif berhasil diperbarui (' + res.affected_rows + ' data).</td></tr>');
          $('#previewInfo').hide();
          $('#searchBarWrap').hide();
        } else {
          $('#alertError').text('Gagal: ' + res.message).show();
        }
      },
      error: function() { $('#alertError').text('Terjadi kesalahan jaringan.').show(); },
      complete: function() { $('#btnEksekusi').text('\u26A0 Perbaiki Tarif').prop('disabled', false); }
    });
  });

  // =============================================
  // MODAL EDIT TARIF MASTER
  // =============================================
  function closeEditModal() {
    $('#modalEditTarifMaster').removeClass('show');
  }
  $('#btnCloseEditModal, #btnBatalEdit').click(closeEditModal);
  
  $(document).on('click', '.btn-edit-tarif', function() {
    const type = $(this).data('type');
    const kd = $(this).data('kd');
    
    $('#editType').val(type);
    $('#editKd').val(kd);
    $('#alertEditSuccess, #alertEditError').hide();
    $('#editFormFields').html('<div style="grid-column: 1 / -1; text-align: center;">Memuat data...</div>');
    $('#modalEditTarifMaster').addClass('show');

    $.ajax({
      url: window.BASE_URL + '/api/get_detail_tarif.php',
      method: 'GET',
      data: { type: type, kd: kd },
      success: function(res) {
        if(res.success) {
          const data = res.data;
          $('#modalEditTitle').text('Edit Tarif - ' + (data.nm_perawatan || kd));
          
          let html = '';
          const excludeFields = ['kd_jenis_prw', 'kode_paket', 'nm_perawatan', 'kd_kategori', 'kd_pj', 'kd_poli', 'kd_bangsal', 'status', 'kelas', 'kategori'];
          
          for(const key in data) {
            if(!excludeFields.includes(key)) {
              let label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
              let val = data[key];
              html += `
                <div>
                  <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px; color:#374151;">${label}</label>
                  <input type="text" name="${key}" value="${val}" style="width:100%; padding:8px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;">
                </div>
              `;
            }
          }
          $('#editFormFields').html(html);
        } else {
          $('#editFormFields').html('<div style="grid-column: 1 / -1; text-align: center; color: red;">' + res.message + '</div>');
        }
      },
      error: function() {
        $('#editFormFields').html('<div style="grid-column: 1 / -1; text-align: center; color: red;">Terjadi kesalahan jaringan.</div>');
      }
    });
  });

  $('#btnSimpanEdit').click(function(e) {
    e.preventDefault();
    const btn = $(this);
    btn.text('Menyimpan...').prop('disabled', true);
    $('#alertEditSuccess, #alertEditError').hide();

    $.ajax({
      url: window.BASE_URL + '/api/save_detail_tarif.php',
      method: 'POST',
      data: $('#formEditTarif').serialize(),
      success: function(res) {
        if(res.success) {
          $('#alertEditSuccess').text('\u2713 ' + res.message).show();
          // Reload datatables if initialized
          if(typeof tableRalan !== 'undefined') tableRalan.ajax.reload(null, false);
          if(typeof tableRanap !== 'undefined') tableRanap.ajax.reload(null, false);
          if(typeof tableLab !== 'undefined') tableLab.ajax.reload(null, false);
          if(typeof tableRadiologi !== 'undefined') tableRadiologi.ajax.reload(null, false);
          if(typeof tableOperasi !== 'undefined') tableOperasi.ajax.reload(null, false);
          
          setTimeout(closeEditModal, 1500);
        } else {
          $('#alertEditError').text('Gagal: ' + res.message).show();
        }
      },
      error: function() {
        $('#alertEditError').text('Terjadi kesalahan jaringan.').show();
      },
      complete: function() {
        btn.html('\uD83D\uDCBE Simpan Perubahan').prop('disabled', false);
      }
    });
  });

});
</script>

<?php require_once '../layouts/footer.php'; ?>
