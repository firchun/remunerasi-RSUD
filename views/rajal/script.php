 <!-- DataTables Buttons -->
 <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

 <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
 <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
 <!-- PDF export -->
 <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
 <script>
   function toggleFilter() {

     const content = document.getElementById('filterContent');
     const icon = document.getElementById('filterIcon');

     if (content.style.maxHeight) {
       // collapse
       content.style.maxHeight = null;
       $("#filterContent").removeClass("hidden");
       icon.classList.remove('rotate-180');
     } else {
       icon.classList.add('rotate-180');
       // expand
       content.style.maxHeight = content.scrollHeight + "px";
       $("#filterContent").addClass("hidden");
     }
   }
 </script>
 <script>
   // modal
   $(document).on("click", ".openModal", function() {
     let no_rawat = $(this).data("rawat");
     let no_sep = $(this).data("sep");
     $("#title_no_sep").text(no_sep);
     loadDetailTindakan(no_rawat);
     loadDetailObat(no_rawat);
     loadDetailLab(no_rawat);
     $("#modalRawat").removeClass("hidden");
   });

   function exportCSV() {
     const params = new URLSearchParams({
       tgl1: $('#tgl1').val(),
       tgl2: $('#tgl2').val(),
       kd_poli: $('#kd_poli').val(),
       kd_pj: $('#kd_pj').val(),
       filter_sep: $('#filter_sep').val(),
       tcari: $('#tcari').val(),
       filter_bulan: $('#filter_bulan').val() || '',
       filter_tahun: $('#filter_tahun').val() || new Date().getFullYear()
     });
     window.open('../api/export_ralan.php?' + params.toString(), '_blank');
   }

   function loadDetailTindakan(no_rawat) {
     fetch("../api/get_detail_tindakan.php?no_rawat=" + no_rawat)
       .then(res => res.json())
       .then(data => {
         let tbody = document.querySelector("#tabelDetailTindakan tbody");
         tbody.innerHTML = "";

         data.forEach((item, index) => {
           tbody.innerHTML += `
          <tr class="border-b">
            <td class="text-center">${index + 1}</td>
            <td class="px-1">${item.nm_perawatan}</td>
            <td class="px-1">${item.nm_dokter}</td>
            <td class="px-1">${item.nama}</td>
            <td class="text-right px-1">${item.material}</td>
            <td class="text-right px-1">${item.bhp}</td>
            <td class="text-right px-1">${item.tarif_tindakandr}</td>
            <td class="text-right px-1">${item.tarif_tindakanpr}</td>
            <td class="text-right px-1">${item.menejemen}</td>
            <td class="text-right px-1 font-bold">${item.total_byrdrpr}</td>
          </tr>
        `;
         });
       });
   }

   function loadDetailLab(no_rawat) {
     fetch("../api/get_detail_lab.php?no_rawat=" + no_rawat)
       .then(res => res.json())
       .then(data => {

         let tbody = document.querySelector("#tabelDetailLab tbody");
         tbody.innerHTML = "";
         if (!data || data.length === 0) {
           $("#tabelDetailLab").addClass("hidden");
           // Tidak ada obat
           tbody.innerHTML = `
          <tr>
            <td colspan="8" class="text-center text-gray-500 py-2">
              Tidak ada lab
            </td>
          </tr>
        `;
           return;
         } else {
           $("#tabelDetailLab").removeClass("hidden");
         }

         data.forEach((item, index) => {
           tbody.innerHTML += `
          <tr class="border-b">
            <td class="text-center">${index + 1}</td>
            <td class="px-1">${item.nm_perawatan}</td>
            <td class="px-1">${item.nm_dokter}</td>
            <td class="text-right px-1">${item.tarif_perujuk}</td>
            <td class="text-right px-1">${item.tarif_tindakan_dokter}</td>
            <td class="text-right px-1">${item.tarif_tindakan_petugas}</td>
            <td class="text-right px-1">${item.menejemen}</td>
            <td class="text-right px-1">${item.biaya}</td>
          </tr>
        `;
         });
       });
   }

   function loadDetailObat(no_rawat) {

     fetch("../api/get_detail_obat.php?no_rawat=" + no_rawat)
       .then(res => res.json())
       .then(data => {
         let tbody = document.querySelector("#tabelDetailObat tbody");
         tbody.innerHTML = "";
         if (!data || data.length === 0) {
           // Tidak ada obat
           tbody.innerHTML = `
          <tr>
            <td colspan="6" class="text-center text-gray-500 py-2">
              Tidak ada obat
            </td>
          </tr>
        `;
           return;
         }
         data.forEach((item, index) => {
           tbody.innerHTML += `
          <tr class="border-b">
            <td class="text-center">${index + 1}</td>
            <td>${item.nama_brng}</td>
            <td class="text-right">${item.jml}</td>
            <td class="text-right">${item.biaya_obat}</td>
            <td class="text-right">${item.embalase}</td>
            <td class="text-right">${item.tuslah}</td>
          </tr>
        `;
         });
       });
   }

   function closeModal() {
     $("#modalRawat").addClass("hidden");
   }
   //   modal
   let table;
   $('#filter_bulan, #filter_tahun').on('change', function() {
     if ($('#filter_bulan').val()) {
       $('#tgl1').val('');
       $('#tgl2').val('');
     }
   });

   $('#tgl1, #tgl2').on('change', function() {
     if ($(this).val()) {
       $('#filter_bulan').val('');
     }
   });

   $(document).ready(function() {
     // Set default datetime (last 7 days to now)
     const now = new Date();
     const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);

     $('#tgl2').val(formatDateTime(now));
     $('#tgl1').val(formatDateTime(sevenDaysAgo));

     function hitungTotalBayar(row) {
       return (
         (parseFloat(row.total_biaya_rawat) || 0) +
         (parseFloat(row.total_obat_dan_ppn) || 0) +
         (parseFloat(row.total_lab) || 0) +
         (parseFloat(row.jasa_farmasi) || 0) +
         (parseFloat(row.total_radiologi) || 0)
       );
     }

     function hitungMedis85(row) {
       return totalJasa(row) * 85 / 100;
     }

     function totalJasa(row) {
       return (Math.round(row.total_biaya_rawat) -
         (Math.round(row.total_material) +
           Math.round(row.total_obat_dan_ppn)));
     }

     // Initialize DataTable
     table = $('#tabelTindakan').DataTable({
       processing: true,
       serverSide: true,
       scrollY: "500px",
       scrollX: true,
       scrollCollapse: true,
       dom: '<"flex justify-between items-center mb-4"lB>rtip',
       buttons: [{
           extend: 'excel',
           text: 'Export Excel',
           filename: function() {
             var poliText = $('#kd_poli option:selected').text();

             if (!poliText || poliText === "Semua Poliklinik") {
               poliText = "semua poli";
             }

             poliText = poliText.toLowerCase().replace(/\s+/g, '_');

             return 'tindakan_ralan_' + poliText;
           },
           title: function() {
             var poliText = $('#kd_poli option:selected').text();

             if (!poliText || poliText === "Semua Poliklinik") {
               poliText = "Semua Poliklinik";
             }

             return 'Laporan Tindakan Rawat Jalan - ' + poliText;
           },
         },
         {
           extend: 'pdfHtml5',
           text: 'Export PDF',
           orientation: 'landscape',
           pageSize: 'A4',
           customize: function(doc) {
             doc.defaultStyle.fontSize = 5;

             doc.styles.tableHeader.fontSize = 8;

             if (doc.content[0].text) {
               doc.content[0].fontSize = 10;
             }
           }
         }
       ],
       lengthMenu: [
         [10, 25, 50, 100, 300, 1000, 5000, 10000],
         [10, 25, 50, 100, 300, 1000, 5000, 10000]
       ],
       pageLength: 25,
       scrollX: true,
       autoWidth: false,

       ajax: {
         url: '../api/get_data_ralan.php',
         type: 'POST',
         dataSrc: function(json) {
           console.log("RAW JSON:", json);
           return json.data || json;
         },
         data: function(d) {
           d.tgl1 = $('#tgl1').val();
           d.tgl2 = $('#tgl2').val();
           d.kd_poli = $('#kd_poli').val();
           d.kd_pj = $('#kd_pj').val();
           d.tcari = $('#tcari').val();
           d.search_value = d.search.value;
           d.filter_bulan = $('#filter_bulan').val();
           d.filter_tahun = $('#filter_tahun').val();
         }
       },
       columns: [{
           data: null,
           className: 'text-center',
           render: function(data, type, row, meta) {
             return meta.row + 1; // auto increment
           }
         },
         {
           data: 'no_rawat',
           className: 'cursor-pointer text-blue-600 font-semibold',
           render: function(data, type, row) {
             return `<span class="openModal" data-rawat="${row.no_rawat}" data-sep="${row.no_sep}"> ${row.no_rawat}</span>`;
           }
         },
         {
           data: 'no_sep'
         },
         {
           data: 'no_rkm_medis'
         },
         {
           data: 'png_jawab'
         },
         {
           data: 'nm_dokter'
         },
         {
           data: 'dokter_tambahan'
         },
         {
           data: 'nm_poli'
         },
         //  tindakan
         {
           data: 'total_material'
         },
         {
           data: 'total_tindakan_dr'
         },
         {
           data: 'total_tindakan_pr'
         },
         {
           data: 'total_menejemen'
         },
         {
           data: 'total_biaya_rawat'
         },
         //  operasi
         {
           data: 'nm_perawatan'
         },
         {
           data: 'daftar_petugas_operasi',
           title: 'Petugas Operasi',
           render: function(data, type, row) {
             if (type !== 'display') {
               return data.replace(/<[^>]*>?/gm, ' ').trim() || '';
             }
             return data;
           }
         },
         {
           data: 'anastesi'
         },
         {
           data: 'total_jasa_sarana_rs'
         },
         {
           data: 'total_perina_operasi'
         },
         {
           data: 'total_onloop_operasi'
         },
         {
           data: 'total_bidan_operasi'
         },
         {
           data: 'total_dr_anestesi_operasi'
         },
         {
           data: 'total_asisten_anestesi_operasi'
         },
         {
           data: 'total_asisten_operator_operasi'
         },
         {
           data: 'total_operator_operasi'
         },
         {
           data: 'total_operasi'
         },
         //  obat
         {
           data: 'jumlah_resep_racikan'
         },
         //  {
         //    data: 'daftar_resep_racikan',
         //    title: 'Resep Racikan',
         //    render: function(data, type, row) {
         //      if (type !== 'display') return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
         //      return data ?? '-';
         //    }
         //  },
         {
           data: 'jumlah_resep_non_racikan'
         },
         //  {
         //    data: 'daftar_resep_non_racikan',
         //    title: 'Resep Non Racikan',
         //    render: function(data, type, row) {
         //      if (type !== 'display') return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
         //      return data ?? '-';
         //    }
         //  },
         {
           data: 'jumlah_resep_operasi'
         },
         //  {
         //    data: 'daftar_resep_operasi',
         //    title: 'Resep Operasi',
         //    render: function(data, type, row) {
         //      if (type !== 'display') return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
         //      return data ?? '-';
         //    }
         //  },
         {
           data: 'jasa_farmasi'
         },
         {
           data: 'total_obat',
           render: function(data) {
             if (!data) return 0;
             return Math.round(data);
           }
         },
         //  lab
         // {
         //   data: 'daftar_tindakan_lab',
         //   title: 'Tindakan Lab',
         //   render: function(data, type, row) {
         //     if (type !== 'display') return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
         //     return data ?? '-';
         //   }
         // },
         {
           data: 'total_material_lab'
         },
         {
           data: 'total_dokter_lab'
         },
         {
           data: 'total_petugas_lab'
         },
         {
           data: 'total_menejemen_lab'
         },
         {
           data: 'total_lab'
         },
         //  radiologi
         {
           data: 'nm_dokter_radiologi'
         },
         {
           data: 'tindakan_radiologi'
         },
         {
           data: 'total_material_radiologi'
         },
         {
           data: 'total_dokter_radiologi'
         },
         {
           data: 'total_petugas_radiologi'
         },
         {
           data: 'total_menejemen_radiologi'
         },
         {
           data: 'total_radiologi'
         },
         {
           data: null,
           render: function(data, type, row) {
             return Math.round(hitungTotalBayar(row));
           }
         },
         {
           data: 'total_bpjs',
           createdCell: function(td, cellData, rowData, row, col) {
             $(td).css('background-color', '#ddffdd'); // hijau muda
             $(td).css('color', '#080');
           }
         },
         // pembagian
         // {
         //   data: null,
         //   render: function(data, type, row) {
         //     return Math.round(totalJasa(row));
         //   },
         //   createdCell: function(td, cellData, rowData, row, col) {
         //     $(td).css('background-color', '#FFE5CC'); // hijau muda
         //     $(td).css('color', '#080');
         //   }
         // },
         // {
         //   data: null,
         //   render: function(data, type, row) {
         //     // Cek lab & radiologi
         //     let totalLab = parseFloat(row.total_lab) || 0;
         //     let totalRad = parseFloat(row.total_radiologi) || 0;

         //     // Jika salah satu bukan 0 → tampilkan "-"
         //     if (totalLab !== 0 || totalRad !== 0) {
         //       return "-";
         //     }
         //     return Math.round(hitungMedis85(row));
         //   },
         //   createdCell: function(td, cellData, rowData, row, col) {
         //     $(td).css('background-color', '#FFE5CC'); // hijau muda
         //     $(td).css('color', '#080');
         //   }
         // },

         // {
         //   data: null,
         //   render: function(data, type, row) {
         //     // Cek lab & radiologi
         //     let totalLab = parseFloat(row.total_lab) || 0;
         //     let totalRad = parseFloat(row.total_radiologi) || 0;

         //     // Jika salah satu bukan 0 → tampilkan "-"
         //     if (totalLab !== 0 || totalRad !== 0) {
         //       return "-";
         //     }

         //     let medis = hitungMedis85(row);
         //     let dokter60 = medis * 60 / 100;
         //     return Math.round(dokter60);
         //   },
         //   createdCell: function(td, cellData, rowData, row, col) {
         //     $(td).css('background-color', '#FFE5CC'); // hijau muda
         //     $(td).css('color', '#080');
         //   }
         // },
         // {
         //   data: null,
         //   render: function(data, type, row) {
         //     // Cek lab & radiologi
         //     let totalLab = parseFloat(row.total_lab) || 0;
         //     let totalRad = parseFloat(row.total_radiologi) || 0;

         //     // Jika salah satu bukan 0 → tampilkan "-"
         //     if (totalLab !== 0 || totalRad !== 0) {
         //       return "-";
         //     }

         //     let medis = hitungMedis85(row);
         //     let perawat40 = medis * 40 / 100;
         //     return Math.round(perawat40);
         //   },
         //   createdCell: function(td, cellData, rowData, row, col) {
         //     $(td).css('background-color', '#FFE5CC'); // hijau muda
         //     $(td).css('color', '#080');
         //   }
         // },
         // {
         //   data: null,
         //   render: function(data, type, row) {
         //     // Cek lab & radiologi
         //     let totalLab = parseFloat(row.total_lab) || 0;
         //     let totalRad = parseFloat(row.total_radiologi) || 0;

         //     // Jika salah satu bukan 0 → tampilkan "-"
         //     if (totalLab !== 0 || totalRad !== 0) {
         //       return "-";
         //     }
         //     let totalBayar = totalJasa(row);
         //     let nonmedis15 = totalBayar * 15 / 100;
         //     return Math.round(nonmedis15);
         //   },
         //   createdCell: function(td, cellData, rowData, row, col) {
         //     $(td).css('background-color', '#FFE5CC'); // hijau muda
         //     $(td).css('color', '#080');
         //   }
         // },
         // perhitunagn %
         // {
         //   data: null,
         //   render: function(data, type, row) {
         //     // Cek lab & radiologi
         //     let totalLab = parseFloat(row.total_lab) || 0;
         //     let totalRad = parseFloat(row.total_radiologi) || 0;

         //     // Jika salah satu bukan 0 → tampilkan "-"
         //     if (totalLab !== 0 || totalRad !== 0) {
         //       return "-";
         //     }
         //     let dokter60 = hitungMedis85(row) * 60 / 100;
         //     let hasil = (dokter60 / totalJasa(row));

         //     return hasil.toFixed(2);
         //   },
         //   createdCell: function(td, cellData, rowData, row, col) {
         //     $(td).css('background-color', '#FFE5CC'); // hijau muda
         //     $(td).css('color', '#080');
         //   }
         // },
         // {
         //   data: null,
         //   render: function(data, type, row) {
         //     // Cek lab & radiologi
         //     let totalLab = parseFloat(row.total_lab) || 0;
         //     let totalRad = parseFloat(row.total_radiologi) || 0;

         //     // Jika salah satu bukan 0 → tampilkan "-"
         //     if (totalLab !== 0 || totalRad !== 0) {
         //       return "-";
         //     }
         //     let medis40 = hitungMedis85(row) * 40 / 100;
         //     let hasil = (medis40 / totalJasa(row));;
         //     return hasil.toFixed(2);
         //   },
         //   createdCell: function(td, cellData, rowData, row, col) {
         //     $(td).css('background-color', '#FFE5CC'); // hijau muda
         //     $(td).css('color', '#080');
         //   }
         // },
         // {
         //   data: null,
         //   render: function(data, type, row) {
         //     // Cek lab & radiologi
         //     let totalLab = parseFloat(row.total_lab) || 0;
         //     let totalRad = parseFloat(row.total_radiologi) || 0;

         //     // Jika salah satu bukan 0 → tampilkan "-"
         //     if (totalLab !== 0 || totalRad !== 0) {
         //       return "-";
         //     }
         //     let nonmedis15 = hitungMedis85(row) * 15 / 100;
         //     let hasil = (nonmedis15 / totalJasa(row));;
         //     return hasil.toFixed(2);
         //   },
         //   createdCell: function(td, cellData, rowData, row, col) {
         //     $(td).css('background-color', '#FFE5CC'); // hijau muda
         //     $(td).css('color', '#080');
         //   }
         // },


       ],
       pageLength: 25,
       order: [
         [0, 'desc']
       ],
       language: {
         processing: "Memuat data...",
         lengthMenu: "Tampilkan _MENU_ data per halaman",
         zeroRecords: "Data tidak ditemukan",
         info: "Menampilkan halaman _PAGE_ dari _PAGES_",
         infoEmpty: "Tidak ada data tersedia",
         infoFiltered: "(difilter dari _MAX_ total data)",
         search: "Cari:",
         paginate: {
           first: "Pertama",
           last: "Terakhir",
           next: "Selanjutnya",
           previous: "Sebelumnya"
         }
       },
       footerCallback: function(row, data, start, end, display) {
         const api = this.api();

         // Helper untuk menjumlahkan data
         const sumData = (prop) => {
           return data
             .map(row => parseFloat(row[prop]) || 0)
             .reduce((a, b) => a + b, 0);
         };

         // Helper khusus untuk Total Bayar karena menggunakan fungsi eksternal
         const sumTotalBayar = () => {
           return data
             .map(row => hitungTotalBayar(row) || 0)
             .reduce((a, b) => a + b, 0);
         };

         const formatIDR = (x) => "Rp " + Math.round(x).toLocaleString('id-ID');

         // Mapping Data (Pastikan indeks sesuai dengan kolom di tabel)

         // TINDAKAN
         $(api.column(7).footer()).html(formatIDR(sumData('total_material')));
         $(api.column(8).footer()).html(formatIDR(sumData('total_tindakan_dr')));
         $(api.column(9).footer()).html(formatIDR(sumData('total_tindakan_pr')));
         $(api.column(10).footer()).html(formatIDR(sumData('total_menejemen')));
         $(api.column(11).footer()).html(formatIDR(sumData('total_biaya_rawat')));

         // OPERASI (Hanya nilai uang)
         $(api.column(15).footer()).html(formatIDR(sumData('total_jasa_sarana_rs')));
         $(api.column(16).footer()).html(formatIDR(sumData('total_perina_operasi')));
         $(api.column(17).footer()).html(formatIDR(sumData('total_onloop_operasi')));
         $(api.column(18).footer()).html(formatIDR(sumData('total_bidan_operasi')));
         $(api.column(19).footer()).html(formatIDR(sumData('total_dr_anestesi_operasi')));
         $(api.column(20).footer()).html(formatIDR(sumData('total_asisten_anestesi_operasi')));
         $(api.column(21).footer()).html(formatIDR(sumData('total_asisten_operator_operasi')));
         $(api.column(22).footer()).html(formatIDR(sumData('total_operator_operasi')));
         $(api.column(23).footer()).html(formatIDR(sumData('total_operasi')));

         // OBAT
         $(api.column(24).footer()).html(sumData('jumlah_resep_racikan'));
         $(api.column(25).footer()).html(sumData('jumlah_resep_non_racikan'));
         $(api.column(26).footer()).html(sumData('jumlah_resep_operasi'));
         $(api.column(27).footer()).html(formatIDR(sumData('jasa_farmasi')));
         $(api.column(28).footer()).html(formatIDR(sumData('total_obat')));

         // LAB
         $(api.column(29).footer()).html(formatIDR(sumData('total_material_lab')));
         $(api.column(30).footer()).html(formatIDR(sumData('total_dokter_lab')));
         $(api.column(31).footer()).html(formatIDR(sumData('total_petugas_lab')));
         $(api.column(32).footer()).html(formatIDR(sumData('total_menejemen_lab')));
         $(api.column(33).footer()).html(formatIDR(sumData('total_lab')));

         // RADIOLOGI
         $(api.column(34).footer()).html(formatIDR(sumData('total_material_radiologi')));
         $(api.column(35).footer()).html(formatIDR(sumData('total_dokter_radiologi')));
         $(api.column(36).footer()).html(formatIDR(sumData('total_petugas_radiologi')));
         $(api.column(37).footer()).html(formatIDR(sumData('total_menejemen_radiologi')));
         $(api.column(38).footer()).html(formatIDR(sumData('total_radiologi')));

         // TOTAL AKHIR
         $(api.column(39).footer()).html(formatIDR(sumTotalBayar()));
         $(api.column(40).footer()).html(formatIDR(sumData('total_bpjs')));
       }
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
     const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);

     $('#tgl1').val(formatDateTime(sevenDaysAgo));
     $('#tgl2').val(formatDateTime(now));
     $('#kd_poli').val('');
     $('#kd_pj').val('');
     $('#tcari').val('');
     $('#filter_bulan').val('');
     $('#filter_tahun').val(new Date().getFullYear());

     loadData();
   }
 </script>