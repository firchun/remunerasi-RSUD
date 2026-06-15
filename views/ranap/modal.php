 <!-- Modal pilih kolom -->
 <div id="modalKolom" class="hidden fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center px-4">
   <div class="bg-white rounded-xl p-6 w-96">
     <h3 class="text-lg font-bold mb-4">Pilih Kolom yang Tampil</h3>
     <div id="columnToggles" class="grid grid-cols-1 gap-2 max-h-96 overflow-y-auto">
       <!-- Checkbox akan di-generate otomatis -->
     </div>
     <div class="mt-4 flex justify-end gap-2">
       <button onclick="document.getElementById('modalKolom').classList.add('hidden')"
         class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Tutup</button>
     </div>
   </div>
 </div>
 <div id="modalUpload" class="hidden fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center px-4">
   <div class="bg-white rounded-2xl shadow-lg p-6 w-full max-w-lg">

     <h2 class="text-xl font-semibold mb-4">Upload CSV BPJS RANAP</h2>

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
 <!-- MODAL -->
 <div id="modalRawat"
   class="hidden fixed inset-0 bg-black/20 flex items-center justify-center z-50 backdrop-blur-sm px-3">
   <div class="bg-white w-full   rounded-xl  p-6 relative border border-green-700  ">

     <!-- Tombol Close Bulat Rapi -->
     <button onclick="closeModal()" class="absolute -top-3 -right-3 bg-red-500 text-white w-8 h-8 flex items-center justify-center 
             rounded-full shadow hover:bg-red-600 transition text-xl">
       ✕
     </button>
     <h2 class="text-xl font-bold mb-4 border-b">
       Detail SEP : <b id="title_no_sep"></b>
     </h2>
     <div class="mt-3 ">
       <div class="max-h-[70vh] overflow-y-auto pr-2">
         <h2 class="text-lg my-2 font-bold"> Riwayat Tindakan</h2>
         <div class="overflow-x-auto">
           <table class="w-full border border-gray-300 display " id="tabelDetailTindakan">
             <thead class="bg-green-600 text-white ">
               <tr>
                 <th>No</th>
                 <th>Nama Tindakan</th>
                 <th>Dokter</th>
                 <th>Perawat</th>
                 <th>Material</th>
                 <th>BHP</th>
                 <th>Dokter</th>
                 <th>Perawat</th>
                 <th>Manajemen</th>
                 <th>Total</th>
               </tr>
             </thead>
             <tbody></tbody>
           </table>
         </div>
         <h2 class="text-lg my-2 font-bold"> Riwayat Obat</h2>
         <div class="overflow-x-auto">
           <table id="tabelDetailObat" class="w-full border mt-4">
             <thead class="bg-green-700 text-white">
               <tr>
                 <th>No</th>
                 <th>Nama Obat</th>
                 <th>Jumlah</th>
                 <th>Biaya Obat</th>
                 <th>Embalase</th>
                 <th>Tuslah</th>
               </tr>
             </thead>
             <tbody></tbody>
           </table>
         </div>
         <h2 class="text-lg my-2 font-bold"> Riwayat Laboratorium</h2>
         <div class="overflow-x-auto">
           <table class="w-full border border-gray-300 display " id="tabelDetailLab">
             <thead class="bg-green-600 text-white ">
               <tr>
                 <th>No</th>
                 <th>Nama Tindakan</th>
                 <th>Nama Dokter</th>
                 <th>Perujuk</th>
                 <th>Dokter</th>
                 <th>Petugas</th>
                 <th>manajemen</th>
                 <th>Total</th>
               </tr>
             </thead>
             <tbody></tbody>
           </table>
         </div>
         <h2 class="text-lg my-2 font-bold"> Riwayat Radiologi</h2>
         <div class="overflow-x-auto">
           <table class="w-full border border-gray-300 display " id="tabelDetailRadiologi">
             <thead class="bg-green-600 text-white ">
               <tr>
                 <th>No</th>
                 <th>Nama Tindakan</th>
                 <th>Nama Dokter</th>
                 <th>Perujuk</th>
                 <th>Dokter</th>
                 <th>Petugas</th>
                 <th>manajemen</th>
                 <th>Total</th>
               </tr>
             </thead>
             <tbody></tbody>
           </table>
         </div>
       </div>
     </div>
   </div>
 </div>