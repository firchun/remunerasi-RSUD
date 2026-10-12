<?php


$pageTitle = 'Rekap Kantong Darah - RSUD MERAUKE';

$tabs = [
  ['jenis' => 'ranap', 'label' => 'Rawat Inap', 'icon' => 'fa-bed'],
  ['jenis' => 'ralan', 'label' => 'Rawat Jalan', 'icon' => 'fa-user-doctor'],
];
?>
<?php
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<style>
.tab-btn { padding: 10px 20px; font-weight: 600; font-size: 14px; border-radius: 10px 10px 0 0; transition: all 0.2s; cursor: pointer; border: 1px solid transparent; border-bottom: none; }
.tab-btn.active { background: white; color: #166534; border-color: #166534; }
.tab-btn:not(.active) { background: #e5e7eb; color: #6b7280; }
.tab-btn:not(.active):hover { background: #d1d5db; }
#tabelKantong tbody td { padding: 6px 8px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: middle !important; font-size: 14px !important; }
#tabelKantong thead th { background-color: #166534 !important; color: white !important; border: 1px solid #065f46 !important; padding: 8px 4px !important; font-weight: bold !important; font-size: 12px !important; white-space: nowrap; }
#tabelDetailKantong thead th { background-color: #166534 !important; color: white !important; border: 1px solid #065f46 !important; padding: 6px 6px !important; font-weight: bold !important; font-size: 12px !important; white-space: nowrap; }
#tabelDetailKantong tbody td { padding: 4px 6px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: middle !important; font-size: 13px !important; }
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
      <select id="tahun"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <?php
        $currentYear = (int)date('Y');
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
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian</label>
      <input type="text" id="tcari" placeholder="Cari No. Rawat, No. RM, Nama Pasien..."
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
  <div class="flex flex-wrap justify-between items-center mb-4 gap-2">
    <div>
      <h3 id="periodeInfo" class="text-lg font-bold text-green-800">Periode: -</h3>
      <p id="summaryInfo" class="text-sm text-gray-500 mt-1">-</p>
    </div>
    <button onclick="exportExcel()"
      class="bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-xl transition text-sm font-semibold">
      <i class="fas fa-file-excel mr-2"></i>Export Excel (Per Bulan)
    </button>
  </div>

  <div class="overflow-x-auto">
    <table id="tabelKantong" class="display w-full" style="width:100%">
      <thead>
        <tr>
          <th>No.Rawat</th>
          <th>No.RM</th>
          <th>Nama Pasien</th>
          <th>Tgl Registrasi</th>
          <th>No. SEP</th>
          <th>Poli/Kamar</th>
          <th class="text-right">Jumlah Kantong</th>
          <th class="text-center">Aksi</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div id="modalDetail"
  class="hidden fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 px-3">
  <div class="bg-white w-full max-w-4xl rounded-xl p-6 relative border border-green-700"
    style="max-height:90vh; overflow-y:auto;">
    <button onclick="closeModal()"
      class="absolute -top-3 -right-3 bg-red-500 text-white w-8 h-8 flex items-center justify-center rounded-full shadow hover:bg-red-600 transition text-xl">
      ✕
    </button>
    <h2 class="text-xl font-bold mb-4 border-b pb-2 flex items-center">
      <i class="fas fa-droplet text-red-500 mr-2"></i>
      Detail Kantong Darah
    </h2>

    <div id="infoPasien" class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4"></div>

    <div class="overflow-x-auto">
      <table id="tabelDetailKantong" class="display w-full">
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Jam</th>
            <th>Nama Perawatan / Produk Darah</th>
            <th>Dokter</th>
            <th>Petugas</th>
            <th class="text-right">Tarif</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

<script>
  var jenis = 'ranap';
  var table;

  function parseResponse(response) {
    return typeof response === 'object' ? response : JSON.parse(response);
  }

  function renderTable(data) {
    if ($.fn.DataTable.isDataTable('#tabelKantong')) {
      $('#tabelKantong').DataTable().clear().destroy();
      $('#tabelKantong').empty();
    }

    table = $('#tabelKantong').DataTable({
      dom: '<"flex justify-between items-center mb-4"l>rtip',
      lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Semua"]],
      pageLength: 25,
      autoWidth: false,
      order: [[6, 'desc']],
      language: {
        lengthMenu: "Tampilkan _MENU_ data per halaman",
        zeroRecords: "Data tidak ditemukan",
        info: "Menampilkan halaman _PAGE_ dari _PAGES_ (total _MAX_ data)",
        search: "Cari:",
        paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
      },
      columns: [
        { data: 'no_rawat' },
        { data: 'no_rkm_medis' },
        { data: 'nm_pasien', className: 'font-medium' },
        {
          data: 'tgl_registrasi',
          className: 'text-center',
          render: function (data) {
            if (!data) return '-';
            var p = String(data).slice(0, 10).split('-');
            return p.length === 3 ? p[2] + '-' + p[1] + '-' + p[0] : data;
          }
        },
        {
          data: 'no_sep',
          render: function (data) { return data || '-'; }
        },
        {
          data: 'lokasi',
          render: function (data) { return data || '-'; }
        },
        {
          data: 'jumlah_kantong',
          className: 'text-right font-bold',
          render: function (data, type) {
            if (type === 'display') return Number(data).toLocaleString('id-ID');
            return data;
          }
        },
        {
          data: null,
          className: 'text-center',
          orderable: false,
          render: function (data, type, row) {
            return '<button onclick="openDetail(\'' + row.no_rawat + '\')" ' +
              'class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition">' +
              '<i class="fas fa-eye mr-1"></i>Detail</button>';
          }
        }
      ],
      data: data
    });

    if (table.search()) table.search($('#tcari').val()).draw();
  }

  function switchTab(newJenis, btn) {
    if (newJenis === jenis) return;
    jenis = newJenis;
    document.querySelectorAll('.tab-btn').forEach(function (b) { b.classList.remove('active'); });
    btn.classList.add('active');
    loadData();
  }

  function loadData() {
    var bulan = $('#bulan').val();
    var tahun = $('#tahun').val();

    $.ajax({
      url: window.BASE_URL + '/api/get_kantong_darah.php',
      type: 'POST',
      dataType: 'json',
      data: { jenis: jenis, bulan: bulan, tahun: tahun },
      success: function (response) {
        var res = parseResponse(response);
        if (res.success) {
          $('#periodeInfo').text('Periode: ' + res.periode + ' (' + (jenis === 'ranap' ? 'Rawat Inap' : 'Rawat Jalan') + ')');
          $('#summaryInfo').text('Total Pasien: ' + res.total_pasien.toLocaleString('id-ID') +
            ' | Total Kantong: ' + res.total_kantong.toLocaleString('id-ID'));
          renderTable(res.data);
        } else {
          alert('Gagal memuat data: ' + res.message);
        }
      },
      error: function (xhr) {
        alert('Error loading data: ' + xhr.statusText);
        console.error(xhr.responseText);
      }
    });
  }

  function openDetail(no_rawat) {
    $.ajax({
      url: window.BASE_URL + '/api/get_detail_kantong_darah.php',
      type: 'GET',
      dataType: 'json',
      data: { jenis: jenis, no_rawat: no_rawat },
      success: function (response) {
        var res = parseResponse(response);
        if (!res.success) {
          alert('Gagal memuat detail: ' + res.message);
          return;
        }
        renderInfoPasien(res.info);
        renderDetailRows(res.rows, res.summary);
        $('#modalDetail').removeClass('hidden');
      },
      error: function (xhr) {
        alert('Error loading detail: ' + xhr.statusText);
        console.error(xhr.responseText);
      }
    });
  }

  function renderInfoPasien(info) {
    $('#infoPasien').html(
      '<div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">No. Rawat</p><p class="font-bold">' + info.no_rawat + '</p></div>' +
      '<div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">No. RM</p><p class="font-bold">' + info.no_rkm_medis + '</p></div>' +
      '<div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Nama Pasien</p><p class="font-bold">' + info.nm_pasien + '</p></div>' +
      '<div class="bg-gray-50 rounded-lg p-3"><p class="text-xs text-gray-500">Poli/Kamar</p><p class="font-bold">' + info.lokasi + '</p></div>'
    );
  }

  function renderDetailRows(rows, summary) {
    var tbody = document.querySelector('#tabelDetailKantong tbody');
    tbody.innerHTML = '';
    if (!rows || rows.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="text-center text-gray-500 py-4">Tidak ada data kantong</td></tr>';
      return;
    }
    var fmt = new Intl.NumberFormat('id-ID');
    rows.forEach(function (row, index) {
      var tr = document.createElement('tr');
      tr.innerHTML =
        '<td class="text-center">' + (index + 1) + '</td>' +
        '<td>' + row.tgl_perawatan + '</td>' +
        '<td>' + row.jam_rawat + '</td>' +
        '<td>' + row.nm_perawatan + '</td>' +
        '<td>' + (row.nm_dokter || '-') + '</td>' +
        '<td>' + (row.nm_petugas || '-') + '</td>' +
        '<td class="text-right">Rp ' + fmt.format(row.biaya) + '</td>';
      tbody.appendChild(tr);
    });
    var total = document.createElement('tr');
    total.style.backgroundColor = '#f0fdf4';
    total.innerHTML =
      '<td colspan="3" class="text-right font-bold">Jumlah Kantong</td>' +
      '<td class="font-bold">' + summary.jumlah_kantong + ' kantong</td>' +
      '<td colspan="2" class="text-right font-bold">Total Tarif</td>' +
      '<td class="text-right font-bold">Rp ' + fmt.format(summary.total_biaya) + '</td>';
    tbody.appendChild(total);
  }

  function closeModal() {
    $('#modalDetail').addClass('hidden');
  }

  function exportExcel() {
    var bulan = $('#bulan').val();
    var tahun = $('#tahun').val();
    var url = window.BASE_URL + '/api/export_kantong_darah.php?bulan=' + bulan + '&tahun=' + tahun;
    window.location.href = url;
  }

  function resetFilter() {
    $('#tahun').val(new Date().getFullYear());
    $('#bulan').val('');
    $('#tcari').val('');
    loadData();
  }

  $('#tcari').on('keyup', function () {
    if (table) table.search($(this).val()).draw();
  });

  $(document).ready(function () {
    $('#tahun').val(new Date().getFullYear());
    $('#bulan').val('');
    loadData();
  });
</script>
<?php require_once '../layouts/footer.php'; ?>