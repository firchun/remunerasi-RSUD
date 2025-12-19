<?php
require_once '../config/conf.php';
$koneksi = bukakoneksi();

$query_pj = "SELECT * FROM penjab WHERE status = '1' ORDER BY kd_pj";
$result_pj = mysqli_query($koneksi, $query_pj);
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Laporan Bulanan Per Kamar - RSUD MERAUKE</title>
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
  <style>
  .dt-button.buttons-excel.buttons-html5,
  .dt-button.buttons-pdf.buttons-html5 {
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

  #tabelBulanan tbody td {
    padding: 6px 8px !important;
    margin: 0 !important;
    line-height: 1.4 !important;
    height: auto;
    border: 0.5px solid #d1d5db;
    vertical-align: middle !important;
  }

  .section-header {
    background-color: #065f46 !important;
  }
  </style>
</head>

<body class="bg-gray-100">
  <div class="flex h-screen overflow-hidden">
    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="fixed top-0 left-0 w-full z-50 backdrop-blur-md bg-white/60 shadow-sm">
        <div class="flex items-center px-4 py-3">
          <a href="../index.php">
            <img src="https://absenrsudmerauke.rifill.id/assetsdata/img/logorsud.png" alt="Logo RSUD Merauke"
              class="w-16 h-16 mr-4">
          </a>
          <div>
            <h2 class="text-xl font-bold text-green-800">
              Laporan Bulanan Per Kamar - RSUD MERAUKE
            </h2>
          </div>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto px-6 pb-6 pt-[100px]">
        <div class="bg-white rounded-2xl border border-green-700 p-6 mb-6">
          <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
            <i
              class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
            Filter Periode
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Bulan & Tahun</label>
              <input type="month" id="bulan"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
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
            <button onclick="loadData()"
              class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition">
              <i class="fas fa-search mr-2"></i>Tampilkan Data
            </button>
            <button onclick="resetFilter()"
              class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition">
              <i class="fas fa-redo mr-2"></i>Reset
            </button>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-green-700 p-6">
          <h3 id="periodeInfo" class="text-lg font-bold text-green-800 mb-4">
            Periode: -
          </h3>

          <div class="overflow-x-auto">
            <table id="tabelBulanan" class="display w-full">
              <thead class="bg-green-800 text-white">
                <tr>
                  <th class="px-2 text-center align-middle">No.</th>
                  <th class="px-2 text-left align-middle">Gedung</th>
                  <th class="px-2 text-right align-middle">Kunjungan</th>
                  <th class="px-2 text-right align-middle">Biaya Kamar</th>

                  <!-- TINDAKAN -->
                  <th class="px-2 text-right">Dokter (T)</th>
                  <th class="px-2 text-right">Perawat (T)</th>
                  <th class="px-2 text-right">non-medis (T)</th>
                  <!-- FARMASI -->
                  <th class="px-2 text-right">Racikan</th>
                  <th class="px-2 text-right">Non-Racikan</th>
                  <th class="px-2 text-right">Operasi</th>
                  <th class="px-2 text-right">Jasa Farmasi</th>
                  <th class="px-2 text-right">Total Obat</th>

                  <!-- LAB -->
                  <th class="px-2 text-right">Material</th>
                  <th class="px-2 text-right">Tarif Dr</th>
                  <th class="px-2 text-right">Tarif Petugas</th>
                  <th class="px-2 text-right">Menejemen</th>
                  <th class="px-2 text-right">Total Lab</th>
                  <!-- RADIOLOGI -->
                  <th class="px-2 text-right">Material</th>
                  <th class="px-2 text-right">Tarif Dr</th>
                  <th class="px-2 text-right">Tarif Petugas</th>
                  <th class="px-2 text-right">Menejemen</th>
                  <th class="px-2 text-right">Total Rad</th>
                  <!-- OPERASI & TOTAL -->
                  <th class="px-2 text-right align-middle">Total Operasi</th>
                  <th class="px-2 text-right align-middle ">GRAND TOTAL</th>
                </tr>

              </thead>
              <tbody></tbody>
              <tfoot class="bg-green-800 font-bold text-white">
                <tr>
                  <th colspan="2" class="text-right">TOTAL</th>
                  <th class="text-right" id="foot-kunjungan">0</th>
                  <th class="text-right" id="foot-biaya-kamar">0</th>

                  <!-- TINDAKAN -->

                  <th class="text-right" id="foot-tindakan-dr">0</th>
                  <th class="text-right" id="foot-tindakan-pr">0</th>
                  <th class="text-right" id="foot-menejemen">0</th>

                  <!-- FARMASI -->
                  <th class="text-right" id="foot-racikan">0</th>
                  <th class="text-right" id="foot-nonracikan">0</th>
                  <th class="text-right" id="foot-resep-operasi">0</th>
                  <th class="text-right" id="foot-jasa-farmasi">0</th>
                  <th class="text-right" id="foot-obat">0</th>

                  <!-- LAB -->
                  <th class="text-right" id="foot-material-lab">0</th>
                  <th class="text-right" id="foot-dokter-lab">0</th>
                  <th class="text-right" id="foot-petugas-lab">0</th>
                  <th class="text-right" id="foot-menejemen-lab">0</th>
                  <th class="text-right" id="foot-lab">0</th>

                  <!-- RADIOLOGI -->
                  <th class="text-right" id="foot-material-rad">0</th>
                  <th class="text-right" id="foot-dokter-rad">0</th>
                  <th class="text-right" id="foot-petugas-rad">0</th>
                  <th class="text-right" id="foot-menejemen-rad">0</th>
                  <th class="text-right" id="foot-radiologi">0</th>

                  <!-- OPERASI & TOTAL -->
                  <th class="text-right" id="foot-operasi">0</th>
                  <th class="text-right " id="foot-grand">0</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </main>
    </div>
  </div>

  <script>
  let table;

  $(document).ready(function() {
    const now = new Date();
    const bulanIni = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    $('#bulan').val(bulanIni);

    table = $('#tabelBulanan').DataTable({

      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{
          extend: 'excel',
          text: '<i class="fas fa-file-excel mr-2"></i>Export Excel',
          title: 'Laporan Bulanan Per Kamar - RSUD MERAUKE'
        },
        {
          extend: 'pdfHtml5',
          text: '<i class="fas fa-file-pdf mr-2"></i>Export PDF',
          orientation: 'landscape',
          pageSize: 'A3',
          title: 'Laporan Bulanan Per Kamar - RSUD MERAUKE',
          customize: function(doc) {
            doc.defaultStyle.fontSize = 6;
            doc.styles.tableHeader.fontSize = 7;
          }
        }
      ],
      lengthMenu: [
        [10, 25, 50, -1],
        [10, 25, 50, "Semua"]
      ],
      pageLength: 25,
      processing: true,
      scrollX: true,
      columns: [{
          data: null,
          className: 'text-center',
          render: (d, t, r, meta) => meta.row + 1
        },
        {
          data: 'nama_gedung',
          className: 'text-left font-semibold'
        },
        {
          data: 'jumlah_kunjungan',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_biaya_kamar',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },

        // TINDAKAN

        {
          data: 'total_tindakan_dr',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_tindakan_pr',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },

        {
          data: 'total_menejemen',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },

        // FARMASI
        {
          data: 'jumlah_resep_racikan',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'jumlah_resep_non_racikan',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'jumlah_resep_operasi',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_jasa_farmasi',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_obat',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },

        // LAB
        {
          data: 'total_material_lab',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_dokter_lab',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_petugas_lab',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_menejemen_lab',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_lab',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },

        // RADIOLOGI
        {
          data: 'total_material_radiologi',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_dokter_radiologi',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_petugas_radiologi',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_menejemen_radiologi',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'total_radiologi',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },

        // OPERASI & GRAND TOTAL
        {
          data: 'total_operasi',
          className: 'text-right',
          render: $.fn.dataTable.render.number(',', '.', 0)
        },
        {
          data: 'grand_total',
          className: 'text-right font-bold',
          render: $.fn.dataTable.render.number(',', '.', 0)
        }
      ]
    });

    loadData();
  });

  function loadData() {
    const bulan = $('#bulan').val();
    const kd_pj = $('#kd_pj').val();

    $.ajax({
      url: '../api/get_monthly_report_ranap.php',
      type: 'POST',
      data: {
        bulan,
        kd_pj
      },
      success: function(response) {
        console.log('Response:', response);

        try {
          const res = JSON.parse(response);

          if (res.success) {
            $('#periodeInfo').text('Periode: ' + res.periode);
            table.clear();
            table.rows.add(res.data);
            table.draw();
            updateFooter(res.data);
          } else {
            alert('Gagal memuat data');
          }
        } catch (e) {
          console.error('Parse error:', e);
          console.error('Raw response:', response);
          alert('Error parsing data: ' + e.message);
        }
      },
      error: function(xhr) {
        alert('Error loading data');
        console.error('Status:', xhr.status);
        console.error('Response:', xhr.responseText);
      }
    });
  }

  function updateFooter(data) {
    const sum = (key) => data.reduce((a, b) => a + parseFloat(b[key] || 0), 0);
    const fmt = (n) => Math.round(n).toLocaleString('id-ID');

    $('#foot-kunjungan').text(fmt(sum('jumlah_kunjungan')));
    $('#foot-biaya-kamar').text(fmt(sum('total_biaya_kamar')));

    // TINDAKAN
    $('#foot-material').text(fmt(sum('total_material')));
    $('#foot-bhp').text(fmt(sum('total_bhp')));
    $('#foot-tindakan-dr').text(fmt(sum('total_tindakan_dr')));
    $('#foot-tindakan-pr').text(fmt(sum('total_tindakan_pr')));
    $('#foot-kso').text(fmt(sum('total_kso')));
    $('#foot-menejemen').text(fmt(sum('total_menejemen')));

    // FARMASI
    $('#foot-racikan').text(fmt(sum('jumlah_resep_racikan')));
    $('#foot-nonracikan').text(fmt(sum('jumlah_resep_non_racikan')));
    $('#foot-resep-operasi').text(fmt(sum('jumlah_resep_operasi')));
    $('#foot-jasa-farmasi').text(fmt(sum('total_jasa_farmasi')));
    $('#foot-obat').text(fmt(sum('total_obat')));

    // LAB
    $('#foot-material-lab').text(fmt(sum('total_material_lab')));
    $('#foot-dokter-lab').text(fmt(sum('total_dokter_lab')));
    $('#foot-petugas-lab').text(fmt(sum('total_petugas_lab')));
    $('#foot-menejemen-lab').text(fmt(sum('total_menejemen_lab')));
    $('#foot-lab').text(fmt(sum('total_lab')));

    // RADIOLOGI
    $('#foot-material-rad').text(fmt(sum('total_material_radiologi')));
    $('#foot-dokter-rad').text(fmt(sum('total_dokter_radiologi')));
    $('#foot-petugas-rad').text(fmt(sum('total_petugas_radiologi')));
    $('#foot-menejemen-rad').text(fmt(sum('total_menejemen_radiologi')));
    $('#foot-radiologi').text(fmt(sum('total_radiologi')));

    // OPERASI & GRAND
    $('#foot-operasi').text(fmt(sum('total_operasi')));
    $('#foot-grand').text(fmt(sum('grand_total')));
  }

  function resetFilter() {
    const now = new Date();
    const bulanIni = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    $('#bulan').val(bulanIni);
    $('#kd_pj').val('');
    loadData();
  }
  </script>
</body>

</html>