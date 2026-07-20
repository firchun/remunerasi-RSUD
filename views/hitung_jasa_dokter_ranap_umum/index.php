<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$query_dokter = "SELECT * FROM dokter where status = '1' ORDER BY nm_dokter";
$result_dokter = mysqli_query($koneksi, $query_dokter);

$query_pj = "SELECT * FROM penjab where status = '1' ORDER BY kd_pj";
$result_pj = mysqli_query($koneksi, $query_pj);

$query_bangsal = "SELECT * FROM bangsal ORDER BY nm_bangsal";
$result_bangsal = mysqli_query($koneksi, $query_bangsal);

$pageTitle = 'Hitung Jasa Dokter Rawat Inap - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  table.dataTable td { color: #1f2937 !important; } table.dataTable thead th { color: #ffffff !important; }
  #tabelJasa th, #tabelJasa td, #tabelRekap th, #tabelRekap td { white-space: nowrap; }
  #tabelJasa tbody td, #tabelRekap tbody td { padding: 2px 4px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: top !important; text-align: left !important; }
  #tabelRekap tbody tr { cursor: pointer; }
  #tabelRekap tbody tr:hover { background-color: #f0fdf4 !important; }
  #modalDetail table th, #modalDetail table td { white-space: nowrap; padding: 4px 8px; }
  table.dataTable { width: auto !important; }
  .dt-buttons { margin-bottom: 10px; }
  .bg-jasa { background-color: #fefce8; }
  .tab-btn.active { border-bottom: 2px solid #16a34a; color: #16a34a; font-weight: 600; }
  .tab-btn { color: #6b7280; font-weight: 500; border-bottom: 2px solid transparent; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i class="fas fa-calculator mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter Pencarian
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
      <select id="filter_bulan"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="1">Januari</option>
        <option value="2">Februari</option>
        <option value="3">Maret</option>
        <option value="4">April</option>
        <option value="5">Mei</option>
        <option value="6">Juni</option>
        <option value="7">Juli</option>
        <option value="8">Agustus</option>
        <option value="9">September</option>
        <option value="10">Oktober</option>
        <option value="11">November</option>
        <option value="12">Desember</option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
      <select id="filter_tahun"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <?php
        $tahun_sekarang = date('Y');
        for ($y = $tahun_sekarang; $y >= $tahun_sekarang - 5; $y--) {
          echo "<option value='$y'" . ($y == $tahun_sekarang ? " selected" : "") . ">$y</option>";
        }
        ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Dokter</label>
      <select id="kd_dokter"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="">Semua Dokter</option>
        <?php while ($row = mysqli_fetch_assoc($result_dokter)): ?>
          <option value="<?= $row['kd_dokter'] ?>"><?= $row['nm_dokter'] ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Cara Bayar</label>
      <select id="kd_pj" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="">Semua Cara Bayar</option>
        <?php while ($r = mysqli_fetch_assoc($result_pj)): ?>
          <option value="<?= $r['kd_pj'] ?>"><?= $r['png_jawab'] ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bangsal</label>
      <select id="grup_bangsal"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="">Semua Bangsal</option>
        <?php
        $grup_result = mysqli_query($koneksi, "SELECT DISTINCT grup_bangsal FROM v_bangsal_grup ORDER BY grup_bangsal");
        while ($g = mysqli_fetch_assoc($grup_result)):
          ?>
          <option value="<?= $g['grup_bangsal'] ?>"><?= $g['grup_bangsal'] ?></option>
        <?php endwhile; ?>
      </select>
    </div>

  </div>
  <div class="mt-4 flex gap-2">
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition"><i
        class="fas fa-search mr-2"></i>Cari Data</button>
    <button onclick="resetFilter()"
      class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition"><i
        class="fas fa-redo mr-2"></i>Reset</button>
    <button onclick="exportExcelPerDokter()"
      class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl transition"><i
        class="fas fa-file-excel mr-2"></i>Export Excel per Dokter</button>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <!-- Tabs Navigation -->
  <div class="flex border-b border-gray-200 mb-4">
    <button onclick="switchTab('pasien')" id="tab-pasien"
      class="tab-btn active px-4 py-2 hover:text-green-600 transition">Per Pasien</button>
    <button onclick="switchTab('dokter')" id="tab-dokter" class="tab-btn px-4 py-2 hover:text-green-600 transition">Per
      Dokter</button>
  </div>

  <!-- Tab Content: Per Pasien -->
  <div id="content-pasien" class="overflow-x-auto">
    <table id="tabelJasa" class="display w-full">
      <thead class="bg-green-800 text-white">
        <tr>
          <th class="px-2 text-left">No.</th>
          <th class="px-2 text-left">No.Rawat</th>
          <th class="px-2 text-right" style="background:#854d0e;color:#fff">Nominal RS</th>
          <th class="px-2 text-left">No.RM</th>
          <th class="px-2 text-left">Pasien</th>
          <th class="px-2 text-left">Bangsal</th>
          <th class="px-2 text-left">Dokter</th>
          <th class="px-2 text-left">Tgl Masuk</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Dokter</th>
          <th class="px-2 text-right" style="background:#065f46;color:#fff">Jml Jasa Dokter (Rupiah)</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot class="bg-green-800 font-bold text-white">
        <tr>
          <th colspan="4" class="text-right px-2">TOTAL AKHIR :</th>
        </tr>
      </tfoot>
    </table>
  </div>

  <!-- Tab Content: Per Dokter -->
  <div id="content-dokter" class="hidden overflow-x-auto">
    <table id="tabelRekap" class="display w-full">
      <thead class="bg-green-800 text-white">
        <tr>
          <th class="px-2 text-left">No.</th>
          <th class="px-2 text-left">Dokter</th>
          <th class="px-2 text-right">Jml Pasien</th>
          <th class="px-2 text-right">Pasien BPJS</th>
          <th class="px-2 text-right">Non BPJS</th>
          <th class="px-2 text-right">Klaim BPJS</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Dokter (Tarif)</th>
          <th class="px-2 text-right" style="background:#064e3b;color:#fff">%Dokter</th>
          <th class="px-2 text-right" style="background:#065f46;color:#fff">Nominal Jasa (Rupiah)</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot class="bg-green-800 font-bold text-white">
        <tr>
          <th colspan="1" class="text-right px-2">TOTAL AKHIR :</th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>
</div>
</div>

<!-- Modal Detail Slip Gaji -->
<div id="modalDetail" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center p-4"
  style="display:none;">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
    <div class="sticky top-0 bg-green-800 text-white px-6 py-4 rounded-t-2xl flex justify-between items-center">
      <div>
        <h2 class="text-xl font-bold"> JASA DOKTER</h2>
        <p id="slipDokter" class="text-green-200 text-sm"></p>
      </div>
      <button onclick="tutupModal()" class="text-white/80 hover:text-white text-2xl leading-none">&times;</button>
    </div>
    <div id="slipContent" class="p-6">
      <div class="text-center text-gray-500 py-8">Memuat data...</div>
    </div>
  </div>
</div>

<script>
  let tablePasien;
  let tableRekap;

  function switchTab(tabName) {
    $('.tab-btn').removeClass('active');
    $('#tab-' + tabName).addClass('active');

    if (tabName === 'pasien') {
      $('#content-pasien').removeClass('hidden');
      $('#content-dokter').addClass('hidden');
      if (tablePasien) tablePasien.columns.adjust().draw();
    } else {
      $('#content-pasien').addClass('hidden');
      $('#content-dokter').removeClass('hidden');
      if (tableRekap) tableRekap.columns.adjust().draw();
    }
  }

  $(document).ready(function () {
    // Tabel Per Pasien
    tablePasien = $('#tabelJasa').DataTable({
      processing: true,
      serverSide: true,
      scrollY: "500px",
      scrollX: true,
      scrollCollapse: true,
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{
        extend: 'excel',
        text: 'Export Excel',
        filename: 'hitung_jasa_dokter_ranap_per_pasien_umum'
      }
      ],
      lengthMenu: [
        [10, 25, 50, 100, 300, 1000, 5000, 10000],
        [10, 25, 50, 100, 300, 1000, 5000, 10000]
      ],
      pageLength: 25,
      autoWidth: false,
      deferLoading: 0,
      ajax: {
        url: window.BASE_URL + '/api/get_data_hitung_jasa_dokter_ranap_umum.php',
        type: 'POST',
        dataSrc: function (json) {
          return json.data || json;
        },
        data: function (d) {
          d.bulan = $('#filter_bulan').val();
          d.tahun = $('#filter_tahun').val();
          d.kd_dokter = $('#kd_dokter').val();
          d.kd_pj = $('#kd_pj').val();
          d.grup_bangsal = $('#grup_bangsal').val();
          d.tcari = '';
          d.search_value = d.search.value;
        }
      },
      columns: [{
        data: null,
        className: 'text-center',
        render: function (data, type, row, meta) {
          return meta.row + 1;
        }
      },
      {
        data: 'no_rawat',
        className: 'text-blue-600 font-semibold'
      },
      {
        data: 'nominal_rs',
        className: 'num',
        render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
      },
      },
      },
    {
      data: 'no_rkm_medis'
    },
    {
      data: 'nm_pasien'
    },
    {
      data: 'nm_bangsal'
    },
    {
      data: 'nm_dokter'
    },
    {
      data: 'tgl_masuk'
    },
    {
      data: 'total_tindakan_dr',
      className: 'num'
    },
    render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
      }
      ],
    order: [[10, 'desc']],
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
      const sumData = (prop) => data.map(r => parseFloat(r[prop]) || 0).reduce((a, b) => a + b, 0);
      const fmt = (x) => "Rp " + Math.round(x).toLocaleString('id-ID');

      $(api.column(2).footer()).html(fmt(sumData('nominal_rs')));
      $(api.column(3).footer()).html(fmt(sumData('total_bpjs')));
      $(api.column(4).footer()).html(fmt(sumData('kolom_44')));
      $(api.column(10).footer()).html(fmt(sumData('total_tindakan_dr')));
      $(api.column(11).footer()).html(fmt(sumData('jumlah_dpjp')));
    }
    });

  // Tabel Per Dokter
  tableRekap = buatTabelRekap(false);

  });

  function buatTabelRekap(withAjax) {
    var opts = {
      processing: true,
      serverSide: false,
      scrollY: "500px",
      scrollX: true,
      scrollCollapse: true,
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{ extend: 'excel', text: 'Export Excel', filename: 'rekap_jasa_dokter_ranap' }],
      lengthMenu: [[10, 25, 50, 100, 300, -1], [10, 25, 50, 100, 300, "Semua"]],
      pageLength: 50,
      autoWidth: false,
      columns: [
        { data: null, className: 'text-center', render: function (data, type, row, meta) { return meta.row + 1; } },
        { data: 'nm_dokter', className: 'font-semibold' },
        },
  },
  { data: 'total_tindakan_dr', className: 'num' },
        },
  render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } }
      ],
  order: [[1, 'asc']],
    language: {
    processing: "Memuat rekap...",
      lengthMenu: "Tampilkan _MENU_ data",
        zeroRecords: "Data tidak ditemukan",
          info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ dokter",
            infoEmpty: "Tidak ada data tersedia",
              infoFiltered: "(difilter dari _MAX_ total data)",
                search: "Cari Dokter:",
                  paginate: { first: "Pertama", last: "Terakhir", next: "Selanjutnya", previous: "Sebelumnya" }
  },
  footerCallback: function (row, data, start, end, display) {
    const api = this.api();
    const sumData = (prop) => data.map(r => parseFloat(r[prop]) || 0).reduce((a, b) => a + b, 0);
    const fmt = (x) => "Rp " + Math.round(x).toLocaleString('id-ID');
    $(api.column(2).footer()).html(Math.round(sumData('jumlah_pasien')).toLocaleString('id-ID'));
    $(api.column(2).footer()).html(Math.round(sumData('jumlah_pasien_bpjs')).toLocaleString('id-ID'));
    $(api.column(3).footer()).html(Math.round(sumData('jumlah_pasien_non_bpjs')).toLocaleString('id-ID'));
    $(api.column(4).footer()).html(Math.round(sumData('jumlah_pasien_klaim_bpjs')).toLocaleString('id-ID'));
    $(api.column(5).footer()).html(fmt(sumData('total_bpjs')));
    $(api.column(6).footer()).html(fmt(sumData('kolom_44')));
    $(api.column(7).footer()).html(fmt(sumData('total_tindakan_dr')));
    let sum_44 = sumData('kolom_44');
    let sum_jml_dpjp = sumData('jumlah_dpjp');
    let avg_pct = sum_44 > 0 ? ((sum_jml_dpjp / sum_44) * 100).toFixed(2) : 0;
    $(api.column(8).footer()).html(avg_pct + '%');
    $(api.column(9).footer()).html(fmt(sum_jml_dpjp));
  }
    };
  if (withAjax) {
    opts.ajax = {
      url: window.BASE_URL + '/api/get_rekap_hitung_jasa_dokter_ranap_umum.php',
      type: 'POST',
      dataSrc: function (json) { return json.data || json; },
      data: function (d) {
        d.bulan = $('#filter_bulan').val();
        d.tahun = $('#filter_tahun').val();
        d.kd_dokter = $('#kd_dokter').val();
        d.kd_pj = $('#kd_pj').val();
        d.grup_bangsal = $('#grup_bangsal').val();
      }
    };
  }
  return $('#tabelRekap').DataTable(opts);
  }

  function loadData() {
    tablePasien.ajax.reload(null, false);
    if (tableRekap) {
      tableRekap.destroy();
    }
    $('#tabelRekap tbody').empty();
    tableRekap = buatTabelRekap(true);
  }

  function resetFilter() {
    const now = new Date();
    $('#filter_bulan').val(String(now.getMonth() + 1));
    $('#filter_tahun').val(String(now.getFullYear()));
    $('#kd_dokter').val('');
    $('#kd_pj').val('');
    $('#grup_bangsal').val('');
    loadData();
  }

  function exportExcelPerDokter() {
    const params = {
      bulan: $('#filter_bulan').val(),
      tahun: $('#filter_tahun').val(),
      kd_dokter: $('#kd_dokter').val(),
      kd_pj: $('#kd_pj').val(),
      grup_bangsal: $('#grup_bangsal').val(),
      tcari: ''
    };
    window.open(window.BASE_URL + '/api/export_hitung_jasa_dokter_ranap_umum.php?' + $.param(params), '_blank');
  }

  // Click row di tabel rekap -> buka modal invoice
  $('#content-dokter').on('click', '#tabelRekap tbody tr', function () {
    if (!tableRekap) return;
    var data = tableRekap.row(this).data();
    if (data) {
      bukaModalInvoice(data);
    }
  });

  function bukaModalInvoice(d) {
    var bulan = $('#filter_bulan').val();
    var tahun = $('#filter_tahun').val();
    var namaBulan = $('#filter_bulan option:selected').text();

    $('#slipDokter').text(d.nm_dokter + ' \u2014 ' + namaBulan + ' ' + tahun);
    $('#modalDetail').removeClass('hidden').show();

    var fmt = function (x) { return Math.round(x).toLocaleString('id-ID'); };
    var rp = function (x) { return x ? 'Rp ' + fmt(x) : 'Rp 0'; };

    var html = '';
    html += '<div class="text-sm">';

    // Items
    var items = [
      { label: 'Jumlah Pasien', value: Math.round(d.jumlah_pasien).toLocaleString('id-ID') + ' orang', style: 'font-bold' },
      { label: 'Pasien BPJS', value: Math.round(d.jumlah_pasien_bpjs).toLocaleString('id-ID') + ' orang' },
      { label: 'Pasien Non BPJS', value: Math.round(d.jumlah_pasien_non_bpjs).toLocaleString('id-ID') + ' orang' },
      { label: 'Klaim BPJS', value: Math.round(d.jumlah_pasien_klaim_bpjs).toLocaleString('id-ID') + ' orang' },
      { label: '', value: '', divider: true },
      { label: 'Total BPJS', value: rp(d.total_bpjs) },
      { label: '44%', value: rp(d.kolom_44) },
      { label: 'Jasa (Tarif Perda)', value: rp(d.total_tindakan_dr) },
      { label: 'Persenan BPJS', value: (d.persen_dokter || 0) + '%' },
      { label: '', value: '', divider: true },
      { label: 'Nominal Jasa (44% X Persenan BPJS)', value: rp(d.jumlah_dpjp), style: 'font-bold text-green-800 text-base' },
    ];

    items.forEach(function (item) {
      if (item.divider) {
        html += '<div class="border-t border-gray-300 my-2"></div>';
        return;
      }
      var cls = item.style || '';
      html += '<div class="flex justify-between py-1 ' + cls + '">';
      html += '<span>' + item.label + '</span>';
      html += '<span class="' + cls + '">' + item.value + '</span>';
      html += '</div>';
    });

    html += '<div class="text-center mt-4">';
    html += '<button onclick="window.open(\'' + window.BASE_URL + '/hitung-jasa-dokter-ranap/detail?kd_dokter=' + encodeURIComponent(d.kd_dokter) + '&bulan=' + $('#filter_bulan').val() + '&tahun=' + $('#filter_tahun').val() + '\',\'_blank\')" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition text-sm">Buka Detail Jasa</button>';
    html += '</div>';
    html += '</div>';
    $('#slipContent').html(html);
  }

  function tutupModal() {
    $('#modalDetail').addClass('hidden').hide();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>