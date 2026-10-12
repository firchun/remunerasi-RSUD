<?php
$pageTitle = 'Evaluasi - RSUD MERAUKE';
$rootPath = '../';
require_once '../layouts/header.php';
?>
<div class="bg-white rounded-2xl border border-green-700 p-6 mb-4 max-w-3xl">
  <h3 class="text-lg font-semibold mb-5 text-green-800 flex items-center">
    <i class="fas fa-clipboard-list mr-2 w-[40px] h-[40px] rounded-full bg-green-200 flex items-center justify-center"></i>
    Filter Evaluasi
  </h3>

  <form id="formEvaluasi" onsubmit="return handleGenerate(event)">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-5">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Bulan Awal</label>
        <select id="bulan_awal" required
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
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
        <label class="block text-sm font-medium text-gray-700 mb-2">Bulan Akhir</label>
        <select id="bulan_akhir" required
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
          <option value="1">Januari</option>
          <option value="2">Februari</option>
          <option value="3" selected>Maret</option>
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
        <select id="tahun" required
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
          <?php
          $currentYear = date('Y');
          for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
            echo "<option value=\"$y\">$y</option>";
          }
          ?>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Jenis Pelayanan</label>
        <select id="jenis_layanan" required
          class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
          <option value="ralan">Rawat Jalan</option>
          <option value="ranap">Rawat Inap</option>
        </select>
      </div>
    </div>

    <div class="flex gap-2">
      <button type="submit" id="btnGenerate"
        class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition flex items-center gap-2">
        <i class="fas fa-file-excel"></i> Generate & Download
      </button>
    </div>
  </form>
</div>

<script>
function handleGenerate(e) {
  e.preventDefault();
  const bulanAwal = document.getElementById('bulan_awal').value;
  const bulanAkhir = document.getElementById('bulan_akhir').value;
  const tahun = document.getElementById('tahun').value;
  const jenisLayanan = document.getElementById('jenis_layanan').value;

  if (parseInt(bulanAwal) > parseInt(bulanAkhir)) {
    alert('Bulan awal tidak boleh lebih besar dari bulan akhir.');
    return false;
  }

  const params = new URLSearchParams({
    bulan_awal: bulanAwal,
    bulan_akhir: bulanAkhir,
    tahun: tahun,
    jenis_layanan: jenisLayanan
  });

  window.location.href = window.BASE_URL + '/api/evaluasi_export.php?' + params.toString();
  return false;
}
</script>
<?php require_once '../layouts/footer.php'; ?>
