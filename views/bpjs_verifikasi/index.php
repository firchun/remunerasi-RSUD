<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$pageTitle = 'BPJS Verifikasi - RSUD MERAUKE';
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  table.dataTable td, table.dataTable th { color: #1f2937 !important; }
  #tabelData th, #tabelData td { white-space: nowrap; }
  #tabelData tbody td { padding: 2px 4px !important; line-height: 1.4 !important; border: 0.5px solid #d1d5db; }
  .editable-cell { cursor: text; min-height: 24px; }
  .editable-cell:focus { outline: 2px solid #16a34a; background: #f0fdf4; }
  #previewSection { display: none; }
  .drop-zone { border: 2px dashed #d1d5db; border-radius: 12px; padding: 40px; text-align: center; cursor: pointer; transition: all 0.3s; }
  .drop-zone:hover, .drop-zone.dragover { border-color: #16a34a; background: #f0fdf4; }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 p-6 mb-6">
  <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
    <i class="fas fa-file-upload mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter & Upload
  </h3>

  <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Bulan</label>
      <select id="filter_bulan" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
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
      <label class="block text-sm font-medium text-gray-700 mb-2">Jenis</label>
      <select id="filter_jenis" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <option value="ralan">Ralan</option>
        <option value="ranap">Ranap</option>
      </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">Tahun</label>
      <select id="filter_tahun" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
        <?php $thn = date('Y'); for ($y = $thn; $y >= $thn - 5; $y--) echo "<option value='$y'>$y</option>"; ?>
      </select>
    </div>
    <div class="flex items-end">
      <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2.5 rounded-xl transition w-full">
        <i class="fas fa-search mr-2"></i>Cari Data
      </button>
    </div>
    <div class="flex items-end">
      <button onclick="document.getElementById('modalUpload').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-xl transition w-full">
        <i class="fas fa-upload mr-2"></i>Upload PDF
      </button>
    </div>
  </div>
</div>

<div id="previewSection" class="bg-white rounded-2xl border border-green-700 p-6 mb-6">
  <div class="flex justify-between items-center mb-4">
    <h3 class="text-lg font-semibold text-green-800">
      <i class="fas fa-eye mr-2 text-green-600"></i>Preview Data
      <span id="rowCount" class="text-sm font-normal text-gray-500 ml-2"></span>
    </h3>
    <div class="flex gap-2">
      <button id="btnSimpan" onclick="simpanData()" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-xl transition">
        <i class="fas fa-save mr-2"></i>Simpan ke Database
      </button>
      <button onclick="batalPreview()" class="bg-gray-500 hover:bg-gray-600 text-white px-5 py-2 rounded-xl transition">
        <i class="fas fa-times mr-2"></i>Batal
      </button>
    </div>
  </div>
  <div class="overflow-x-auto max-h-[600px] overflow-y-auto border rounded-lg">
    <table id="tabelData" class="w-full text-sm">
      <thead class="bg-green-800 text-white sticky top-0">
        <tr>
          <th class="px-3 py-2 text-left">No</th>
          <th class="px-3 py-2 text-left">No. SEP</th>
          <th class="px-3 py-2 text-left">Tgl. Verifikasi</th>
          <th class="px-3 py-2 text-right">Riil RS</th>
          <th class="px-3 py-2 text-right">Diajukan</th>
          <th class="px-3 py-2 text-right">Disetujui</th>
        </tr>
      </thead>
      <tbody id="previewBody"></tbody>
    </table>
  </div>
</div>

<div id="dataSection" class="bg-white rounded-2xl border border-green-700 p-6" style="display:none">
  <h3 class="text-lg font-semibold mb-4 text-green-800">
    <i class="fas fa-database mr-2 text-green-600"></i>Data Tersimpan
  </h3>
  <div class="overflow-x-auto">
    <table id="tabelTersimpan" class="display w-full">
      <thead class="bg-green-800 text-white">
        <tr>
          <th class="px-3 py-2 text-left">ID</th>
          <th class="px-3 py-2 text-left">File</th>
          <th class="px-3 py-2 text-left">Bulan</th>
          <th class="px-3 py-2 text-left">Tahun</th>
          <th class="px-3 py-2 text-left">Jenis</th>
          <th class="px-3 py-2 text-right">Jumlah Data</th>
          <th class="px-3 py-2 text-left">Tanggal Upload</th>
          <th class="px-3 py-2 text-center">Aksi</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
  </div>
</div>

<div id="modalUpload" class="hidden fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center px-4 z-50">
  <div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-2xl">
    <h2 class="text-xl font-semibold mb-4">Upload PDF BPJS Verifikasi</h2>
    <form id="formUpload">
      <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-3"></i>
        <p class="text-gray-600">Seret file PDF ke sini atau klik untuk memilih</p>
        <p class="text-xs text-gray-400 mt-1">Format: PDF (format BPJS)</p>
        <input type="file" id="fileInput" name="file" accept=".pdf" class="hidden" onchange="onFileSelect(event)">
      </div>
      <div id="uploadProgress" class="hidden mt-4">
        <div class="flex items-center gap-3 text-gray-600">
          <i class="fas fa-spinner fa-spin text-green-600"></i>
          <span>Memproses PDF, mohon tunggu...</span>
        </div>
      </div>
    </form>
    <div class="flex justify-end space-x-2 mt-4">
      <button onclick="document.getElementById('modalUpload').classList.add('hidden')" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition">
        Batal
      </button>
    </div>
  </div>
</div>

<div id="modalDetail" class="hidden fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center px-4 z-50">
  <div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-5xl max-h-[80vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-semibold">Detail Data</h2>
      <button onclick="document.getElementById('modalDetail').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
        <i class="fas fa-times text-xl"></i>
      </button>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-sm border">
        <thead class="bg-green-800 text-white">
          <tr>
            <th class="px-3 py-2 text-left">No</th>
            <th class="px-3 py-2 text-left">No. SEP</th>
            <th class="px-3 py-2 text-left">Tgl. Verifikasi</th>
            <th class="px-3 py-2 text-right">Riil RS</th>
            <th class="px-3 py-2 text-right">Diajukan</th>
            <th class="px-3 py-2 text-right">Disetujui</th>
          </tr>
        </thead>
        <tbody id="detailBody"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
let currentRows = [];
let currentFilename = '';
let uploadId = null;
let tableTersimpan = null;

$('#filter_bulan').val(new Date().getMonth() + 1);

function onFileSelect(e) {
  const file = e.target.files[0];
  if (!file) return;
  uploadFile(file);
}

const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
  e.preventDefault();
  dropZone.classList.remove('dragover');
  const file = e.dataTransfer.files[0];
  if (file) uploadFile(file);
});

function uploadFile(file) {
  const formData = new FormData();
  formData.append('file', file);

  document.getElementById('uploadProgress').classList.remove('hidden');
  document.getElementById('dropZone').classList.add('opacity-50');

  $.ajax({
    url: BASE_URL + '/api/upload_bpjs_verifikasi.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function(res) {
      document.getElementById('uploadProgress').classList.add('hidden');
      document.getElementById('dropZone').classList.remove('opacity-50');
      document.getElementById('modalUpload').classList.add('hidden');

      if (res.success) {
        currentRows = res.rows;
        currentFilename = res.filename;
        tampilkanPreview(res.rows);
      } else {
        alert('Gagal: ' + (res.message || 'Unknown error'));
      }
    },
    error: function() {
      document.getElementById('uploadProgress').classList.add('hidden');
      document.getElementById('dropZone').classList.remove('opacity-50');
      alert('Gagal mengupload file');
    }
  });
}

function tampilkanPreview(rows) {
  const tbody = document.getElementById('previewBody');
  tbody.innerHTML = '';

  rows.forEach((r, i) => {
    const tr = document.createElement('tr');
    tr.className = i % 2 === 0 ? 'bg-white' : 'bg-gray-50';
    tr.innerHTML = `
      <td class="px-3 py-1 border">${i + 1}</td>
      <td class="px-3 py-1 border editable-cell" contenteditable="true" data-idx="${i}" data-field="no_sep">${r.no_sep}</td>
      <td class="px-3 py-1 border editable-cell" contenteditable="true" data-idx="${i}" data-field="tgl_verifikasi">${r.tgl_verifikasi}</td>
      <td class="px-3 py-1 border editable-cell text-right" contenteditable="true" data-idx="${i}" data-field="riil_rs">${r.riil_rs.toLocaleString('id-ID')}</td>
      <td class="px-3 py-1 border editable-cell text-right" contenteditable="true" data-idx="${i}" data-field="diajukan">${r.diajukan.toLocaleString('id-ID')}</td>
      <td class="px-3 py-1 border editable-cell text-right" contenteditable="true" data-idx="${i}" data-field="disetujui">${r.disetujui.toLocaleString('id-ID')}</td>
    `;
    tbody.appendChild(tr);
  });

  document.getElementById('rowCount').textContent = '(' + rows.length + ' baris)';
  document.getElementById('previewSection').style.display = 'block';
  document.getElementById('dataSection').style.display = 'none';

  bindEditEvents();
}

function bindEditEvents() {
  document.querySelectorAll('.editable-cell').forEach(el => {
    el.addEventListener('blur', function() {
      const idx = parseInt(this.dataset.idx);
      const field = this.dataset.field;
      let val = this.innerText.trim();

      if (field === 'riil_rs' || field === 'diajukan' || field === 'disetujui') {
        const clean = val.replace(/[^\d]/g, '');
        currentRows[idx][field] = parseInt(clean) || 0;
        this.innerText = (parseInt(clean) || 0).toLocaleString('id-ID');
      } else {
        currentRows[idx][field] = val;
      }
    });

    el.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        this.blur();
      }
    });
  });
}

function simpanData() {
  if (!currentRows.length) return;

  const bulan = document.getElementById('filter_bulan').value;
  const tahun = document.getElementById('filter_tahun').value;
  const jenis = document.getElementById('filter_jenis').value;
  const btn = document.getElementById('btnSimpan');
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Menyimpan...';

  $.ajax({
    url: BASE_URL + '/api/save_bpjs_verifikasi.php',
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({
      bulan: parseInt(bulan),
      tahun: parseInt(tahun),
      jenis: jenis,
      filename: currentFilename,
      rows: currentRows
    }),
    dataType: 'json',
    success: function(res) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save mr-2"></i>Simpan ke Database';
      if (res.success) {
        uploadId = res.id;
        alert('Data berhasil disimpan!');
        document.getElementById('previewSection').style.display = 'none';
        currentRows = [];
        loadData();
      } else {
        alert('Gagal menyimpan: ' + (res.message || 'Unknown error'));
      }
    },
    error: function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-save mr-2"></i>Simpan ke Database';
      alert('Gagal menyimpan data');
    }
  });
}

function batalPreview() {
  currentRows = [];
  currentFilename = '';
  document.getElementById('previewSection').style.display = 'none';
}

function loadData() {
  const bulan = document.getElementById('filter_bulan').value;
  const tahun = document.getElementById('filter_tahun').value;
  const jenis = document.getElementById('filter_jenis').value;

  if (tableTersimpan) {
    tableTersimpan.destroy();
    tableTersimpan = null;
  }

  $.ajax({
    url: BASE_URL + '/api/get_bpjs_verifikasi.php',
    type: 'GET',
    data: { bulan, tahun, jenis },
    dataType: 'json',
    success: function(res) {
      if (res.success) {
        const tbody = document.querySelector('#tabelTersimpan tbody');
        tbody.innerHTML = '';
        if (res.data.length === 0) {
          document.getElementById('dataSection').style.display = 'none';
          return;
        }
        document.getElementById('dataSection').style.display = 'block';

        const namaBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        res.data.forEach(d => {
          const rowCount = Array.isArray(d.data) ? d.data.length : 0;
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td class="px-3 py-2 border">${d.id}</td>
            <td class="px-3 py-2 border">${d.filename}</td>
            <td class="px-3 py-2 border">${namaBulan[d.bulan] || d.bulan}</td>
            <td class="px-3 py-2 border">${d.tahun}</td>
            <td class="px-3 py-2 border uppercase">${d.jenis || '-'}</td>
            <td class="px-3 py-2 border text-right">${rowCount}</td>
            <td class="px-3 py-2 border">${d.created_at}</td>
            <td class="px-3 py-2 border text-center">
              <button onclick="lihatDetail(${d.id})" class="text-blue-600 hover:text-blue-800 mr-2" title="Lihat Detail">
                <i class="fas fa-eye"></i>
              </button>
              <button onclick="hapusData(${d.id})" class="text-red-600 hover:text-red-800" title="Hapus">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          `;
          tbody.appendChild(tr);
        });

        tableTersimpan = $('#tabelTersimpan').DataTable({
          pageLength: 25,
          order: [[0, 'desc']],
          language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/id.json' }
        });
      }
    }
  });
}

function lihatDetail(id) {
  $.ajax({
    url: BASE_URL + '/api/get_bpjs_verifikasi.php',
    type: 'GET',
    data: {},
    dataType: 'json',
    success: function(res) {
      if (res.success) {
        const item = res.data.find(d => d.id == id);
        if (!item || !item.data) return;
        const tbody = document.getElementById('detailBody');
        tbody.innerHTML = '';
        item.data.forEach((r, i) => {
          const tr = document.createElement('tr');
          tr.className = i % 2 === 0 ? 'bg-white' : 'bg-gray-50';
          tr.innerHTML = `
            <td class="px-3 py-1 border">${i + 1}</td>
            <td class="px-3 py-1 border">${r.no_sep}</td>
            <td class="px-3 py-1 border">${r.tgl_verifikasi}</td>
            <td class="px-3 py-1 border text-right">${(r.riil_rs || 0).toLocaleString('id-ID')}</td>
            <td class="px-3 py-1 border text-right">${(r.diajukan || 0).toLocaleString('id-ID')}</td>
            <td class="px-3 py-1 border text-right">${(r.disetujui || 0).toLocaleString('id-ID')}</td>
          `;
          tbody.appendChild(tr);
        });
        document.getElementById('modalDetail').classList.remove('hidden');
      }
    }
  });
}

function hapusData(id) {
  if (!confirm('Yakin ingin menghapus data ini?')) return;
  $.ajax({
    url: BASE_URL + '/api/delete_bpjs_verifikasi.php',
    type: 'POST',
    contentType: 'application/json',
    data: JSON.stringify({ id }),
    dataType: 'json',
    success: function(res) {
      if (res.success) {
        alert('Data berhasil dihapus');
        loadData();
      } else {
        alert('Gagal: ' + (res.message || 'Unknown error'));
      }
    }
  });
}

loadData();
</script>

<?php require_once '../layouts/footer.php'; ?>
