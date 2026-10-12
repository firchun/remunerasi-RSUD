<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$query_bangsal = "SELECT * FROM bangsal WHERE status = '1' ORDER BY nm_bangsal";
$result_bangsal = mysqli_query($koneksi, $query_bangsal);

$pageTitle = 'Kepatuhan Rawat Inap - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<style>
  table.dataTable td { color: #1f2937 !important; } table.dataTable thead th { color: #ffffff !important; }
  #tabelKepatuhan th, #tabelKepatuhan td { white-space: nowrap; }
  #tabelKepatuhan tbody td { padding: 2px 4px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: top !important; }
  table.dataTable { width: auto !important; }
  .dt-buttons { margin-bottom: 10px; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i
      class="fas fa-bed mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter Pencarian
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bulan (Tgl Masuk)</label>
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
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bangsal / Kamar</label>
      <select id="kd_bangsal"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="">Semua Bangsal</option>
        <?php while ($row = mysqli_fetch_assoc($result_bangsal)): ?>
          <option value="<?= $row['kd_bangsal'] ?>"><?= $row['nm_bangsal'] ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Status Pulang</label>
      <select id="status_pulang"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="semua" selected>Semua</option>
        <option value="belum_pulang">Belum Pulang</option>
        <option value="sudah_pulang">Sudah Pulang</option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Pelanggaran</label>
      <select id="jenis_pelanggaran"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="semua" selected>Semua Pelanggaran</option>
        <option value="cara_bayar">Cara Bayar Tidak Sesuai</option>
        <option value="lama_minus">Lama Perawatan Minus</option>
        <option value="tanggal">Tanggal Keluar Sebelum Masuk</option>
        <option value="tanpa_sep">BPJS Tanpa SEP</option>
        <option value="resume">Belum Ada Resume</option>
      </select>
    </div>
  </div>
  <div class="mt-4 flex flex-wrap gap-2">
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-xl transition">
      <i class="fas fa-search mr-2"></i>Cari Data
    </button>
    <button onclick="resetFilter()"
      class="border border-gray-600 text-gray-600 p-2 rounded-xl hover:bg-gray-200 transition">
      <i class="fas fa-redo mr-2"></i>Reset
    </button>
    <button onclick="downloadExcel()"
      class="bg-emerald-700 hover:bg-emerald-800 text-white p-2 rounded-xl transition" id="btnExcel">
      <i class="fas fa-file-excel mr-2"></i>Download Excel (Sheet per Bulan)
    </button>
  </div>
</div>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-3" id="ringkasanPelanggaran">
  <div class="bg-white rounded-2xl border border-green-700 p-3 text-center">
    <div class="text-2xl font-bold text-green-800" id="sum_total">0</div>
    <div class="text-xs text-gray-600">Total Pelanggaran</div>
  </div>
  <div class="bg-white rounded-2xl border border-red-400 p-3 text-center">
    <div class="text-2xl font-bold text-red-600" id="sum_cara_bayar">0</div>
    <div class="text-xs text-gray-600">Cara Bayar Tidak Sesuai</div>
  </div>
  <div class="bg-white rounded-2xl border border-red-400 p-3 text-center">
    <div class="text-2xl font-bold text-red-600" id="sum_lama_minus">0</div>
    <div class="text-xs text-gray-600">Lama Perawatan Minus</div>
  </div>
  <div class="bg-white rounded-2xl border border-red-400 p-3 text-center">
    <div class="text-2xl font-bold text-red-600" id="sum_tanggal">0</div>
    <div class="text-xs text-gray-600">Tgl Keluar &lt; Masuk</div>
  </div>
  <div class="bg-white rounded-2xl border border-red-400 p-3 text-center">
    <div class="text-2xl font-bold text-red-600" id="sum_tanpa_sep">0</div>
    <div class="text-xs text-gray-600">BPJS Tanpa SEP</div>
  </div>
  <div class="bg-white rounded-2xl border border-red-400 p-3 text-center">
    <div class="text-2xl font-bold text-red-600" id="sum_resume">0</div>
    <div class="text-xs text-gray-600">Belum Ada Resume</div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mb-3">
  <div class="bg-white rounded-2xl border border-green-700 p-3">
    <h3 class="text-sm font-semibold text-green-800 mb-2">
      <i class="fas fa-chart-bar mr-2"></i>Pelanggaran per Jenis
      <span class="text-gray-400 font-normal text-xs">(bulan & tahun terpilih)</span>
    </h3>
    <canvas id="chartJenis" height="220"></canvas>
  </div>
  <div class="bg-white rounded-2xl border border-green-700 p-3">
    <h3 class="text-sm font-semibold text-green-800 mb-2">
      <i class="fas fa-chart-column mr-2"></i>Tren Pelanggaran per Bulan
      <span class="text-gray-400 font-normal text-xs" id="labelTrenTahun"></span>
    </h3>
    <canvas id="chartBulanan" height="220"></canvas>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <div class="overflow-x-auto">
    <table id="tabelKepatuhan" class="display w-full">
      <thead class="bg-green-800 text-white">
        <tr>
          <th class="px-2 text-left">No</th>
          <th class="px-2 text-left">No.Rawat</th>
          <th class="px-2 text-left">No.RM</th>
          <th class="px-2 text-left">Nama Pasien</th>
          <th class="px-2 text-left">Cara Bayar</th>
          <th class="px-2 text-left">No.SEP</th>
          <th class="px-2 text-left">Bangsal / Kamar</th>
          <th class="px-2 text-left" style="background:#1d4ed8;color:#fff">Tanggal Masuk</th>
          <th class="px-2 text-left" style="background:#b45309;color:#fff">Tanggal Keluar</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Lama Perawatan (Hari)</th>
          <th class="px-2 text-left">Status Pulang</th>
          <th class="px-2 text-left" style="background:#991b1b;color:#fff">Jenis Pelanggaran</th>
          <th class="px-2 text-left">Keterangan</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot class="bg-green-800 font-bold text-white">
        <tr>
          <th colspan="10" class="text-right px-2">TOTAL AKHIR :</th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2" id="foot_jenis"></th>
          <th class="text-right px-2" id="foot_ket"></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
  let table;
  let chartJenis = null;
  let chartBulanan = null;

  const JENIS_KEYS = ['cara_bayar', 'lama_minus', 'tanggal', 'tanpa_sep', 'resume'];
  const JENIS_LABELS = ['Cara Bayar Tidak Sesuai', 'Lama Perawatan Minus', 'Tanggal Keluar Sebelum Masuk', 'BPJS Tanpa SEP', 'Belum Ada Resume'];
  const JENIS_COLORS = ['#dc2626', '#f97316', '#eab308', '#7c3aed', '#059669'];
  const BULAN_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  const tableConfig = {
    processing: true,
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    dom: '<"flex justify-between items-center mb-4"lB>rtip',
    buttons: [{
      extend: 'pdfHtml5',
      text: 'Export PDF',
      filename: function () {
        let bulan = $('#filter_bulan option:selected').text();
        let tahun = $('#filter_tahun option:selected').text();
        return 'Kepatuhan_Ranap_' + bulan + '_' + tahun;
      },
      orientation: 'landscape',
      pageSize: 'A4',
      title: function () {
        let bulan = $('#filter_bulan option:selected').text();
        let tahun = $('#filter_tahun option:selected').text();
        return 'Laporan Kepatuhan Rawat Inap - ' + bulan + ' ' + tahun;
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
    autoWidth: false,
    columns: [
      { data: null, className: 'text-center', render: function (data, type, row, meta) { return meta.row + 1; } },
      { data: 'no_rawat', className: 'font-semibold' },
      { data: 'no_rkm_medis' },
      { data: 'nm_pasien' },
      { data: 'png_jawab' },
      {
        data: 'no_sep',
        render: function (v) {
          if (!v || v === '-') return '<span class="text-red-500 font-semibold">-</span>';
          return v;
        }
      },
      { data: 'ruang' },
      {
        data: 'tgl_masuk',
        render: function (v, t, row) {
          if (!v) return '-';
          return v + (row.jam_masuk && row.jam_masuk !== '00:00:00' ? ' ' + row.jam_masuk : '');
        }
      },
      {
        data: 'tgl_keluar',
        render: function (v, t, row) {
          if (!v) return '<span class="text-orange-500 font-semibold">Masih Dirawat</span>';
          return v + (row.jam_keluar && row.jam_keluar !== '00:00:00' ? ' ' + row.jam_keluar : '');
        }
      },
      {
        data: 'lama',
        className: 'num text-right font-bold',
        render: function (v) { return (v || 0) + ' hari'; }
      },
      {
        data: 'stts_pulang',
        render: function (v) {
          if (v == null || v === '' || v === '-') return '<span class="text-orange-500">Belum Pulang</span>';
          return v;
        }
      },
      {
        data: 'jenis_pelanggaran',
        render: function (v) {
          if (!v) return '-';
          return '<span class="inline-block px-2 py-0.5 text-xs rounded-lg border bg-red-100 text-red-700 border-red-400 font-semibold">' + v + '</span>';
        }
      },
      { data: 'keterangan', className: 'text-xs text-gray-600' },
    ],
    order: [
      [7, 'desc']
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
      const uniq = {};
      data.forEach(r => {
        const l = parseFloat(r.lama) || 0;
        if (!(r.no_rawat in uniq)) uniq[r.no_rawat] = l;
      });
      const sumLama = Object.values(uniq).reduce((a, b) => a + b, 0);
      $(api.column(9).footer()).html(Math.round(sumLama) + ' hari');
      $(api.column(11).footer()).html(data.length + ' pelanggaran');
      $(api.column(12).footer()).html('');
    }
  };

  $(document).ready(function () {
    loadData();
  });

  function loadData() {
    if (table) table.destroy();
    table = $('#tabelKepatuhan').DataTable({
      ...tableConfig,
      serverSide: true,
      ajax: {
        url: window.BASE_URL + '/api/get_data_kepatuhan_ranap.php',
        type: 'POST',
        dataSrc: function (json) {
          renderSummary(json.summary);
          renderCharts(json.summary, json.bulanan);
          return json.data || json;
        },
        data: function (d) {
          d.bulan = $('#filter_bulan').val();
          d.tahun = $('#filter_tahun').val();
          d.kd_bangsal = $('#kd_bangsal').val();
          d.status_pulang = $('#status_pulang').val();
          d.jenis_pelanggaran = $('#jenis_pelanggaran').val();
          d.search_value = d.search.value;
        }
      }
    });
  }

  function renderSummary(s) {
    if (!s) return;
    $('#sum_total').text(s.total || 0);
    $('#sum_cara_bayar').text(s.cara_bayar || 0);
    $('#sum_lama_minus').text(s.lama_minus || 0);
    $('#sum_tanggal').text(s.tanggal || 0);
    $('#sum_tanpa_sep').text(s.tanpa_sep || 0);
    $('#sum_resume').text(s.resume || 0);
  }

  function renderCharts(summary, bulanan) {
    if (typeof Chart === 'undefined') return;
    summary = summary || {};
    bulanan = bulanan || {};

    $('#labelTrenTahun').text('(tahun ' + $('#filter_tahun').val() + ', di luar filter bulan)');

    const jenisData = JENIS_KEYS.map(k => summary[k] || 0);

    if (chartJenis) chartJenis.destroy();
    chartJenis = new Chart(document.getElementById('chartJenis'), {
      type: 'bar',
      data: {
        labels: JENIS_LABELS,
        datasets: [{
          label: 'Jumlah Pelanggaran',
          data: jenisData,
          backgroundColor: JENIS_COLORS,
          borderRadius: 4
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });

    const datasets = JENIS_KEYS.map((k, i) => ({
      label: JENIS_LABELS[i],
      data: Array.from({ length: 12 }, (_, idx) => (bulanan[idx + 1] && bulanan[idx + 1][k]) || 0),
      backgroundColor: JENIS_COLORS[i],
      borderRadius: 3
    }));

    if (chartBulanan) chartBulanan.destroy();
    chartBulanan = new Chart(document.getElementById('chartBulanan'), {
      type: 'bar',
      data: { labels: BULAN_LABELS, datasets: datasets },
      options: {
        responsive: true,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 10 } } }
        },
        scales: {
          x: { stacked: true },
          y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  }

  function downloadExcel() {
    const params = new URLSearchParams({
      tahun: $('#filter_tahun').val(),
      kd_bangsal: $('#kd_bangsal').val(),
      status_pulang: $('#status_pulang').val(),
      jenis_pelanggaran: $('#jenis_pelanggaran').val()
    });
    window.open(window.BASE_URL + '/api/export_kepatuhan_ranap.php?' + params.toString(), '_blank');
  }

  function resetFilter() {
    const now = new Date();
    $('#filter_bulan').val(String(now.getMonth() + 1));
    $('#filter_tahun').val(String(now.getFullYear()));
    $('#kd_bangsal').val('');
    $('#status_pulang').val('semua');
    $('#jenis_pelanggaran').val('semua');
    loadData();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>
