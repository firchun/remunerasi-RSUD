<?php
require_once '../../config/conf.php';
$koneksi = bukakoneksi();
$result_pj = mysqli_query($koneksi, "SELECT * FROM penjab WHERE status = '1' ORDER BY kd_pj");
$result_poli = mysqli_query($koneksi, "SELECT * FROM poliklinik WHERE status = '1' ORDER BY nm_poli");
$result_bangsal = mysqli_query($koneksi, "SELECT * FROM v_bangsal_grup ORDER BY nm_bangsal");
$pageTitle = 'Laporan Gabungan - RSUD MERAUKE';
$extraHead = '
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <style>
    #tabelBulanan { border-collapse: collapse; min-width: 1200px; width: 100%; }
    #tabelBulanan thead { position: sticky; top: 0; z-index: 2; }
    #tabelBulanan th, #tabelBulanan td { padding: 6px 8px; border: 0.5px solid #d1d5db; font-size: 14px; white-space: nowrap; }
    #tabelBulanan thead th { background-color: #166534; color: white; font-weight: 600; }
    #tabelBulanan tbody tr:nth-child(even) { background-color: #f9fafb; }
    #tabelBulanan tbody tr:hover { background-color: #f0fdf4; }
    #tabelBulanan tfoot th { background-color: #166534; color: white; border: 1px solid #065f46; padding: 8px 4px; font-weight: bold; font-size: 11px; white-space: nowrap; }
    #tabelBulanan tfoot th:not([colspan]) { min-width: 100px; }
    .num { text-align: right; }
    .center { text-align: center; }
    .left { text-align: left; }
    .jenis-btn { padding: 8px 20px; border-radius: 8px; border: 1px solid #d1d5db; cursor: pointer; font-weight: 500; transition: all .2s; background: white; }
    .jenis-btn.active { background: #166534; color: white; border-color: #166534; }
    .jenis-btn:hover { border-color: #166534; }
  </style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>
        <div class="bg-white rounded-2xl border border-green-700 p-6 mb-6">
          <h3 class="text-lg font-semibold mb-4 text-green-800 flex items-center">
            <i class="fas fa-filter mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
            Filter Laporan
          </h3>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Dari Bulan</label>
              <input type="month" id="bulan_awal" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">Sampai Bulan</label>
              <input type="month" id="bulan_akhir" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
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
              <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Rawat</label>
              <div class="flex gap-2">
                <button class="jenis-btn active" data-jenis="ralan" onclick="pilihJenis('ralan')">Rawat Jalan</button>
                <button class="jenis-btn" data-jenis="ranap" onclick="pilihJenis('ranap')">Rawat Inap</button>
              </div>
            </div>

            <div id="filter-poli">
              <label class="block text-sm font-medium text-gray-700 mb-2">Poliklinik <span class="text-xs text-gray-400">(tahan Ctrl/Cmd untuk pilih banyak)</span></label>
              <select id="kd_poli" multiple size="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                <?php while ($r = mysqli_fetch_assoc($result_poli)): ?>
                  <option value="<?= $r['kd_poli'] ?>"><?= $r['nm_poli'] ?></option>
                <?php endwhile; ?>
              </select>
            </div>

            <div id="filter-bangsal" style="display:none">
              <label class="block text-sm font-medium text-gray-700 mb-2">Bangsal <span class="text-xs text-gray-400">(tahan Ctrl/Cmd untuk pilih banyak)</span></label>
              <select id="kd_bangsal" multiple size="6" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                <?php while ($r = mysqli_fetch_assoc($result_bangsal)): ?>
                  <option value="<?= $r['kd_bangsal'] ?>"><?= $r['nm_bangsal'] ?></option>
                <?php endwhile; ?>
              </select>
            </div>

          </div>

          <div class="flex gap-2">
            <button onclick="loadData()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition flex items-center gap-2">
              <i class="fas fa-search"></i>Tampilkan Data
            </button>
            <button onclick="resetFilter()" class="border border-gray-600 text-gray-600 px-6 py-2 rounded-xl hover:bg-gray-200 transition flex items-center gap-2">
              <i class="fas fa-redo"></i>Reset
            </button>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-green-700 p-6">
          <div class="flex justify-between items-center mb-4">
            <h3 id="periodeInfo" class="text-lg font-bold text-green-800">Periode: -</h3>
            <div class="flex gap-2 items-center">
              <span id="jenisLabel" class="text-sm px-3 py-1 rounded-full bg-green-100 text-green-800 font-semibold">RAWAT JALAN</span>
              <button onclick="exportExcel()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl text-sm transition flex items-center gap-1"><i class="fas fa-file-excel"></i>Excel</button>
              <button onclick="exportPDF()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-xl text-sm transition flex items-center gap-1"><i class="fas fa-file-pdf"></i>PDF</button>
            </div>
          </div>

          <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table id="tabelBulanan">
              <thead>
                <tr>
                  <th class="center" style="width:50px">No</th>
                  <th class="left" id="header-unit">Poliklinik</th>
                  <th class="left">Bulan</th>
                  <th class="num" style="width:90px">Pasien</th>
                  <th class="num">Tindakan</th>
                  <th class="num">Obat</th>
                  <th class="num">Jasa Farmasi</th>
                  <th class="num">Radiologi</th>
                  <th class="num">Laboratorium</th>
                  <th class="num">Grand Total</th>
                </tr>
              </thead>
              <tbody id="tableBody"></tbody>
              <tfoot>
                <tr>
                  <th colspan="3" class="center">TOTAL</th>
                  <th class="num" id="foot-pasien">0</th>
                  <th class="num" id="foot-tindakan">0</th>
                  <th class="num" id="foot-obat">0</th>
                  <th class="num" id="foot-farmasi">0</th>
                  <th class="num" id="foot-radiologi">0</th>
                  <th class="num" id="foot-laboratorium">0</th>
                  <th class="num" id="foot-grand">0</th>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

  <script>
    let currentJenis = 'ralan';

    const BULAN_NAMA = {
      '01': 'Januari', '02': 'Februari', '03': 'Maret', '04': 'April',
      '05': 'Mei', '06': 'Juni', '07': 'Juli', '08': 'Agustus',
      '09': 'September', '10': 'Oktober', '11': 'November', '12': 'Desember'
    };

    function fmt(n) {
      return n != null ? Math.round(n).toLocaleString('id-ID') : '-';
    }

    function pilihJenis(jenis) {
      currentJenis = jenis;
      $('.jenis-btn').removeClass('active');
      $(`.jenis-btn[data-jenis="${jenis}"]`).addClass('active');
      $('#jenisLabel').text(jenis === 'ralan' ? 'RAWAT JALAN' : 'RAWAT INAP');
      $('#header-unit').text(jenis === 'ranap' ? 'Bangsal' : 'Poliklinik');

      $('#filter-poli').toggle(jenis === 'ralan');
      $('#filter-bangsal').toggle(jenis === 'ranap');

      if ($('#bulan_awal').val()) loadData();
    }

    function setFoot(id, val) {
      document.getElementById(id).textContent = fmt(val);
    }

    function loadData() {
      const bulan_awal = $('#bulan_awal').val();
      const bulan_akhir = $('#bulan_akhir').val();
      if (!bulan_awal) { alert('Pilih Dari Bulan terlebih dahulu.'); return; }
      if (!bulan_akhir) { alert('Pilih Sampai Bulan terlebih dahulu.'); return; }

      const kd_pj = $('#kd_pj').val();
      const kd_poli = ($('#kd_poli').val() || []).join(',');
      const kd_bangsal = ($('#kd_bangsal').val() || []).join(',');

      $('#tableBody').html('<tr><td colspan="10" class="center" style="color:#6b7280;padding:24px">Memuat data...</td></tr>');

      $.ajax({
        url: window.BASE_URL + '/api/get_report_gabungan.php',
        type: 'POST',
        data: { bulan_awal, bulan_akhir, kd_pj, jenis: currentJenis, kd_poli, kd_bangsal },
        success: function(response) {
          try {
            const res = typeof response === 'string' ? JSON.parse(response) : response;
            if (!res.success) { alert('Gagal memuat data'); return; }

            $('#periodeInfo').text('Periode: ' + res.periode);
            const raw = res.data;
            let html = '';
            let counter = 1;
            let gp = 0, gt = 0, go = 0, gf = 0, gr = 0, gl = 0, gtot = 0;

            raw.sort(function(a, b) {
              const ua = (a.nama_unit || a.nm_poli || '').toLowerCase();
              const ub = (b.nama_unit || b.nm_poli || '').toLowerCase();
              if (ua !== ub) return ua.localeCompare(ub);
              return (a.bulan || '').localeCompare(b.bulan || '');
            });

            raw.forEach(function(item) {
              const unit = item.nama_unit || item.nm_poli || '-';
              const bln = item.bulan;
              const m = bln ? bln.match(/^(\d{4})-(\d{2})$/) : null;
              const namaBulan = m ? (BULAN_NAMA[m[2]] + ' ' + m[1]) : '-';
              const pasien = parseInt(item.jumlah_kunjungan || 0);
              const t = parseFloat(item.total_tindakan || 0);
              const o = parseFloat(item.total_obat || 0);
              const f = parseFloat(item.total_jasa_farmasi || item.jasa_farmasi || 0);
              const r = parseFloat(item.total_radiologi || 0);
              const l = parseFloat(item.total_lab || 0);
              const tot = parseFloat(item.grand_total || 0);

              html += '<tr>' +
                '<td class="center">' + (counter++) + '</td>' +
                '<td class="left" style="font-weight:600">' + unit + '</td>' +
                '<td class="left">' + namaBulan + '</td>' +
                '<td class="num">' + fmt(pasien) + '</td>' +
                '<td class="num">' + fmt(t) + '</td>' +
                '<td class="num">' + fmt(o) + '</td>' +
                '<td class="num">' + fmt(f) + '</td>' +
                '<td class="num">' + fmt(r) + '</td>' +
                '<td class="num">' + fmt(l) + '</td>' +
                '<td class="num" style="font-weight:700">' + fmt(tot) + '</td>' +
                '</tr>';

              gp += pasien; gt += t; go += o; gf += f; gr += r; gl += l; gtot += tot;
            });

            $('#tableBody').html(html);
            setFoot('foot-pasien', gp);
            setFoot('foot-tindakan', gt);
            setFoot('foot-obat', go);
            setFoot('foot-farmasi', gf);
            setFoot('foot-radiologi', gr);
            setFoot('foot-laboratorium', gl);
            setFoot('foot-grand', gtot);
          } catch(e) { console.error(e); alert('Error: ' + e.message); }
        },
        error: function(xhr) { console.error(xhr.responseText); alert('Error memuat data'); }
      });
    }

    function resetFilter() {
      const now = new Date();
      const bulanIni = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
      $('#bulan_awal').val(bulanIni);
      $('#bulan_akhir').val(bulanIni);
      $('#kd_pj').val('');
      $('#kd_poli').val([]);
      $('#kd_bangsal').val([]);
      pilihJenis('ralan');
      loadData();
    }

    function exportExcel() {
      const table = document.getElementById('tabelBulanan');
      const wb = XLSX.utils.table_to_book(table, { sheet: 'Laporan' });
      XLSX.writeFile(wb, 'Laporan Gabungan Bulanan.xlsx');
    }

    function exportPDF() {
      const element = document.getElementById('tabelBulanan');
      const opt = {
        margin: 8,
        filename: 'Laporan Gabungan Bulanan.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
      };
      html2pdf().set(opt).from(element).save();
    }

    $(document).ready(function() {
      const now = new Date();
      const bulanIni = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
      $('#bulan_awal').val(bulanIni);
      $('#bulan_akhir').val(bulanIni);
      loadData();
    });
  </script>
<?php require_once '../layouts/footer.php'; ?>
