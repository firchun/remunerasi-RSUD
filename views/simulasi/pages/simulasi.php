<div class="fade-enter">
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
    <div>
      <h2 class="text-2xl font-bold text-gray-800">Simulasi Perhitungan Remunerasi</h2>
    </div>
    <div class="flex gap-2">
      <button onclick="addRecord('pegawai')"
        class="px-3 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors inline-flex items-center gap-1">
        <i class="fas fa-plus"></i> Pegawai
      </button>
      <button onclick="editRecord('set_index',1)"
        class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors inline-flex items-center gap-1">
        <i class="fas fa-sliders-h"></i> Atur Index
      </button>
      <button onclick="editRecord('rumus',1)"
        class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors inline-flex items-center gap-1">
        <i class="fas fa-cog"></i> Rumus
      </button>
      <div class="relative" id="colsDropdown">
        <button onclick="toggleColsDropdown()"
          class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors inline-flex items-center gap-1">
          <i class="fas fa-table"></i> Kolom <i class="fas fa-chevron-down text-xs"></i>
        </button>
        <div id="colsMenu"
          class="absolute right-0 mt-2 w-52 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50 hidden">
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_kr" onchange="toggleColumn('v_kr')"> Koreksi</label>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_p" onchange="toggleColumn('v_p')"> Pend</label>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_rs" onchange="toggleColumn('v_rs')"> Resiko</label>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_re" onchange="toggleColumn('v_re')"> Emergensi</label>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_sk" onchange="toggleColumn('v_sk')"> Kepeg</label>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_jr" onchange="toggleColumn('v_jr')"> Jabatan</label>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_sf" onchange="toggleColumn('v_sf')"> Sertifikat</label>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_bk" onchange="toggleColumn('v_bk')"> B.Kerja</label>
          <div class="border-t border-gray-100 my-1"></div>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_ik" onchange="toggleColumn('v_ik')"> IKI</label>
          <label class="flex items-center gap-2 px-4 py-2 text-sm hover:bg-gray-50 cursor-pointer"><input
              type="checkbox" data-col="v_ab" onchange="toggleColumn('v_ab')"> Penilaian</label>
        </div>
      </div>
      <button onclick="printSimulasi()"
        class="px-3 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors inline-flex items-center gap-1">
        <i class="fas fa-print"></i> Cetak
      </button>
    </div>
  </div>

  <?php
  $db = getSimulasiDB();
  $idx = $db->query("SELECT nama, rating FROM set_index")->fetchAll();
  ?>
  <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-2 mb-6">
    <?php foreach ($idx as $r): ?>
      <div class="bg-[#065f46] rounded-lg border border-gray-200 px-3 py-2 text-center">
        <p class="text-xs text-white truncate"><?= htmlspecialchars($r['nama']) ?></p>
        <p class="text-sm font-bold text-white">x<?= $r['rating'] ?></p>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="p-4 border-b border-gray-100">
      <input data-search="simulasi" placeholder="Cari pegawai..."
        class="w-full sm:w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none">
    </div>
    <div class="overflow-x-auto">
      <table class="w-full text-xs">
        <thead class="bg-gray-50 text-left font-medium text-gray-500 uppercase tracking-wider" id="simulasiThead">
        </thead>
        <tbody data-ajax-table="simulasi" class="divide-y divide-gray-100"></tbody>
      </table>
    </div>
    <div class="pagination px-4 py-3 border-t border-gray-100"></div>
  </div>
</div>