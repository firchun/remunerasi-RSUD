<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$query_pj = "SELECT * FROM penjab WHERE status = '1' ORDER BY kd_pj";
$result_pj = mysqli_query($koneksi, $query_pj);
?>
<?php
$pageTitle = 'Laporan Bulanan RANAP - RSUD MERAUKE';
$extraHead = <<<'EOT'
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
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
.dt-button.buttons-colvis {
  background-color: #16a34a !important;
  color: white !important;
  border: none !important;
  padding: 12px 20px !important;
  border-radius: 8px !important;
  font-size: 14px !important;
  font-weight: 600 !important;
  cursor: pointer;
  transition: 0.25s ease-in-out;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.dt-button.buttons-pdf.buttons-html5 {
  background-color: #dc2626 !important;
}

.dt-button.buttons-colvis {
  background-color: #2563eb !important;
}

.dt-button.buttons-colvis:hover {
  background-color: #1d4ed8 !important;
}

/* Fix DataTables ColVis dropdown styling under Tailwind */
div.dt-button-collection {
  position: absolute !important;
  background-color: white !important;
  border: 1px solid #d1d5db !important;
  border-radius: 0.5rem !important;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
  padding: 0.5rem !important;
  z-index: 9999 !important;
  display: flex !important;
  flex-direction: column !important;
  gap: 4px !important;
  min-width: 180px !important;
}

div.dt-button-collection button.dt-button {
  background: none !important;
  color: #374151 !important;
  border: none !important;
  padding: 8px 12px !important;
  text-align: left !important;
  font-size: 13px !important;
  font-weight: 500 !important;
  border-radius: 6px !important;
  cursor: pointer !important;
  display: flex !important;
  align-items: center !important;
  justify-content: space-between !important;
  transition: background-color 0.2s !important;
}

div.dt-button-collection button.dt-button:hover {
  background-color: #f3f4f6 !important;
}

div.dt-button-collection button.dt-button.active {
  background-color: #dbeafe !important;
  color: #1e40af !important;
  font-weight: 600 !important;
}

#tabelBulanan tbody td {
  padding: 4px 6px !important;
  margin: 0 !important;
  line-height: 1.4 !important;
  height: auto;
  border: 0.5px solid #d1d5db;
  vertical-align: middle !important;
  font-size: 14px !important;
}

#tabelBulanan tbody tr.farmasi-row {
  background-color: #fef3c7 !important;
  font-weight: 600;
}

#tabelBulanan tbody tr.lab-row {
  background-color: #f0fdf4 !important;
  font-weight: 600;
}

#tabelBulanan tbody tr.rad-row {
  background-color: #ecfeff !important;
  font-weight: 600;
}

#tabelBulanan tbody tr.operasi-row {
  background-color: #fce7f3 !important;
  font-weight: 600;
}

#tabelBulanan tfoot tr th {
  background-color: #166534 !important;
  color: white !important;
  border: 1px solid #065f46 !important;
  padding: 8px 4px !important;
  font-weight: bold !important;
  font-size: 11px !important;
}
</style>
EOT;
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 3 mb-3">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter Periode
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Filter</label>
      <select id="filter_type" onchange="toggleFilterType()"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <option value="bulan">Per Bulan</option>
        <option value="tahun">Per Tahun</option>
      </select>
    </div>

    <div id="container-bulan">
      <label class="block text-sm font-medium text-gray-700 mb-2">Bulan & Tahun</label>
      <input type="month" id="bulan"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
    </div>

    <div id="container-tahun" class="hidden">
      <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
      <select id="tahun"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
      <select id="kd_pj"
        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <option value="">Semua Cara Bayar</option>
        <?php while ($row = mysqli_fetch_assoc($result_pj)): ?>
          <option value="<?= $row['kd_pj'] ?>"><?= $row['png_jawab'] ?></option>
        <?php endwhile; ?>
      </select>
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

<div class="bg-white rounded-2xl border border-green-700 p-3">
  <h3 id="periodeInfo" class="text-lg font-bold text-green-800 mb-4">
    Periode: -
  </h3>

  <div class="overflow-x-auto">
    <table id="tabelBulanan" class="display w-full">
      <thead>
        <tr>
          <th rowspan="3">No</th>
          <th rowspan="3">Tahun</th>
          <th rowspan="3">Ruangan</th>
          <th rowspan="3">Kunjungan</th>
          <th rowspan="3">Biaya Kamar</th>
        </tr>
        <tr>
          <th rowspan="2">Sarana</th>
          <th colspan="2">Jasa Pelayanan</th>
          <th rowspan="2">Non Medis</th>
          <th rowspan="2">Grand Total</th>
        </tr>
        <tr>
          <th>Dokter</th>
          <th>Perawat</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <th colspan="3" class="text-right">TOTAL</th>
          <th class="text-right" id="foot-kunjungan">0</th>
          <th class="text-right" id="foot-biaya-kamar">0</th>
          <th class="text-right" id="foot-sarana">0</th>
          <th class="text-right" id="foot-dokter">0</th>
          <th class="text-right" id="foot-perawat">0</th>
          <th class="text-right" id="foot-menejemen">0</th>
          <th class="text-right" id="foot-grand">0</th>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<script>
  let table;

  function toggleFilterType() {
    const filterType = $('#filter_type').val();
    if (filterType === 'tahun') {
      $('#container-bulan').addClass('hidden');
      $('#container-tahun').removeClass('hidden');
    } else {
      $('#container-bulan').removeClass('hidden');
      $('#container-tahun').addClass('hidden');
    }
  }

  $(document).ready(function () {
    const now = new Date();
    const bulanIni = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    $('#bulan').val(bulanIni);

    table = $('#tabelBulanan').DataTable({
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{
        extend: 'excel',
        text: '<i class="fas fa-file-excel mr-2"></i>Export Excel',
        title: 'Laporan Bulanan Per Kamar - RSUD MERAUKE',
        exportOptions: {
          columns: ':visible'
        }
      },
      {
        extend: 'pdfHtml5',
        text: '<i class="fas fa-file-pdf mr-2"></i>Export PDF',
        orientation: 'landscape',
        pageSize: 'A4',
        title: 'Laporan Bulanan Per Kamar - RSUD MERAUKE',
        exportOptions: {
          columns: ':visible'
        },
        customize: function (doc) {
          doc.defaultStyle.fontSize = 7;
          doc.styles.tableHeader.fontSize = 8;
        }
      },
      {
        extend: 'colvis',
        text: '<i class="fas fa-columns mr-2"></i>Pilih Kolom'
      }
      ],
      lengthMenu: [
        [10, 25, 50, -1],
        [10, 25, 50, "Semua"]
      ],
      pageLength: 50,
      scrollX: true,
      autoWidth: false,
      paging: false,
      ordering: false,
      searching: true,
      info: false,
      columns: [{
        data: 'no',
        className: 'text-center'
      },
      {
        data: 'tahun',
        className: 'text-center'
      },
      {
        data: 'nama_gedung',
        className: 'text-left'
      },
      {
        data: 'jumlah_kunjungan',
        render: function (data, type) {
          if (type === 'display') {
            return data ? $.fn.dataTable.render.number(',', '.', 0).display(data) : '-';
          }
          return data;
        },
        className: 'text-right'
      },
      {
        data: 'total_biaya_kamar',
        render: function (data, type) {
          if (type === 'display') {
            return data ? $.fn.dataTable.render.number(',', '.', 0).display(data) : '-';
          }
          return data;
        },
        className: 'text-right'
      },
      {
        data: 'total_sarana',
        render: function (data, type) {
          if (type === 'display') {
            return data ? $.fn.dataTable.render.number(',', '.', 0).display(data) : '-';
          }
          return data;
        },
        className: 'text-right'
      },
      {
        data: 'total_dokter',
        render: function (data, type) {
          if (type === 'display') {
            return data ? $.fn.dataTable.render.number(',', '.', 0).display(data) : '-';
          }
          return data;
        },
        className: 'text-right'
      },
      {
        data: 'total_perawat',
        render: function (data, type) {
          if (type === 'display') {
            return data ? $.fn.dataTable.render.number(',', '.', 0).display(data) : '-';
          }
          return data;
        },
        className: 'text-right'
      },
      {
        data: 'total_menejemen',
        render: function (data, type) {
          if (type === 'display') {
            return data ? $.fn.dataTable.render.number(',', '.', 0).display(data) : '-';
          }
          return data;
        },
        className: 'text-right'
      },
      {
        data: 'grand_total',
        render: function (data, type) {
          if (type === 'display') {
            return data ? $.fn.dataTable.render.number(',', '.', 0).display(data) : '-';
          }
          return data;
        },
        className: 'text-right font-bold'
      }
      ]
    });

    loadData();
  });

  function loadData() {
    const filter_type = $('#filter_type').val();
    const bulan = $('#bulan').val();
    const tahun = $('#tahun').val();
    const kd_pj = $('#kd_pj').val();

    $.ajax({
      url: window.BASE_URL + '/api/get_monthly_report_ranap.php',
      type: 'POST',
      data: {
        filter_type,
        bulan,
        tahun,
        kd_pj
      },
      success: function (response) {
        console.log('Response:', response);

        try {
          const res = JSON.parse(response);

          if (res.success) {
            $('#periodeInfo').text('Periode: ' + res.periode);

            const transformedData = [];
            let counter = 1;
            const selectedTahun = $('#filter_type').val() === 'tahun' ? $('#tahun').val() : $('#bulan').val().split('-')[0];

            // Akumulasi untuk Farmasi, Lab, Radiologi, Operasi
            let totalFarmasi = {
              sarana: 0,
              dokter: 0,
              perawat: 0,
              menejemen: 0,
              total: 0
            };
            let totalLab = {
              sarana: 0,
              dokter: 0,
              perawat: 0,
              menejemen: 0,
              total: 0
            };
            let totalRad = {
              sarana: 0,
              dokter: 0,
              perawat: 0,
              menejemen: 0,
              total: 0
            };
            let totalOperasi = {
              sarana: 0,
              dokter: 0,
              perawat: 0,
              menejemen: 0,
              total: 0
            };

            // Tambahkan data ruangan
            res.data.forEach((item) => {
              // Hitung total sarana (tindakan material + BHP + KSO)
              const totalSarana = parseFloat(item.total_material || 0) +
                parseFloat(item.total_bhp || 0) +
                parseFloat(item.total_kso || 0);

              transformedData.push({
                no: counter++,
                tahun: selectedTahun,
                nama_gedung: item.nama_gedung,
                jumlah_kunjungan: item.jumlah_kunjungan,
                total_biaya_kamar: item.total_biaya_kamar,
                total_sarana: totalSarana,
                total_dokter: item.total_tindakan_dr,
                total_perawat: item.total_tindakan_pr,
                total_menejemen: item.total_menejemen,
                grand_total: item.grand_total,
                rowType: 'main'
              });

              // Akumulasi Farmasi
              totalFarmasi.sarana += parseFloat(item.jumlah_resep_racikan || 0) +
                parseFloat(item.jumlah_resep_non_racikan || 0) +
                parseFloat(item.jumlah_resep_operasi || 0);
              totalFarmasi.total += (parseFloat(item.jumlah_resep_racikan || 0) * 25000) + (parseFloat(item
                .jumlah_resep_non_racikan || 0) * 15000) + (parseFloat(item.jumlah_resep_operasi || 0) *
                  30000);

              // Akumulasi Lab
              totalLab.sarana += parseFloat(item.total_material_lab || 0);
              totalLab.dokter += parseFloat(item.total_dokter_lab || 0);
              totalLab.perawat += parseFloat(item.total_petugas_lab || 0);
              totalLab.menejemen += parseFloat(item.total_menejemen_lab || 0);
              totalLab.total += parseFloat(item.total_lab || 0);

              // Akumulasi Radiologi
              totalRad.sarana += parseFloat(item.total_material_radiologi || 0);
              totalRad.dokter += parseFloat(item.total_dokter_radiologi || 0);
              totalRad.perawat += parseFloat(item.total_petugas_radiologi || 0);
              totalRad.menejemen += parseFloat(item.total_menejemen_radiologi || 0);
              totalRad.total += parseFloat(item.total_radiologi || 0);

              // Akumulasi Operasi
              totalOperasi.total += parseFloat(item.total_operasi || 0);
            });

            // Tambahkan baris Farmasi
            transformedData.push({
              no: counter++,
              tahun: selectedTahun,
              nama_gedung: 'FARMASI',
              jumlah_kunjungan: totalFarmasi.sarana,
              total_biaya_kamar: null,
              total_sarana: null,
              total_dokter: null,
              total_perawat: null,
              total_menejemen: null,
              grand_total: totalFarmasi.total,
              rowType: 'farmasi'
            });


            // Tambahkan baris Laboratorium
            transformedData.push({
              no: counter++,
              tahun: selectedTahun,
              nama_gedung: 'LABORATORIUM',
              jumlah_kunjungan: null,
              total_biaya_kamar: null,
              total_sarana: totalLab.sarana,
              total_dokter: totalLab.dokter,
              total_perawat: totalLab.perawat,
              total_menejemen: totalLab.menejemen,
              grand_total: totalLab.total,
              rowType: 'lab'
            });

            // Tambahkan baris Radiologi
            transformedData.push({
              no: counter++,
              tahun: selectedTahun,
              nama_gedung: 'RADIOLOGI',
              jumlah_kunjungan: null,
              total_biaya_kamar: null,
              total_sarana: totalRad.sarana,
              total_dokter: totalRad.dokter,
              total_perawat: totalRad.perawat,
              total_menejemen: totalRad.menejemen,
              grand_total: totalRad.total,
              rowType: 'rad'
            });

            // Tambahkan baris Operasi
            transformedData.push({
              no: counter++,
              tahun: selectedTahun,
              nama_gedung: 'OPERASI',
              jumlah_kunjungan: null,
              total_biaya_kamar: null,
              total_sarana: null,
              total_dokter: null,
              total_perawat: null,
              total_menejemen: null,
              grand_total: totalOperasi.total,
              rowType: 'operasi'
            });

            table.clear();
            table.rows.add(transformedData);
            table.draw();

            updateFooter(res.data, totalFarmasi, totalLab, totalRad, totalOperasi);
          } else {
            alert('Gagal memuat data');
          }
        } catch (e) {
          console.error('Parse error:', e);
          alert('Error parsing data: ' + e.message);
        }
      },
      error: function (xhr) {
        alert('Error loading data');
        console.error(xhr.responseText);
      }
    });
  }

  function updateFooter(data, totalFarmasi, totalLab, totalRad, totalOperasi) {
    const sum = (key) => data.reduce((a, b) => a + parseFloat(b[key] || 0), 0);
    const fmt = (n) => Math.round(n).toLocaleString('id-ID');

    const totalKunjungan = sum('jumlah_kunjungan');
    const totalBiayaKamar = sum('total_biaya_kamar');

    const totalSarana = sum('total_material') + sum('total_bhp') + sum('total_kso') +
      totalFarmasi.sarana + totalLab.sarana + totalRad.sarana;

    const totalDokter = sum('total_tindakan_dr') + totalLab.dokter + totalRad.dokter;
    const totalPerawat = sum('total_tindakan_pr') + totalLab.perawat + totalRad.perawat;
    const totalMenejemen = sum('total_menejemen') + totalLab.menejemen + totalRad.menejemen;

    const totalGrand = sum('grand_total') + totalFarmasi.total + totalLab.total +
      totalRad.total + totalOperasi.total;

    $('#foot-kunjungan').text(fmt(totalKunjungan));
    $('#foot-biaya-kamar').text(fmt(totalBiayaKamar));
    $('#foot-sarana').text(fmt(totalSarana));
    $('#foot-dokter').text(fmt(totalDokter));
    $('#foot-perawat').text(fmt(totalPerawat));
    $('#foot-menejemen').text(fmt(totalMenejemen));
    $('#foot-grand').text(fmt(totalGrand));
  }

  function resetFilter() {
    $('#filter_type').val('bulan');
    toggleFilterType();
    const now = new Date();
    const bulanIni = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    $('#bulan').val(bulanIni);
    $('#tahun').val(now.getFullYear());
    $('#kd_pj').val('');
    loadData();
  }
</script>
<?php require_once '../layouts/footer.php'; ?>