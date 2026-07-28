<div class="fade-enter">
  <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
    <div>
      <h2 class="text-2xl font-bold text-gray-800">Master Data Tempat Tugas</h2>
      <p class="text-gray-500 text-sm mt-1">Data tempat tugas dengan index resiko dan emergensi</p>
    </div>
    <button onclick="addRecord('tempat_tugas')" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors inline-flex items-center gap-2">
      <i class="fas fa-plus"></i>Tambah
    </button>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="p-4 border-b border-gray-100">
      <input data-search="tempat_tugas" placeholder="Cari tempat tugas..." class="w-full sm:w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none">
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
          <tr><th class="px-4 py-3">#</th><th class="px-4 py-3">Tempat Tugas</th><th class="px-4 py-3">Index Resiko</th><th class="px-4 py-3">Index Emergensi</th><th class="px-4 py-3">Aksi</th></tr>
        </thead>
        <tbody data-ajax-table="tempat_tugas" class="divide-y divide-gray-100"></tbody>
      </table>
    </div>
    <div class="pagination px-4 py-3 border-t border-gray-100"></div>
  </div>
</div>
