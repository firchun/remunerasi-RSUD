<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$query_pj = "SELECT * FROM penjab WHERE status = '1' ORDER BY kd_pj";
$result_pj = mysqli_query($koneksi, $query_pj);
?>
<?php
$pageTitle = 'Data BPJS - RSUD MERAUKE';
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
<style>
.dt-button.buttons-excel.buttons-html5 {
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

.dt-button.buttons-excel.buttons-html5:hover {
  background-color: #15803d !important;
}

#tableBPJS tbody td {
  padding: 8px 12px !important;
  line-height: 1.4 !important;
  border: 0.5px solid #d1d5db;
  vertical-align: middle !important;
}

.status-badge {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  display: inline-block;
  white-space: nowrap;
}

.status-ada {
  background-color: #d4edda;
  color: #155724;
}

.status-tidak-ada {
  background-color: #f8d7da;
  color: #721c24;
}

.status-ranap {
  background-color: #d1ecf1;
  color: #0c5460;
}

.status-ralan {
  background-color: #fff3cd;
  color: #856404;
}

.info-card {
  background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
  color: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.info-card h3 {
  font-size: 14px;
  opacity: 0.9;
  margin-bottom: 8px;
}

.info-card p {
  font-size: 24px;
  font-weight: bold;
}
</style>
EOT;
$rootPath = '../';
require_once '../layouts/header.php';
?>
        <div class="flex justify-end my-3">
          <button class="px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700"
            onclick="document.getElementById('modalUpload').classList.remove('hidden')">
            Upload CSV BPJS
          </button>
        </div>
        <!-- Filter Section -->
        <div class="bg-white rounded-2xl border border-green-700 p-6 mb-6">
          <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
            <i
              class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
            Filter Data
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai</label>
              <input type="datetime-local" id="tgl1"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Selesai</label>
              <input type="datetime-local" id="tgl2"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
              <select id="status_lanjut"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                <option value="semua">Semua</option>
                <option value="Ralan">Rawat Jalan</option>
                <option value="Ranap">Rawat Inap</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Pencarian Manual</label>
              <input type="text" id="tcari" placeholder="No. Rawat, Pasien, SEP..."
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
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

        <!-- Info Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div class="info-card">
            <h3>Total Data</h3>
            <p id="totalData">0</p>
          </div>
          <div class="info-card">
            <h3>Total Klaim BPJS</h3>
            <p id="totalBPJS">Rp 0</p>
          </div>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-2xl border border-green-700 p-6">
          <h3 id="periodeInfo" class="text-lg font-bold text-green-800 mb-4">
            Data Klaim BPJS
          </h3>

          <div class="overflow-x-auto">
            <table id="tableBPJS" class="display w-full">
              <thead class="bg-green-800 text-white">
                <tr>
                  <th class="px-2 text-left">No.</th>
                  <th class="px-2 text-left">No. Rawat</th>
                  <th class="px-2 text-left">No. RM</th>
                  <th class="px-2 text-left">Nama Pasien</th>
                  <th class="px-2 text-left">No. SEP</th>
                  <th class="px-2 text-left">Tgl Registrasi</th>
                  <th class="px-2 text-center">Status</th>
                  <th class="px-2 text-left">Ruang/Poli</th>
                  <th class="px-2 text-left">Dokter</th>
                  <th class="px-2 text-right">Total BPJS</th>
                </tr>
              </thead>
              <tbody></tbody>
              <tfoot class="bg-green-800 font-bold text-white">
                <tr>
                  <th colspan="9" class="text-right px-2">TOTAL</th>
                  <th class="text-right px-2" id="foot-total-bpjs">Rp 0</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
  <div id="modalUpload" class="hidden fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center px-4">
    <div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-lg">

      <h2 class="text-xl font-semibold mb-4">Upload CSV BPJS RAJAL</h2>

      <form action="<?= $baseUrl ?>/api/upload_bpjs.php" method="POST" enctype="multipart/form-data">
        <label class="block mb-3 text-sm font-medium">Pilih File CSV</label>
        <input type="file" name="file" accept=".csv" class="w-full border rounded p-2 mb-4" required>

        <div class="flex justify-end space-x-2">
          <button type="button" onclick="document.getElementById('modalUpload').classList.add('hidden')"
            class="px-4 py-2 border border-gray-500 rounded-xl hover:bg-gray-200">
            Batal
          </button>

          <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-xl hover:bg-green-700">
            Upload
          </button>
        </div>
      </form>

    </div>
  </div>

  <script>
  let table;

  $(document).ready(function() {
    // Set default tanggal (awal bulan - akhir bulan)
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    $('#tgl1').val(formatDateTime(firstDay));
    $('#tgl2').val(formatDateTime(lastDay));

    // Initialize DataTable
    table = $('#tableBPJS').DataTable({
      processing: true,
      serverSide: true,
      ajax: {
        url: window.BASE_URL + '/api/bpjs.php',
        type: 'POST',
        data: function(d) {
          d.tgl1 = $('#tgl1').val();
          d.tgl2 = $('#tgl2').val();
          d.status_lanjut = $('#status_lanjut').val();
          d.kd_pj = $('#kd_pj').val();
          d.tcari = $('#tcari').val();
        },
        dataSrc: function(json) {
          // Update info cards
          $('#totalData').text(json.recordsFiltered.toLocaleString('id-ID'));

          // Calculate total BPJS
          let totalBPJS = 0;
          json.data.forEach(function(row) {
            totalBPJS += parseFloat(row.total_bpjs || 0);
          });
          $('#totalBPJS').text('Rp ' + Math.round(totalBPJS).toLocaleString('id-ID'));

          // Update footer
          $('#foot-total-bpjs').text('Rp ' + Math.round(totalBPJS).toLocaleString('id-ID'));

          return json.data;
        }
      },
      dom: '<"flex justify-between items-center mb-4"lB>rtip',
      buttons: [{
        extend: 'excel',
        text: '<i class="fas fa-file-excel mr-2"></i>Export Excel',
        title: 'Laporan Data BPJS - ' + new Date().toLocaleDateString('id-ID'),
        exportOptions: {
          columns: ':visible'
        }
      }],
      lengthMenu: [
        [10, 25, 50, 100],
        [10, 25, 50, 100]
      ],
      pageLength: 25,
      scrollX: true,
      autoWidth: false,
      columns: [{
          data: null,
          render: (d, t, r, meta) => meta.row + meta.settings._iDisplayStart + 1
        },
        {
          data: 'no_rawat'
        },
        {
          data: 'no_rkm_medis'
        },
        {
          data: 'nm_pasien'
        },
        {
          data: 'no_sep'
        },
        {
          data: null,
          render: function(data) {
            return data.tgl_registrasi;
          }
        },
        {
          data: 'status_lanjut',
          className: 'text-center',
          render: function(data) {
            let badgeClass = data === 'Ranap' ? 'status-ranap' : 'status-ralan';
            return `<span class="status-badge ${badgeClass}">${data}</span>`;
          }
        },
        {
          data: 'nm_bangsal'
        },
        {
          data: 'nm_dokter'
        },

        {
          data: 'total_bpjs',
          className: 'text-right',
          render: function(data) {
            return 'Rp ' + Math.round(parseFloat(data || 0)).toLocaleString('id-ID');
          }
        },

      ],
      language: {
        processing: "Memuat data...",
        lengthMenu: "Tampilkan _MENU_ data",
        zeroRecords: "Data tidak ditemukan",
        info: "Halaman _PAGE_ dari _PAGES_",
        infoEmpty: "Tidak ada data",
        search: "Cari:",
        paginate: {
          first: "Pertama",
          last: "Terakhir",
          next: "›",
          previous: "‹"
        }
      },
      order: [
        [5, 'desc']
      ]
    });
  });

  function formatDateTime(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
  }

  function loadData() {
    table.ajax.reload();
  }

  function resetFilter() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);

    $('#tgl1').val(formatDateTime(firstDay));
    $('#tgl2').val(formatDateTime(lastDay));
    $('#status_lanjut').val('semua');
    $('#kd_pj').val('');
    $('#tcari').val('');

    table.ajax.reload();
  }
  </script>
<?php require_once '../layouts/footer.php'; ?>