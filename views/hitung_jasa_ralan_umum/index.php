<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$query_poli = "SELECT * FROM poliklinik where status = '1' ORDER BY nm_poli";
$result_poli = mysqli_query($koneksi, $query_poli);

$query_pj = "SELECT * FROM penjab where status = '1' ORDER BY kd_pj";
$result_pj = mysqli_query($koneksi, $query_pj);

$pageTitle = 'Hitung Jasa Rawat Jalan - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  table.dataTable td { color: #1f2937 !important; } table.dataTable thead th { color: #ffffff !important; }
  #tabelJasa th, #tabelJasa td { white-space: nowrap; }
  #tabelJasa tbody td { padding: 2px 4px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: top !important; text-align: left !important; }
  table.dataTable { width: auto !important; }
  .dt-buttons { margin-bottom: 10px; }
  .bg-jasa { background-color: #fefce8; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 p-6 mb-6">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i class="fas fa-calculator mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter Pencarian
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
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
      <label class="block text-sm font-medium text-gray-700 mb-2">Poliklinik</label>
      <select id="kd_poli" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="">Semua Poliklinik</option>
        <?php while ($row = mysqli_fetch_assoc($result_poli)): ?>
          <option value="<?= $row['kd_poli'] ?>"><?= $row['nm_poli'] ?></option>
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
    
  </div>
  <div class="mt-4 flex gap-2">
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition"><i
        class="fas fa-search mr-2"></i>Cari Data</button>
    <button onclick="resetFilter()"
      class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition"><i
        class="fas fa-redo mr-2"></i>Reset</button>
    <button onclick="exportExcelPerPoli()"
      class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl transition"><i
        class="fas fa-file-excel mr-2"></i>Export Excel per Poli</button>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-6">
  <div class="overflow-x-auto">
    <table id="tabelJasa" class="display w-full">
      <thead class="bg-green-800 text-white">
        <tr>
          <th class="px-2 text-left">No.</th>
          <th class="px-2 text-left">No.Rawat</th>
          <th class="px-2 text-left">No.RM</th>
          <th class="px-2 text-left">Pasien</th>
          <th class="px-2 text-left">Poli</th>
          <th class="px-2 text-left">Dokter</th>
          <th class="px-2 text-left">Tgl</th>

          <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Dokter</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Perawat</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Manajemen</th>
          <th class="px-2 text-right border-l-2 border-white" style="background:#166534;color:#fff">Total Jasa Tindakan
          </th>
          <th class="px-2 text-right border-r-2 border-white" style="background:#166534;color:#fff">Total Non Medis</th>

          <th class="px-2 text-right" style="background:#92400e;color:#fff">Jasa Farmasi</th>
          <th class="px-2 text-right" style="background:#92400e;color:#fff">Apoteker</th>
          <th class="px-2 text-right" style="background:#92400e;color:#fff">Non Apoteker</th>

          <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Dokter Lab</th>
          <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Petugas Lab</th>
          <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Manajemen Lab</th>
          <th class="px-2 text-right border-l-2 border-white" style="background:#075985;color:#fff">Total Jasa Lab</th>

          <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Dokter Rad</th>
          <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Petugas Rad</th>
          <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Manajemen Rad</th>
          <th class="px-2 text-right border-l-2 border-white" style="background:#7c3aed;color:#fff">Total Jasa Rad</th>

          <th class="px-2 text-right border-l-2 border-white" style="background:#991b1b;color:#fff;font-weight:900">
            TOTAL JASA</th>
          </tr>
      </thead>
      <tbody></tbody>
      <tfoot class="bg-green-800 font-bold text-white">
        <tr>
          <th colspan="7" class="text-right px-2">TOTAL AKHIR :</th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
          <th class="text-right px-2"></th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
  let table;
  const tableConfig = {
    processing: true,
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    dom: '<"flex justify-between items-center mb-4"lB>rtip',
    buttons: [{
      extend: 'excel',
      text: 'Export Excel',
      filename: 'hitung_jasa_ralan_umum'
    },
    {
      extend: 'pdfHtml5',
      text: 'Export PDF',
      orientation: 'landscape',
      pageSize: 'A4',
      customize: function (doc) {
        doc.defaultStyle.fontSize = 5;
        doc.styles.tableHeader.fontSize = 8;
      }
    }
    ],
    lengthMenu: [
      [10, 25, 50, 100, 300, 1000, 5000, 10000],
      [10, 25, 50, 100, 300, 1000, 5000, 10000]
    ],
    pageLength: 25,
    autoWidth: false,
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
      data: 'no_rkm_medis'
    },
    {
      data: 'nm_pasien'
    },
    {
      data: 'nm_poli'
    },
    {
      data: 'nm_dokter'
    },
    {
      data: 'tgl_registrasi'
    },

    {
      data: 'total_tindakan_dr',
      className: 'num'
    },
    {
      data: 'total_tindakan_pr',
      className: 'num'
    },
    {
      data: 'total_menejemen_tindakan',
      className: 'num'
    },
    {
      data: 'jasa_tindakan',
      className: 'num font-bold',
      createdCell: function (td) {
        $(td).css('background', '#fefce8');
      }
    },
    {
      data: 'total_non_medis',
      className: 'num',
      createdCell: function (td) {
        $(td).css('background', '#fefce8');
      }
    },

    {
      data: 'jasa_farmasi',
      className: 'num',
      createdCell: function (td) {
        $(td).css('background', '#fffbeb');
      }
    },
    {
      data: 'jasa_apoteker',
      className: 'num',
      createdCell: function (td) {
        $(td).css('background', '#fffbeb');
      }
    },
    {
      data: 'jasa_non_apoteker',
      className: 'num',
      createdCell: function (td) {
        $(td).css('background', '#fffbeb');
      }
    },

    {
      data: 'total_dokter_lab',
      className: 'num'
    },
    {
      data: 'total_petugas_lab',
      className: 'num'
    },
    {
      data: 'total_menejemen_lab',
      className: 'num'
    },
    {
      data: 'jasa_lab',
      className: 'num font-bold',
      createdCell: function (td) {
        $(td).css('background', '#f0f9ff');
      }
    },

    {
      data: 'total_dokter_radiologi',
      className: 'num'
    },
    {
      data: 'total_petugas_radiologi',
      className: 'num'
    },
    {
      data: 'total_menejemen_radiologi',
      className: 'num'
    },
    {
      data: 'jasa_radiologi',
      className: 'num font-bold',
      createdCell: function (td) {
        $(td).css('background', '#f5f3ff');
      }
    },

    {
      data: 'total_jasa',
      className: 'num font-bold',
      createdCell: function (td) {
        $(td).css('background', '#fef2f2').css('font-weight', '900');
      }
    }
    ],
    order: [
      [9, 'desc']
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
      const sumData = (prop) => data.map(r => parseFloat(r[prop]) || 0).reduce((a, b) => a + b, 0);
      const fmt = (x) => "Rp " + Math.round(x).toLocaleString('id-ID');

      $(api.column(7).footer()).html(fmt(sumData('total_tindakan_dr')));
      $(api.column(8).footer()).html(fmt(sumData('total_tindakan_pr')));
      $(api.column(9).footer()).html(fmt(sumData('total_menejemen_tindakan')));
      $(api.column(10).footer()).html(fmt(sumData('jasa_tindakan')));
      $(api.column(11).footer()).html(fmt(sumData('total_non_medis')));
      $(api.column(12).footer()).html(fmt(sumData('jasa_farmasi')));
      $(api.column(13).footer()).html(fmt(sumData('jasa_apoteker')));
      $(api.column(14).footer()).html(fmt(sumData('jasa_non_apoteker')));
      $(api.column(15).footer()).html(fmt(sumData('total_dokter_lab')));
      $(api.column(16).footer()).html(fmt(sumData('total_petugas_lab')));
      $(api.column(17).footer()).html(fmt(sumData('total_menejemen_lab')));
      $(api.column(18).footer()).html(fmt(sumData('jasa_lab')));
      $(api.column(19).footer()).html(fmt(sumData('total_dokter_radiologi')));
      $(api.column(20).footer()).html(fmt(sumData('total_petugas_radiologi')));
      $(api.column(21).footer()).html(fmt(sumData('total_menejemen_radiologi')));
      $(api.column(22).footer()).html(fmt(sumData('jasa_radiologi')));
      $(api.column(23).footer()).html(fmt(sumData('total_jasa')));
    }
  };

  $(document).ready(function () {
    table = $('#tabelJasa').DataTable(tableConfig);
  });

  function loadData() {
    if (table) table.destroy();
    table = $('#tabelJasa').DataTable({
      ...tableConfig,
      serverSide: true,
      ajax: {
        url: window.BASE_URL + '/api/get_data_hitung_jasa_ralan_umum.php',
        type: 'POST',
        dataSrc: function (json) {
          return json.data || json;
        },
        data: function (d) {
          d.bulan = $('#filter_bulan').val();
          d.tahun = $('#filter_tahun').val();
          d.kd_poli = $('#kd_poli').val();
          d.kd_pj = $('#kd_pj').val();
                    d.tcari = '';
          d.search_value = d.search.value;
        }
      }
    });
  }

  function resetFilter() {
    const now = new Date();
    $('#filter_bulan').val(String(now.getMonth() + 1));
    $('#filter_tahun').val(String(now.getFullYear()));
    $('#kd_poli').val('');
    $('#kd_pj').val('');
        loadData();
  }

  function exportExcelPerPoli() {
    const params = {
      bulan: $('#filter_bulan').val(),
      tahun: $('#filter_tahun').val(),
      kd_poli: $('#kd_poli').val(),
      kd_pj: $('#kd_pj').val(),
            tcari: ''
    };
    window.open(window.BASE_URL + '/api/export_hitung_jasa_ralan_umum.php?' + $.param(params), '_blank');
  }
</script>
<?php require_once '../layouts/footer.php'; ?>