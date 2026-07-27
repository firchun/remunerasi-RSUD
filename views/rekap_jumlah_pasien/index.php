<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$query_pj = "SELECT kd_pj, png_jawab FROM penjab where status = '1' ORDER BY kd_pj";
$result_pj = mysqli_query($koneksi, $query_pj);
$payment_types = [];
while ($row = mysqli_fetch_assoc($result_pj)) {
    $payment_types[] = $row;
}

?>
<?php
$pageTitle = 'Rekap Jumlah Pasien - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
<style>
.dt-button.buttons-excel.buttons-html5,
.dt-button.buttons-pdf.buttons-html5,
.dt-button.buttons-colvis { background-color: #16a34a !important; color: white !important; border: none !important; padding: 12px 20px !important; border-radius: 8px !important; font-size: 14px !important; font-weight: 600 !important; cursor: pointer; transition: 0.25s ease-in-out; display: inline-flex; align-items: center; gap: 6px; }
.dt-button.buttons-pdf.buttons-html5 { background-color: #dc2626 !important; }
.dt-button.buttons-colvis { background-color: #2563eb !important; }
.dt-button.buttons-colvis:hover { background-color: #1d4ed8 !important; }
div.dt-button-collection { position: absolute !important; background-color: white !important; border: 1px solid #d1d5db !important; border-radius: 0.5rem !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important; padding: 0.5rem !important; z-index: 9999 !important; display: flex !important; flex-direction: column !important; gap: 4px !important; min-width: 180px !important; }
div.dt-button-collection button.dt-button { background: none !important; color: #374151 !important; border: none !important; padding: 8px 12px !important; text-align: left !important; font-size: 13px !important; font-weight: 500 !important; border-radius: 6px !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: space-between !important; transition: background-color 0.2s !important; }
div.dt-button-collection button.dt-button:hover { background-color: #f3f4f6 !important; }
div.dt-button-collection button.dt-button.active { background-color: #dbeafe !important; color: #1e40af !important; font-weight: 600 !important; }

#tabelRekap tbody td { padding: 4px 6px !important; margin: 0 !important; line-height: 1.4 !important; height: auto; border: 0.5px solid #d1d5db; vertical-align: middle !important; font-size: 14px !important; }
#tabelRekap tbody tr.subtotal-ralan { background-color: #f0fdf4 !important; font-weight: 600; }
#tabelRekap tbody tr.subtotal-ranap { background-color: #ecfeff !important; font-weight: 600; }
#tabelRekap tbody tr.grand-total { background-color: #fef3c7 !important; font-weight: 700; }
#tabelRekap thead th { background-color: #166534 !important; color: white !important; border: 1px solid #065f46 !important; padding: 8px 4px !important; font-weight: bold !important; font-size: 12px !important; white-space: nowrap; }
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
      <select id="tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <?php
        $currentYear = date('Y');
        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
          echo "<option value=\"$y\">$y</option>";
        }
        ?>
      </select>
    </div>

    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Cara Bayar</label>
      <select id="kd_pj" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <option value="">Semua Cara Bayar</option>
        <?php foreach ($payment_types as $pt): ?>
          <option value="<?= $pt['kd_pj'] ?>"><?= htmlspecialchars($pt['png_jawab']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="mt-4 flex gap-2">
    <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition">
      <i class="fas fa-search mr-2"></i>Tampilkan Data
    </button>
    <button onclick="resetFilter()" class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition">
      <i class="fas fa-redo mr-2"></i>Reset
    </button>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <h3 id="periodeInfo" class="text-lg font-bold text-green-800 mb-4">Periode: -</h3>

  <div class="overflow-x-auto">
    <table id="tabelRekap" class="display w-full">
      <thead>
        <tr>
          <th>Bulan</th>
          <th>Pelayanan</th>
          <?php foreach ($payment_types as $pt): ?>
            <th class="text-right"><?= htmlspecialchars($pt['png_jawab']) ?></th>
          <?php endforeach; ?>
          <th class="text-right">Total</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<script>
  var paymentTypes = <?= json_encode($payment_types) ?>;
  var table;

  function buildColumns() {
    var cols = [
      { data: 'bulan', title: 'Bulan', className: 'text-left font-medium' },
      { data: 'pelayanan', title: 'Pelayanan', className: 'text-left' }
    ];
    paymentTypes.forEach(function(pt) {
      cols.push({
        data: 'pt_' + pt.kd_pj,
        title: pt.png_jawab,
        className: 'text-right',
        render: function(data, type) {
          if (type === 'display') {
            return data != null ? Number(data).toLocaleString('id-ID') : '-';
          }
          return data;
        }
      });
    });
    cols.push({
      data: 'total',
      title: 'Total',
      className: 'text-right font-bold',
      render: function(data, type) {
        if (type === 'display') {
          return data != null ? Number(data).toLocaleString('id-ID') : '-';
        }
        return data;
      }
    });
    return cols;
  }

  $(document).ready(function () {
    var now = new Date();
    $('#tahun').val(now.getFullYear());

    var columns = buildColumns();

    table = $('#tabelRekap').DataTable({
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{
        text: '<i class="fas fa-file-excel mr-2"></i>Export Excel (Ralan & Ranap)',
        className: 'btn-export-excel',
        action: function () {
          var tahun = $('#tahun').val();
          var kd_pj = $('#kd_pj').val();
          var url = window.BASE_URL + '/api/export_rekap_jumlah_pasien.php?tahun=' + tahun + '&kd_pj=' + kd_pj;
          window.location.href = url;
        }
      }, {
        extend: 'pdfHtml5',
        text: '<i class="fas fa-file-pdf mr-2"></i>Export PDF',
        orientation: 'landscape',
        pageSize: 'A4',
        title: 'Rekap Jumlah Pasien',
        exportOptions: { columns: ':visible' },
        customize: function (doc) {
          doc.defaultStyle.fontSize = 7;
          doc.styles.tableHeader.fontSize = 8;
          doc.styles.tableHeader.fillColor = '#166534';
        }
      }, {
        extend: 'colvis',
        text: '<i class="fas fa-columns mr-2"></i>Pilih Kolom'
      }],
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      pageLength: 50,
      scrollX: true,
      autoWidth: false,
      paging: false,
      ordering: false,
      searching: true,
      info: false,
      columns: columns
    });

    loadData();
  });

  function loadData() {
    var tahun = $('#tahun').val();
    var kd_pj = $('#kd_pj').val();

    $.ajax({
      url: window.BASE_URL + '/api/get_rekap_jumlah_pasien.php',
      type: 'POST',
      data: { tahun: tahun, kd_pj: kd_pj },
      success: function (response) {
        var res = JSON.parse(response);

        if (res.success) {
          $('#periodeInfo').text('Periode: ' + res.periode);

          table.clear();
          table.rows.add(res.data);
          table.draw();

          // Set row classes after draw
          $(table.rows().nodes()).each(function () {
            var rowData = table.row(this).data();
            if (rowData && rowData.rowType) {
              $(this).addClass(rowData.rowType);
            }
          });

          // Show/hide payment type columns based on filter
          paymentTypes.forEach(function(pt, idx) {
            var colIndex = idx + 2;
            var visible = (kd_pj === '') || (kd_pj === pt.kd_pj);
            table.column(colIndex).visible(visible);
          });
        } else {
          alert('Gagal memuat data');
        }
      },
      error: function (xhr) {
        alert('Error loading data: ' + xhr.statusText);
        console.error(xhr.responseText);
      }
    });
  }

  function resetFilter() {
    var now = new Date();
    $('#tahun').val(now.getFullYear());
    $('#kd_pj').val('');
    loadData();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>
