<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$query_bangsal = "SELECT DISTINCT grup_bangsal FROM v_bangsal_grup ORDER BY grup_bangsal";
$result_bangsal = mysqli_query($koneksi, $query_bangsal);

$query_pj = "SELECT * FROM penjab where status = '1' ORDER BY kd_pj";
$result_pj = mysqli_query($koneksi, $query_pj);

$pageTitle = 'Hitung Jasa Rawat Inap - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  table.dataTable td, table.dataTable th { color: #1f2937 !important; }
  #tabelJasa th, #tabelJasa td { white-space: nowrap; }
  #tabelJasa tbody td { padding: 2px 4px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: top !important; text-align: left !important; }
  table.dataTable { width: auto !important; }
  .dt-buttons { margin-bottom: 10px; }
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
      <label class="block text-sm font-medium text-gray-700 mb-2">Bangsal</label>
      <select id="grup_bangsal" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="">Semua Grup Bangsal</option>
        <?php while ($row = mysqli_fetch_assoc($result_bangsal)): ?>
          <option value="<?= $row['grup_bangsal'] ?>"><?= $row['grup_bangsal'] ?></option>
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
      <label class="block text-sm font-medium text-gray-700 mb-2">Status SEP</label>
      <select id="filter_sep"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="semua" selected>Semua Data</option>
        <option value="ada">Ada SEP</option>
        <option value="tidak_ada">Tidak Ada SEP</option>
      </select>
    </div>
  </div>
  <div class="mt-4 flex gap-2">
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition"><i
        class="fas fa-search mr-2"></i>Cari Data</button>
    <button onclick="resetFilter()"
      class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition"><i
        class="fas fa-redo mr-2"></i>Reset</button>
    <button onclick="exportExcelPerBangsal()"
      class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl transition"><i
        class="fas fa-file-excel mr-2"></i>Export Excel per Bangsal</button>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-6">
  <div class="overflow-x-auto">
    <table id="tabelJasa" class="display w-full">
      <thead class="bg-green-800 text-white">
        <tr>
          <th class="px-2 text-left">No.</th>
          <th class="px-2 text-left">No.Rawat</th>
          <th class="px-2 text-left">No.SEP</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Total BPJS</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">44%</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Sisa BPJS</th>
          <th class="px-2 text-left">No.RM</th>
          <th class="px-2 text-left">Pasien</th>
          <th class="px-2 text-left">Bangsal</th>
          <th class="px-2 text-left">DPJP</th>
          <th class="px-2 text-left">Tgl Masuk</th>
          <th class="px-2 text-right">Lama</th>
          <th class="px-2 text-left">Status Pulang</th>

          <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Dokter</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Perawat</th>
          <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Manajemen</th>
          <th class="px-2 text-right border-l-2 border-white" style="background:#166534;color:#fff">Total Jasa Tindakan</th>
          <th class="px-2 text-right border-r-2 border-white" style="background:#166534;color:#fff">Total Non Medis</th>

          <th class="px-2 text-right" style="background:#92400e;color:#fff">Jasa Farmasi</th>

          <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Dokter Lab</th>
          <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Petugas Lab</th>
          <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Manajemen Lab</th>
          <th class="px-2 text-right border-l-2 border-white" style="background:#075985;color:#fff">Total Jasa Lab</th>

          <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Dokter Rad</th>
          <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Petugas Rad</th>
          <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Manajemen Rad</th>
          <th class="px-2 text-right border-l-2 border-white" style="background:#7c3aed;color:#fff">Total Jasa Rad</th>

          <th class="px-2 text-right border-l-2 border-white" style="background:#991b1b;color:#fff;font-weight:900">TOTAL JASA</th>
          <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%DPJP</th>
          <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml DPJP</th>
          <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Perawat</th>
          <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Perawat</th>
          <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Farmasi</th>
          <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Farmasi</th>
          <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Dr Lab</th>
          <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Dr Lab</th>
          <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Analis Lab</th>
          <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Analis Lab</th>
          <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Dr Rad</th>
          <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Dr Rad</th>
          <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Radiografer</th>
          <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Radiografer</th>
          <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Non Medis</th>
          <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Non Medis</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot class="bg-green-800 font-bold text-white">
        <tr>
          <th colspan="3" class="text-right px-2">TOTAL AKHIR :</th>
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

  $(document).ready(function() {
    table = $('#tabelJasa').DataTable({
      processing: true,
      serverSide: true,
      scrollY: "500px",
      scrollX: true,
      scrollCollapse: true,
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{
          extend: 'excel',
          text: 'Export Excel',
          filename: 'hitung_jasa_ranap'
        },
        {
          extend: 'pdfHtml5',
          text: 'Export PDF',
          orientation: 'landscape',
          pageSize: 'A4',
          customize: function(doc) {
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

      ajax: {
        url: window.BASE_URL + '/api/get_data_hitung_jasa_ranap.php',
        type: 'POST',
        dataSrc: function(json) {
          return json.data || json;
        },
        data: function(d) {
          d.bulan = $('#filter_bulan').val();
          d.tahun = $('#filter_tahun').val();
          d.grup_bangsal = $('#grup_bangsal').val();
          d.kd_pj = $('#kd_pj').val();
          d.filter_sep = $('#filter_sep').val();
          d.tcari = '';
          d.search_value = d.search.value;
        }
      },
      columns: [{
          data: null,
          className: 'text-center',
          render: function(data, type, row, meta) {
            return meta.row + 1;
          }
        },
        {
          data: 'no_rawat',
          className: 'text-blue-600 font-semibold'
        },
        {
          data: 'no_sep'
        },
        {
          data: 'total_bpjs',
          className: 'num',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'kolom_44',
          className: 'num',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'sisa_bpjs',
          className: 'num',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
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
          data: 'lama',
          className: 'num'
        },
        {
          data: 'stts_pulang'
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
          createdCell: function(td) {
            $(td).css('background', '#fefce8');
          }
        },
        {
          data: 'total_non_medis',
          className: 'num',
          createdCell: function(td) {
            $(td).css('background', '#fefce8');
          }
        },

        {
          data: 'jasa_farmasi',
          className: 'num',
          createdCell: function(td) {
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
          createdCell: function(td) {
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
          createdCell: function(td) {
            $(td).css('background', '#f5f3ff');
          }
        },

        {
          data: 'total_jasa',
          className: 'num font-bold',
          createdCell: function(td) {
            $(td).css('background', '#fef2f2').css('font-weight', '900');
          }
        },

        {
          data: 'persen_dpjp',
          className: 'num text-xs',
          render: function(v) {
            return v ? v.toFixed(2) : '0';
          }
        },
        {
          data: 'jumlah_dpjp',
          className: 'num text-xs',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'persen_perawat',
          className: 'num text-xs',
          render: function(v) {
            return v ? v.toFixed(2) : '0';
          }
        },
        {
          data: 'jumlah_perawat',
          className: 'num text-xs',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'persen_farmasi',
          className: 'num text-xs',
          render: function(v) {
            return v ? v.toFixed(2) : '0';
          }
        },
        {
          data: 'jumlah_farmasi',
          className: 'num text-xs',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'persen_dokter_lab',
          className: 'num text-xs',
          render: function(v) {
            return v ? v.toFixed(2) : '0';
          }
        },
        {
          data: 'jumlah_dokter_lab',
          className: 'num text-xs',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'persen_analis_lab',
          className: 'num text-xs',
          render: function(v) {
            return v ? v.toFixed(2) : '0';
          }
        },
        {
          data: 'jumlah_analis_lab',
          className: 'num text-xs',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'persen_dokter_radiologi',
          className: 'num text-xs',
          render: function(v) {
            return v ? v.toFixed(2) : '0';
          }
        },
        {
          data: 'jumlah_dokter_radiologi',
          className: 'num text-xs',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'persen_radiografer',
          className: 'num text-xs',
          render: function(v) {
            return v ? v.toFixed(2) : '0';
          }
        },
        {
          data: 'jumlah_radiografer',
          className: 'num text-xs',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
        {
          data: 'persen_non_medis',
          className: 'num text-xs',
          render: function(v) {
            return v ? v.toFixed(2) : '0';
          }
        },
        {
          data: 'jumlah_non_medis',
          className: 'num text-xs',
          render: function(v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; }
        },
      ],
      order: [
        [10, 'desc']
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
      footerCallback: function(row, data, start, end, display) {
        const api = this.api();
        const sumData = (prop) => data.map(r => parseFloat(r[prop]) || 0).reduce((a, b) => a + b, 0);
        const fmt = (x) => "Rp " + Math.round(x).toLocaleString('id-ID');
        const pct = (num, den) => den > 0 ? (num / den * 100).toFixed(2) : '0';

        const st2 = (p) => sumData(p);
        const sj2 = st2('total_jasa');

        $(api.column(13).footer()).html(fmt(sumData('total_tindakan_dr')));
        $(api.column(14).footer()).html(fmt(sumData('total_tindakan_pr')));
        $(api.column(15).footer()).html(fmt(sumData('total_menejemen_tindakan')));
        $(api.column(16).footer()).html(fmt(sumData('jasa_tindakan')));
        $(api.column(17).footer()).html(fmt(sumData('total_non_medis')));
        $(api.column(18).footer()).html(fmt(sumData('jasa_farmasi')));
        $(api.column(19).footer()).html(fmt(sumData('total_dokter_lab')));
        $(api.column(20).footer()).html(fmt(sumData('total_petugas_lab')));
        $(api.column(21).footer()).html(fmt(sumData('total_menejemen_lab')));
        $(api.column(22).footer()).html(fmt(sumData('jasa_lab')));
        $(api.column(23).footer()).html(fmt(sumData('total_dokter_radiologi')));
        $(api.column(24).footer()).html(fmt(sumData('total_petugas_radiologi')));
        $(api.column(25).footer()).html(fmt(sumData('total_menejemen_radiologi')));
        $(api.column(26).footer()).html(fmt(sumData('jasa_radiologi')));
        $(api.column(27).footer()).html(fmt(sumData('total_jasa')));
        $(api.column(3).footer()).html(fmt(sumData('total_bpjs')));
        $(api.column(4).footer()).html(fmt(sumData('kolom_44')));
        $(api.column(5).footer()).html(fmt(sumData('sisa_bpjs')));

        $(api.column(28).footer()).html(pct(st2('total_tindakan_dr'), sj2));
        $(api.column(29).footer()).html(fmt(sumData('jumlah_dpjp')));
        $(api.column(30).footer()).html(pct(st2('total_tindakan_pr'), sj2));
        $(api.column(31).footer()).html(fmt(sumData('jumlah_perawat')));
        $(api.column(32).footer()).html(pct(st2('jasa_farmasi'), sj2));
        $(api.column(33).footer()).html(fmt(sumData('jumlah_farmasi')));
        $(api.column(34).footer()).html(pct(st2('total_dokter_lab'), sj2));
        $(api.column(35).footer()).html(fmt(sumData('jumlah_dokter_lab')));
        $(api.column(36).footer()).html(pct(st2('total_petugas_lab'), sj2));
        $(api.column(37).footer()).html(fmt(sumData('jumlah_analis_lab')));
        $(api.column(38).footer()).html(pct(st2('total_dokter_radiologi'), sj2));
        $(api.column(39).footer()).html(fmt(sumData('jumlah_dokter_radiologi')));
        $(api.column(40).footer()).html(pct(st2('total_petugas_radiologi'), sj2));
        $(api.column(41).footer()).html(fmt(sumData('jumlah_radiografer')));
        $(api.column(42).footer()).html(pct(st2('total_non_medis'), sj2));
        $(api.column(43).footer()).html(fmt(sumData('jumlah_non_medis')));
      }
    });
  });

  function loadData() {
    table.ajax.reload();
  }

  function resetFilter() {
    const now = new Date();
    $('#filter_bulan').val(String(now.getMonth() + 1));
    $('#filter_tahun').val(String(now.getFullYear()));
    $('#grup_bangsal').val('');
    $('#kd_pj').val('');
    $('#filter_sep').val('semua');
    loadData();
  }

  function exportExcelPerBangsal() {
    const params = {
      bulan: $('#filter_bulan').val(),
      tahun: $('#filter_tahun').val(),
      grup_bangsal: $('#grup_bangsal').val(),
      kd_pj: $('#kd_pj').val(),
      filter_sep: $('#filter_sep').val(),
      tcari: ''
    };
    window.open(window.BASE_URL + '/api/export_hitung_jasa_ranap.php?' + $.param(params), '_blank');
  }
</script>
<?php require_once '../layouts/footer.php'; ?>
