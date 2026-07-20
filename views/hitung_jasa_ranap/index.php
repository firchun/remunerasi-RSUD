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
  table.dataTable td { color: #1f2937 !important; } table.dataTable thead th { color: #ffffff !important; }
  #tabelJasa th, #tabelJasa td { white-space: nowrap; }
  #tabelJasa tbody td { padding: 2px 4px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: top !important; text-align: left !important; }
  table.dataTable { width: auto !important; }
  .dt-buttons { margin-bottom: 10px; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
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
      <select id="grup_bangsal"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
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
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white p-2 rounded-xl transition"><i
        class="fas fa-search mr-2"></i>Cari Data</button>
    <button onclick="resetFilter()"
      class="border border-gray-600 text-gray-600 p-2 rounded-xl hover:bg-gray-200 transition"><i
        class="fas fa-redo mr-2"></i>Reset</button>
    <button onclick="exportExcelPerBangsal()"
      class="bg-blue-600 hover:bg-blue-700 text-white p-2 rounded-xl transition"><i
        class="fas fa-file-excel mr-2"></i>Export Excel per Bangsal</button>
  </div>
</div>

<div class="mb-4 flex border-b border-gray-200">
  <button id="tab-pasien" onclick="switchTab('pasien')"
    class="py-2 px-6 text-green-700 border-b-2 border-green-700 font-semibold focus:outline-none transition"><i
      class="fas fa-procedures mr-2"></i>Per Pasien</button>
  <button id="tab-ruangan" onclick="switchTab('ruangan')"
    class="py-2 px-6 text-gray-500 border-b-2 border-transparent hover:text-green-700 font-semibold focus:outline-none transition"><i
      class="fas fa-door-open mr-2"></i>Per Ruangan</button>
</div>

<div id="content-pasien" class="tab-content block">
  <div class="bg-white rounded-2xl border border-green-700 p-3">
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

            <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Dokter</th>
            <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Perawat</th>
            <th class="px-2 text-right" style="background:#166534;color:#fff">Jasa Manajemen</th>
            <th class="px-2 text-right border-l-2 border-white" style="background:#166534;color:#fff">Total Jasa
              Tindakan
            </th>
            <th class="px-2 text-right border-r-2 border-white" style="background:#166534;color:#fff">Total Non Medis
            </th>

            <th class="px-2 text-right" style="background:#b45309;color:#fff">Operator</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Asisten</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Dr Anestesi</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">As Anestesi</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Dr Anak</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Pr Resusitasi</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Bidan</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Instrumen</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Omloop</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Dr PJA</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Dr Umum</th>
            <th class="px-2 text-right" style="background:#b45309;color:#fff">Pr Luar</th>
            <th class="px-2 text-right border-l-2 border-white" style="background:#b45309;color:#fff">Total Ops</th>

            <th class="px-2 text-right" style="background:#92400e;color:#fff">Jasa Farmasi</th>

            <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Dokter Lab</th>
            <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Petugas Lab</th>
            <th class="px-2 text-right" style="background:#075985;color:#fff">Jasa Manajemen Lab</th>
            <th class="px-2 text-right border-l-2 border-white" style="background:#075985;color:#fff">Total Jasa Lab
            </th>

            <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Dokter Rad</th>
            <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Petugas Rad</th>
            <th class="px-2 text-right" style="background:#7c3aed;color:#fff">Jasa Manajemen Rad</th>
            <th class="px-2 text-right border-l-2 border-white" style="background:#7c3aed;color:#fff">Total Jasa Rad
            </th>

            <th class="px-2 text-right border-l-2 border-white" style="background:#991b1b;color:#fff;font-weight:900">
              TOTAL JASA</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%DPJP</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml DPJP</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Perawat</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Perawat</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Operator</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Operator</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Asisten</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Asisten</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Dr Anes</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Dr Anes</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%As Anes</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml As Anes</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Dr Anak</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Dr Anak</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Pr Resus</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Pr Resus</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Bidan</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Bidan</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Instrumen</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Instrumen</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Omloop</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Omloop</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Dr PJA</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Dr PJA</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Dr Umum</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Dr Umum</th>
            <th class="px-2 text-right text-xs" style="background:#064e3b;color:#fff">%Pr Luar</th>
            <th class="px-2 text-right text-xs" style="background:#065f46;color:#fff">Jml Pr Luar</th>
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
            <th class="text-right px-2"></th>
            <th class="text-right px-2">TOTAL AKHIR :</th>
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
</div>

<div id="content-ruangan" class="tab-content hidden">
  <div class="bg-white rounded-2xl border border-green-700 p-6">
    <div class="overflow-x-auto">
      <table id="tabelRekap" class="display w-full">
        <thead class="bg-green-800 text-white">
          <tr>
            <th class="px-2 text-left">No.</th>
            <th class="px-2 text-left">Bangsal</th>
            <th class="px-2 text-right">Jml Pasien</th>
            <th class="px-2 text-right">Total BPJS</th>
            <th class="px-2 text-right">44%</th>
            <th class="px-2 text-right">Sisa BPJS</th>
            <th class="px-2 text-right">Jml DPJP</th>
            <th class="px-2 text-right">Jml Perawat</th>
            <th class="px-2 text-right">Jml Operator</th>
            <th class="px-2 text-right">Jml Asisten</th>
            <th class="px-2 text-right">Jml Dr Anes</th>
            <th class="px-2 text-right">Jml As Anes</th>
            <th class="px-2 text-right">Jml Dr Anak</th>
            <th class="px-2 text-right">Jml Pr Resusitasi</th>
            <th class="px-2 text-right">Jml Bidan</th>
            <th class="px-2 text-right">Jml Instrumen</th>
            <th class="px-2 text-right">Jml Omloop</th>
            <th class="px-2 text-right">Jml Dr PJA</th>
            <th class="px-2 text-right">Jml Dr Umum</th>
            <th class="px-2 text-right">Jml Pr Luar</th>
            <th class="px-2 text-right">Jml Farmasi</th>
            <th class="px-2 text-right">Jml Dr Lab</th>
            <th class="px-2 text-right">Jml Analis Lab</th>
            <th class="px-2 text-right">Jml Dr Rad</th>
            <th class="px-2 text-right">Jml Radiografer</th>
            <th class="px-2 text-right">Jml Non Medis</th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot class="bg-green-800 font-bold text-white">
          <tr>
            <th class="text-right px-2"></th>
            <th class="text-right px-2">TOTAL AKHIR :</th>
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
</div>
</div>

<script>
  let table;
  let tableRekap;
  let currentTab = 'pasien';

  function switchTab(tabId) {
    $('.tab-content').removeClass('block').addClass('hidden');
    $('#content-' + tabId).removeClass('hidden').addClass('block');

    $('#tab-pasien, #tab-ruangan').removeClass('text-green-700 border-green-700').addClass('text-gray-500 border-transparent');
    $('#tab-' + tabId).removeClass('text-gray-500 border-transparent').addClass('text-green-700 border-green-700');

    currentTab = tabId;

    setTimeout(function () {
      if (tabId === 'pasien' && table) {
        table.columns.adjust().draw();
      } else if (tabId === 'ruangan') {
        if (!tableRekap) {
          initTableRekap();
        } else {
          tableRekap.columns.adjust().draw();
        }
      }
    }, 50);
  }

  function initTableRekap() {
    tableRekap = $('#tabelRekap').DataTable({
      processing: true,
      serverSide: false, // Since API returns all data aggregated
      scrollY: "500px",
      scrollX: true,
      scrollCollapse: true,
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [
        {
          extend: 'excel',
          text: 'Export Excel',
          filename: 'rekap_jasa_ranap'
        }
      ],
      lengthMenu: [[-1, 10, 25, 50, 100], ["Semua", 10, 25, 50, 100]],
      pageLength: -1,
      autoWidth: false,
      ajax: {
        url: window.BASE_URL + '/api/get_data_rekap_jasa_ranap.php',
        type: 'POST',
        dataSrc: 'data',
        data: function (d) {
          d.bulan = $('#filter_bulan').val();
          d.tahun = $('#filter_tahun').val();
          d.grup_bangsal = $('#grup_bangsal').val();
          d.kd_pj = $('#kd_pj').val();
          d.filter_sep = $('#filter_sep').val();
        }
      },
      columns: [
        {
          data: null,
          className: 'text-center',
          render: function (data, type, row, meta) {
            return meta.row + 1;
          }
        },
        { data: 'nm_bangsal', className: 'font-semibold' },
        { data: 'jumlah_pasien', className: 'num text-center', render: function (v) { return v ? v.toLocaleString('id-ID') : '0'; } },
        { data: 'total_bpjs', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'kolom_44', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'sisa_bpjs', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_dpjp', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_perawat', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_operator', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_asisten', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_dr_anestesi', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_as_anestesi', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_dr_anak', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_pr_resusitas', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_bidan', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_instrumen', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_omloop', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_dr_pjanak', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_dr_umum', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_pr_luar', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_farmasi', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_dokter_lab', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_analis_lab', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_dokter_radiologi', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_radiografer', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
        { data: 'jml_non_medis', className: 'num', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } }
      ],
      order: [[1, 'asc']],
      language: {
        processing: "Memuat data...",
        lengthMenu: "Tampilkan _MENU_ data per halaman",
        zeroRecords: "Data tidak ditemukan",
        info: "Menampilkan _TOTAL_ data bangsal",
        infoEmpty: "Tidak ada data tersedia",
        infoFiltered: "(difilter dari _MAX_ total data)",
        search: "Cari Bangsal:",
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
        const numFmt = (x) => Math.round(x).toLocaleString('id-ID');

        let colIdx = 2;
        $(api.column(colIdx++).footer()).html(numFmt(sumData('jumlah_pasien')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('total_bpjs')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('kolom_44')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('sisa_bpjs')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_dpjp')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_perawat')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_operator')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_asisten')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_dr_anestesi')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_as_anestesi')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_dr_anak')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_pr_resusitas')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_bidan')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_instrumen')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_omloop')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_dr_pjanak')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_dr_umum')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_pr_luar')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_farmasi')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_dokter_lab')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_analis_lab')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_dokter_radiologi')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_radiografer')));
        $(api.column(colIdx++).footer()).html(fmt(sumData('jml_non_medis')));
      }
    });
  }

  $(document).ready(function () {
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

      ajax: {
        url: window.BASE_URL + '/api/get_data_hitung_jasa_ranap.php',
        type: 'POST',
        dataSrc: function (json) {
          return json.data || json;
        },
        data: function (d) {
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
        render: function (data, type, row, meta) {
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
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      {
        data: 'kolom_44',
        className: 'num',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      {
        data: 'sisa_bpjs',
        className: 'num',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
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

      { data: 'jasa_operator', className: 'num' },
      { data: 'jasa_asisten', className: 'num' },
      { data: 'jasa_dr_anestesi', className: 'num' },
      { data: 'jasa_as_anestesi', className: 'num' },
      { data: 'jasa_dr_anak', className: 'num' },
      { data: 'jasa_pr_resusitas', className: 'num' },
      { data: 'jasa_bidan', className: 'num' },
      { data: 'jasa_instrumen', className: 'num' },
      { data: 'jasa_omloop', className: 'num' },
      { data: 'jasa_dr_pjanak', className: 'num' },
      { data: 'jasa_dr_umum', className: 'num' },
      { data: 'jasa_pr_luar', className: 'num' },
      {
        data: 'jasa_operasi',
        className: 'num font-bold',
        createdCell: function (td) {
          $(td).css('background', '#fffbeb');
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
      },

      {
        data: 'persen_dpjp',
        className: 'num text-xs',
        render: function (v) {
          return v ? v.toFixed(2) : '0';
        }
      },
      {
        data: 'jumlah_dpjp',
        className: 'num text-xs',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      {
        data: 'persen_perawat',
        className: 'num text-xs',
        render: function (v) {
          return v ? v.toFixed(2) : '0';
        }
      },
      {
        data: 'jumlah_perawat',
        className: 'num text-xs',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      { data: 'persen_operator', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_operator', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_asisten', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_asisten', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_dr_anestesi', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_dr_anestesi', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_as_anestesi', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_as_anestesi', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_dr_anak', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_dr_anak', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_pr_resusitas', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_pr_resusitas', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_bidan', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_bidan', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_instrumen', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_instrumen', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_omloop', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_omloop', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_dr_pjanak', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_dr_pjanak', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_dr_umum', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_dr_umum', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },
      { data: 'persen_pr_luar', className: 'num text-xs', render: function (v) { return v ? v.toFixed(2) : '0'; } },
      { data: 'jumlah_pr_luar', className: 'num text-xs', render: function (v) { return v ? Math.round(v).toLocaleString('id-ID') : '0'; } },

      {
        data: 'persen_farmasi',
        className: 'num text-xs',
        render: function (v) {
          return v ? v.toFixed(2) : '0';
        }
      },
      {
        data: 'jumlah_farmasi',
        className: 'num text-xs',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      {
        data: 'persen_dokter_lab',
        className: 'num text-xs',
        render: function (v) {
          return v ? v.toFixed(2) : '0';
        }
      },
      {
        data: 'jumlah_dokter_lab',
        className: 'num text-xs',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      {
        data: 'persen_analis_lab',
        className: 'num text-xs',
        render: function (v) {
          return v ? v.toFixed(2) : '0';
        }
      },
      {
        data: 'jumlah_analis_lab',
        className: 'num text-xs',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      {
        data: 'persen_dokter_radiologi',
        className: 'num text-xs',
        render: function (v) {
          return v ? v.toFixed(2) : '0';
        }
      },
      {
        data: 'jumlah_dokter_radiologi',
        className: 'num text-xs',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      {
        data: 'persen_radiografer',
        className: 'num text-xs',
        render: function (v) {
          return v ? v.toFixed(2) : '0';
        }
      },
      {
        data: 'jumlah_radiografer',
        className: 'num text-xs',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
      },
      {
        data: 'persen_non_medis',
        className: 'num text-xs',
        render: function (v) {
          return v ? v.toFixed(2) : '0';
        }
      },
      {
        data: 'jumlah_non_medis',
        className: 'num text-xs',
        render: function (v) {
          return v ? Math.round(v).toLocaleString('id-ID') : '0';
        }
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
      footerCallback: function (row, data, start, end, display) {
        const api = this.api();
        const sumData = (prop) => data.map(r => parseFloat(r[prop]) || 0).reduce((a, b) => a + b, 0);
        const fmt = (x) => "Rp " + Math.round(x).toLocaleString('id-ID');
        const pct = (num, den) => den > 0 ? (num / den * 100).toFixed(2) : '0';

        const st2 = (p) => sumData(p);
        const sj2 = st2('total_jasa');

        $(api.column(12).footer()).html(fmt(sumData('total_tindakan_dr')));
        $(api.column(13).footer()).html(fmt(sumData('total_tindakan_pr')));
        $(api.column(14).footer()).html(fmt(sumData('total_menejemen_tindakan')));
        $(api.column(15).footer()).html(fmt(sumData('jasa_tindakan')));
        $(api.column(16).footer()).html(fmt(sumData('total_non_medis')));
        $(api.column(17).footer()).html(fmt(sumData('jasa_operator')));
        $(api.column(18).footer()).html(fmt(sumData('jasa_asisten')));
        $(api.column(19).footer()).html(fmt(sumData('jasa_dr_anestesi')));
        $(api.column(20).footer()).html(fmt(sumData('jasa_as_anestesi')));
        $(api.column(21).footer()).html(fmt(sumData('jasa_dr_anak')));
        $(api.column(22).footer()).html(fmt(sumData('jasa_pr_resusitas')));
        $(api.column(23).footer()).html(fmt(sumData('jasa_bidan')));
        $(api.column(24).footer()).html(fmt(sumData('jasa_instrumen')));
        $(api.column(25).footer()).html(fmt(sumData('jasa_omloop')));
        $(api.column(26).footer()).html(fmt(sumData('jasa_dr_pjanak')));
        $(api.column(27).footer()).html(fmt(sumData('jasa_dr_umum')));
        $(api.column(28).footer()).html(fmt(sumData('jasa_pr_luar')));
        $(api.column(29).footer()).html(fmt(sumData('jasa_operasi')));
        $(api.column(30).footer()).html(fmt(sumData('jasa_farmasi')));
        $(api.column(31).footer()).html(fmt(sumData('total_dokter_lab')));
        $(api.column(32).footer()).html(fmt(sumData('total_petugas_lab')));
        $(api.column(33).footer()).html(fmt(sumData('total_menejemen_lab')));
        $(api.column(34).footer()).html(fmt(sumData('jasa_lab')));
        $(api.column(35).footer()).html(fmt(sumData('total_dokter_radiologi')));
        $(api.column(36).footer()).html(fmt(sumData('total_petugas_radiologi')));
        $(api.column(37).footer()).html(fmt(sumData('total_menejemen_radiologi')));
        $(api.column(38).footer()).html(fmt(sumData('jasa_radiologi')));
        $(api.column(39).footer()).html(fmt(sumData('total_jasa')));
        $(api.column(3).footer()).html(fmt(sumData('total_bpjs')));
        $(api.column(4).footer()).html(fmt(sumData('kolom_44')));
        $(api.column(5).footer()).html(fmt(sumData('sisa_bpjs')));

        $(api.column(40).footer()).html(pct(st2('total_tindakan_dr'), sj2));
        $(api.column(41).footer()).html(fmt(sumData('jumlah_dpjp')));
        $(api.column(42).footer()).html(pct(st2('total_tindakan_pr'), sj2));
        $(api.column(43).footer()).html(fmt(sumData('jumlah_perawat')));
        $(api.column(44).footer()).html(pct(st2('jasa_operator'), sj2));
        $(api.column(45).footer()).html(fmt(sumData('jumlah_operator')));
        $(api.column(46).footer()).html(pct(st2('jasa_asisten'), sj2));
        $(api.column(47).footer()).html(fmt(sumData('jumlah_asisten')));
        $(api.column(48).footer()).html(pct(st2('jasa_dr_anestesi'), sj2));
        $(api.column(49).footer()).html(fmt(sumData('jumlah_dr_anestesi')));
        $(api.column(50).footer()).html(pct(st2('jasa_as_anestesi'), sj2));
        $(api.column(51).footer()).html(fmt(sumData('jumlah_as_anestesi')));
        $(api.column(52).footer()).html(pct(st2('jasa_dr_anak'), sj2));
        $(api.column(53).footer()).html(fmt(sumData('jumlah_dr_anak')));
        $(api.column(54).footer()).html(pct(st2('jasa_pr_resusitas'), sj2));
        $(api.column(55).footer()).html(fmt(sumData('jumlah_pr_resusitas')));
        $(api.column(56).footer()).html(pct(st2('jasa_bidan'), sj2));
        $(api.column(57).footer()).html(fmt(sumData('jumlah_bidan')));
        $(api.column(58).footer()).html(pct(st2('jasa_instrumen'), sj2));
        $(api.column(59).footer()).html(fmt(sumData('jumlah_instrumen')));
        $(api.column(60).footer()).html(pct(st2('jasa_omloop'), sj2));
        $(api.column(61).footer()).html(fmt(sumData('jumlah_omloop')));
        $(api.column(62).footer()).html(pct(st2('jasa_dr_pjanak'), sj2));
        $(api.column(63).footer()).html(fmt(sumData('jumlah_dr_pjanak')));
        $(api.column(64).footer()).html(pct(st2('jasa_dr_umum'), sj2));
        $(api.column(65).footer()).html(fmt(sumData('jumlah_dr_umum')));
        $(api.column(66).footer()).html(pct(st2('jasa_pr_luar'), sj2));
        $(api.column(67).footer()).html(fmt(sumData('jumlah_pr_luar')));
        $(api.column(68).footer()).html(pct(st2('jasa_farmasi'), sj2));
        $(api.column(69).footer()).html(fmt(sumData('jumlah_farmasi')));
        $(api.column(70).footer()).html(pct(st2('total_dokter_lab'), sj2));
        $(api.column(71).footer()).html(fmt(sumData('jumlah_dokter_lab')));
        $(api.column(72).footer()).html(pct(st2('total_petugas_lab'), sj2));
        $(api.column(73).footer()).html(fmt(sumData('jumlah_analis_lab')));
        $(api.column(74).footer()).html(pct(st2('total_dokter_radiologi'), sj2));
        $(api.column(75).footer()).html(fmt(sumData('jumlah_dokter_radiologi')));
        $(api.column(76).footer()).html(pct(st2('total_petugas_radiologi'), sj2));
        $(api.column(77).footer()).html(fmt(sumData('jumlah_radiografer')));
        $(api.column(78).footer()).html(pct(st2('total_non_medis'), sj2));
        $(api.column(79).footer()).html(fmt(sumData('jumlah_non_medis')));
      }
    });
  });

  function loadData() {
    if (currentTab === 'pasien') {
      if (table) table.ajax.reload();
    } else {
      if (tableRekap) tableRekap.ajax.reload();
      else initTableRekap();
    }
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