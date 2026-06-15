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
// Tambahkan fungsi export
function exportCSV() {
  const params = new URLSearchParams({
    tgl1: $('#tgl1').val(),
    tgl2: $('#tgl2').val(),
    kd_bangsal: $('#kd_bangsal').val(),
    kd_pj: $('#kd_pj').val(),
    filter_sep: $('#filter_sep').val(),
    tcari: $('#tcari').val(),
    gedung: $('#gedung').val(),
    status_pulang: $('#status_pulang').val(),
    filter_bulan: $('#filter_bulan').val() || '',
    filter_tahun: $('#filter_tahun').val() || new Date().getFullYear()
  });
  window.open('../api/export_ranap.php?' + params.toString(), '_blank');
}
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

function loadDetailTindakan(no_rawat) {
  fetch("../api/get_detail_tindakan_ranap.php?no_rawat=" + no_rawat)
    .then(res => res.json())
    .then(json => {

      let tbody = document.querySelector("#tabelDetailTindakan tbody");
      tbody.innerHTML = "";

      if (!json.tindakan || json.tindakan.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="10" class="text-center py-3 text-gray-500">
              Tidak ada data tindakan
            </td>
          </tr>`;
        return;
      }

      json.tindakan.forEach((item, index) => {
        tbody.innerHTML += `
          <tr class="border-b">
            <td class="text-center">${index + 1}</td>
            <td class="px-1">${item.nm_perawatan}</td>
            <td class="px-1">${item.nm_dokter}</td>
            <td class="px-1">${item.nama_petugas ?? '-'}</td>
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
  fetch("../api/get_detail_lab_ranap.php?no_rawat=" + no_rawat)
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

  fetch("../api/get_detail_obat_ranap.php?no_rawat=" + no_rawat)
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

$(document).ready(function() {
  // Set default datetime (last 7 days to now)
  const now = new Date();
  const sevenDaysAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);

  sevenDaysAgo.setHours(1, 0, 0, 0);
  now.setHours(23, 0, 0, 0);
  $('#tgl2').val(formatDateTime(now));
  $('#tgl1').val(formatDateTime(sevenDaysAgo));

  function jasaResepPulang(row) {
    const total = parseFloat(row.total_obat_pulang) || 0;
    return total > 0 ? 15000 : 0;
  }

  function hitungTotalBayar(row) {
    return (
      (parseFloat(row.total_biaya_rawat) || 0) +
      (parseFloat(row.total_obat) || 0) +
      (parseFloat(row.total_obat_pulang) || 0) +
      (parseFloat(row.total_lab) || 0) +
      (parseFloat(row.jasa_farmasi) || 0) +
      (parseFloat(row.total_biaya_kamar) || 0) +
      (parseFloat(row.total_operasi) || 0) +
      (parseFloat(row.total_radiologi) || 0) +
      (parseFloat(row.total_rajal_biaya_rawat) || 0) +
      jasaResepPulang(row)
    );
  }

  function hitungMedis85(row) {
    return totalJasa(row) * 85 / 100;
  }

  function totalJasa(row) {
    return (Math.round(row.total_biaya_rawat) -
      (Math.round(row.total_material) +
        Math.round(row.total_obat)));
  }

  function inapdrpr(row) {
    return (
      Math.round(row.total_tindakan_dr) +
      Math.round(row.total_tindakan_pr));
  }

  function rajaldrpr(row) {
    return (
      Math.round(row.total_rajal_tindakan_dr) +
      Math.round(row.total_rajal_tindakan_pr));
  }

  // Initialize DataTable
  table = $('#tabelTindakan').DataTable({
    processing: true,
    serverSide: true,
    scrollY: "500px",
    scrollX: true,
    scrollCollapse: true,
    dom: '<"flex justify-between items-center mb-4"lB>rtip',
    fixedColumns: {
      left: 3
    },
    columnDefs: [{
      targets: [0, 1, 2]
    }],
    buttons: [{
        extend: 'excel',
        text: 'Export Excel',
      },
      {
        extend: 'pdfHtml5',
        text: 'Export PDF',
        orientation: 'landscape',
        pageSize: 'legal',
        customize: function(doc) {
          doc.defaultStyle.fontSize = 3;

          doc.styles.tableHeader.fontSize = 3;

          if (doc.content[0].text) {
            doc.content[0].fontSize = 3;
          }
        }
      }
    ],
    lengthMenu: [
      [10, 25, 50, 100, 300, 10000],
      [10, 25, 50, 100, 300, 10000]
    ],
    pageLength: 25,
    scrollX: true,
    autoWidth: false,

    ajax: {
      url: '../api/get_data_ranap.php',
      type: 'POST',
      dataSrc: function(json) {
        console.log("RAW JSON:", json);
        return json.data || json;
      },
      data: function(d) {
        d.tgl1 = $('#tgl1').val();
        d.tgl2 = $('#tgl2').val();
        d.kd_bangsal = $('#kd_bangsal').val();
        d.kd_pj = $('#kd_pj').val();
        d.tcari = $('#tcari').val();
        d.gedung = $('#gedung').val();
        d.status_pulang = $('#status_pulang').val();
        d.filter_sep = $('#filter_sep').val();
        d.search_value = d.search.value;
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
        data: null,
        title: 'No SEP / Penanggung',
        render: function(data, type, row) {
          const noSepRaw = (row.no_sep ?? '').trim();
          const penjab = (row.png_jawab ?? '').trim().toUpperCase();

          // Jika ada data SEP
          if (noSepRaw !== '' && noSepRaw !== '-') {
            // Pecah string berdasarkan separator '|'
            const sepList = noSepRaw.split('|');

            // Jika hanya satu, langsung tampilkan. Jika lebih dari satu, buat list.
            if (sepList.length > 1) {
              let listHtml = '<ol style="margin:0;">';
              sepList.forEach(function(item) {
                listHtml += '<li>- ' + item.trim() + '</li>';
              });
              listHtml += '</ol>';
              return listHtml;
            }
            return sepList[0];
          }

          // Logika jika SEP tidak ada
          if (penjab.includes('BPJS')) {
            return '<span class="badge badge-danger">Belum Ada SEP</span>';
          }

          return '<span class="text-muted">Bukan BPJS</span>';
        }
      }, {
        data: 'no_rkm_medis'
      },
      {
        data: 'png_jawab'
      },
      {
        data: 'nm_dokter'
      },
      {
        "data": "daftar_dpjp",
        "name": "daftar_dpjp",
        "orderable": false,
        "searchable": false,
        "render": function(data, type, row) {
          if (type === 'display') {
            return data;
          }
          return data;
        }
      },
      {
        data: 'ruang'
      },
      {
        data: 'col_hanya_kamar',
      },
      {
        data: 'col_tarif_pr_kamar',
      },
      {
        data: 'col_tarif_dr_kamar',
      },
      //  kamar
      {
        data: 'total_lama_inap'
      },
      {
        data: 'total_biaya_kamar'
      },
      {
        data: 'status_pulang'
      },
      //  tindakan
      //  {
      //    data: 'daftar_perawat_tindakan',
      //    render: function(data, type, row) {
      //      if (type !== 'display') {
      //        return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
      //      }
      //      return data ?? '-';
      //    }
      //  },
      //  {
      //    data: 'daftar_dokter_tindakan',
      //    render: function(data, type, row) {
      //      if (type !== 'display') {
      //        return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
      //      }
      //      return data ?? '-';
      //    }
      //  },
      {
        data: 'total_material',
        visible: false
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
        data: 'total_biaya_rawat',
        visible: false
      },

      {
        data: 'total_rajal_biaya_rawat',
        visible: false
      },
      {
        data: null,
        render: function(data, type, row) {
          return Math.round(inapdrpr(row));
        }
      },
      {
        data: null,
        render: function(data, type, row) {
          return Math.round(rajaldrpr(row));
        }
      },
      //  operasi
      {
        data: 'nm_perawatan'
      },
      // {
      //   data: 'daftar_petugas_operasi',
      //   title: 'Petugas Operasi', // Judul kolom di header tabel
      //   render: function(data, type, row) {
      //     // Jika DataTables meminta data untuk sorting atau filtering (type !== 'display')
      //     if (type !== 'display') {
      //       // Kita kembalikan data mentah tanpa tag HTML untuk sorting/search yang benar
      //       return data.replace(/<[^>]*>?/gm, ' ').trim() || '';
      //     }
      //     // Untuk tampilan, kita kembalikan string HTML (yang sudah kita buat di PHP)
      //     return data;
      //   }
      // },
      {
        data: 'anastesi'
      },
      {
        data: 'total_jasa_sarana_rs',
        visible: false
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
      {
        data: 'daftar_resep_racikan',
        title: 'Resep Racikan',
        visible: false,
        render: function(data, type, row) {
          if (type !== 'display') return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
          return data ?? '-';
        }
      },
      {
        data: 'jumlah_resep_non_racikan'
      },
      {
        data: 'daftar_resep_non_racikan',
        title: 'Resep Non Racikan',
        visible: false,
        render: function(data, type, row) {
          if (type !== 'display') return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
          return data ?? '-';
        }
      },
      {
        data: 'jumlah_resep_operasi'
      },
      {
        data: 'daftar_resep_operasi',
        title: 'Resep Operasi',
        visible: false,
        render: function(data, type, row) {
          if (type !== 'display') return (data ?? '').replace(/<[^>]*>?/gm, ' ').trim();
          return data ?? '-';
        }
      },
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

      {
        data: 'total_obat_pulang',
        render: function(data) {
          if (!data) return 0;
          return '15000';
        }
      },
      {
        data: 'total_obat_pulang',
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
        data: 'total_material_lab',
        visible: false
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
      // radiologi
      {
        data: 'nm_dokter_radiologi'
      },
      {
        data: 'tindakan_radiologi'
      },
      {
        data: 'total_material_radiologi',
        visible: false
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
        data: 'total_radiologi',
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
      // // pembagian
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
      //  {
      //    data: null,
      //    render: function(data, type, row) {
      //      // Cek lab & radiologi
      //      let totalLab = parseFloat(row.total_lab) || 0;
      //      let totalRad = parseFloat(row.total_radiologi) || 0;

      //      // Jika salah satu bukan 0 → tampilkan "-"
      //      if (totalLab !== 0 || totalRad !== 0) {
      //        return "-";
      //      }
      //      let dokter60 = hitungMedis85(row) * 60 / 100;
      //      let hasil = (dokter60 / totalJasa(row));

      //      return hasil.toFixed(2);
      //    },
      //    createdCell: function(td, cellData, rowData, row, col) {
      //      $(td).css('background-color', '#FFE5CC'); // hijau muda
      //      $(td).css('color', '#080');
      //    }
      //  },
      //  {
      //    data: null,
      //    render: function(data, type, row) {
      //      // Cek lab & radiologi
      //      let totalLab = parseFloat(row.total_lab) || 0;
      //      let totalRad = parseFloat(row.total_radiologi) || 0;

      //      // Jika salah satu bukan 0 → tampilkan "-"
      //      if (totalLab !== 0 || totalRad !== 0) {
      //        return "-";
      //      }
      //      let medis40 = hitungMedis85(row) * 40 / 100;
      //      let hasil = (medis40 / totalJasa(row));;
      //      return hasil.toFixed(2);
      //    },
      //    createdCell: function(td, cellData, rowData, row, col) {
      //      $(td).css('background-color', '#FFE5CC'); // hijau muda
      //      $(td).css('color', '#080');
      //    }
      //  },
      //  {
      //    data: null,
      //    render: function(data, type, row) {
      //      // Cek lab & radiologi
      //      let totalLab = parseFloat(row.total_lab) || 0;
      //      let totalRad = parseFloat(row.total_radiologi) || 0;

      //      // Jika salah satu bukan 0 → tampilkan "-"
      //      if (totalLab !== 0 || totalRad !== 0) {
      //        return "-";
      //      }
      //      let nonmedis15 = hitungMedis85(row) * 15 / 100;
      //      let hasil = (nonmedis15 / totalJasa(row));;
      //      return hasil.toFixed(2);
      //    },
      //    createdCell: function(td, cellData, rowData, row, col) {
      //      $(td).css('background-color', '#FFE5CC'); // hijau muda
      //      $(td).css('color', '#080');
      //    }
      //  },
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

      // Helper untuk menjumlahkan data dari property object
      const sumData = (propertyName) => {
        return data.reduce((a, b) => {
          let val = b[propertyName];
          // Bersihkan jika string, konversi ke float
          return a + (parseFloat(val) || 0);
        }, 0);
      };

      // Helper untuk format Rupiah
      const formatIDR = (n) => {
        return new Intl.NumberFormat('id-ID', {
          style: 'currency',
          currency: 'IDR',
          maximumFractionDigits: 0
        }).format(n);
      };

      // 1. Kalkulasi Total per Kategori
      const totals = {

        totalInapDrPr: data.reduce((a, b) => a + (inapdrpr(b) || 0), 0),
        totalRajalDrPr: data.reduce((a, b) => a + (rajaldrpr(b) || 0), 0),


        totalBiayaKamar: sumData('total_biaya_kamar'),
        // Tindakan
        saranaTindakan: sumData('total_material'),
        drTindakan: sumData('total_tindakan_dr'),
        prTindakan: sumData('total_tindakan_pr'),
        manajemenTindakan: sumData('total_menejemen'),
        totalTindakan: sumData('total_biaya_rawat'),
        totalRajalTindakan: sumData('total_rajal_biaya_rawat'),

        // Operasi
        saranaOp: sumData('total_jasa_sarana_rs'),
        perinaOp: sumData('total_perina_operasi'),
        onloopOp: sumData('total_onloop_operasi'),
        bidanOp: sumData('total_bidan_operasi'),
        drAnestesi: sumData('total_dr_anestesi_operasi'),
        asistenAnestesi: sumData('total_asisten_anestesi_operasi'),
        asistenOp: sumData('total_asisten_operator_operasi'),
        operatorOp: sumData('total_operator_operasi'),
        totalOp: sumData('total_operasi'),

        // Obat
        jasaFarmasi: sumData('jasa_farmasi'),
        totalObat: sumData('total_obat'),
        jasaFarmasiPlg: data.reduce((a, b) => a + (parseFloat(b.total_obat_pulang) > 0 ? 15000 : 0), 0),
        totalObatPlg: sumData('total_obat_pulang'),

        // Lab
        saranaLab: sumData('total_material_lab'),
        drLab: sumData('total_dokter_lab'),
        petugasLab: sumData('total_petugas_lab'),
        manajemenLab: sumData('total_menejemen_lab'),
        totalLab: sumData('total_lab'),

        // Radiologi
        saranaRad: sumData('total_material_radiologi'),
        drRad: sumData('total_dokter_radiologi'),
        petugasRad: sumData('total_petugas_radiologi'),
        manajemenRad: sumData('total_menejemen_radiologi'),
        totalRad: sumData('total_radiologi'),


        // Grand Totals
        totalBPJS: sumData('total_bpjs')
      };

      // Hitung Grand Total Bayar secara manual (seperti fungsi hitungTotalBayar)
      const grandTotalBayar = totals.totalTindakan + totals.totalOp + totals.totalObat +
        totals.jasaFarmasi + totals.totalLab + totals.totalRad + totals.totalRajalTindakan
      totals.totalObatPlg + totals.jasaFarmasiPlg;


      // 2. Update Elemen Footer berdasarkan Indeks (Urutkan sesuai <thead>)
      // Catatan: Pastikan urutan indeks ini sesuai dengan susunan <th> di <tfoot> HTML Anda

      $(api.column(12).footer()).html(formatIDR(totals.totalBiayaKamar));
      $(api.column(14).footer()).html(formatIDR(totals.saranaTindakan));
      $(api.column(15).footer()).html(formatIDR(totals.drTindakan));
      $(api.column(16).footer()).html(formatIDR(totals.prTindakan));
      $(api.column(17).footer()).html(formatIDR(totals.manajemenTindakan));
      $(api.column(18).footer()).html(formatIDR(totals.totalTindakan));
      $(api.column(19).footer()).html(formatIDR(totals.totalRajalTindakan));
      $(api.column(20).footer()).html(formatIDR(totals.totalInapDrPr));
      $(api.column(21).footer()).html(formatIDR(totals.totalRajalDrPr));

      // Footer Operasi
      $(api.column(24).footer()).html(formatIDR(totals.saranaOp));
      $(api.column(25).footer()).html(formatIDR(totals.perinaOp));
      $(api.column(26).footer()).html(formatIDR(totals.onloopOp));
      $(api.column(27).footer()).html(formatIDR(totals.bidanOp));
      $(api.column(28).footer()).html(formatIDR(totals.drAnestesi));
      $(api.column(29).footer()).html(formatIDR(totals.asistenAnestesi));
      $(api.column(30).footer()).html(formatIDR(totals.asistenOp));
      $(api.column(31).footer()).html(formatIDR(totals.operatorOp));
      $(api.column(32).footer()).html(formatIDR(totals.totalOp));

      // Footer Obat & Farmasi
      $(api.column(39).footer()).html(formatIDR(totals.jasaFarmasi));
      $(api.column(40).footer()).html(formatIDR(totals.totalObat));
      $(api.column(41).footer()).html(formatIDR(totals.jasaFarmasiPlg));
      $(api.column(42).footer()).html(formatIDR(totals.totalObatPlg));

      // Footer Lab
      $(api.column(43).footer()).html(formatIDR(totals.saranaLab));
      $(api.column(44).footer()).html(formatIDR(totals.drLab));
      $(api.column(45).footer()).html(formatIDR(totals.petugasLab));
      $(api.column(46).footer()).html(formatIDR(totals.manajemenLab));
      $(api.column(47).footer()).html(formatIDR(totals.totalLab));

      // Footer Radiologi
      $(api.column(50).footer()).html(formatIDR(totals.saranaRad));
      $(api.column(51).footer()).html(formatIDR(totals.drRad));
      $(api.column(53).footer()).html(formatIDR(totals.petugasRad));
      $(api.column(53).footer()).html(formatIDR(totals.manajemenRad));
      $(api.column(54).footer()).html(formatIDR(totals.totalRad));

      // Final Totals
      $(api.column(55).footer()).html(formatIDR(grandTotalBayar));
      $(api.column(56).footer()).html(formatIDR(totals.totalBPJS));
    }
  });
  table.columns().every(function(index) {
    let column = this;
    let columnName = $(column.header()).text().trim();

    if (columnName === "" || index === 0) return;

    let isVisible = column.visible();
    let checkedAttr = isVisible ? 'checked' : '';

    $('#columnToggles').append(`
      <label class="inline-flex items-center p-2 border rounded-md cursor-pointer hover:bg-gray-50 mr-2 mb-2">
        <input type="checkbox" class="toggle-vis mr-2" data-column="${index}" ${checkedAttr}> 
        <span class="text-sm">${columnName}</span>
      </label>
  `);
  });
  $('input.toggle-vis').on('change', function(e) {
    let columnIdx = $(this).attr('data-column');

    let column = table.column(columnIdx);
    column.visible($(this).is(':checked'));

    table.table().footer().querySelectorAll('tr').forEach(row => {});

    table.columns.adjust();

    if (table.fixedColumns) {
      table.fixedColumns().update();
    }

    table.draw(false);
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
  $('#kd_bangsal').val('');
  $('#kd_pj').val('');
  $('#tcari').val('');
  $('#gedung').val('');
  $('#gedung').val('');
  $('#filter_sep').val('semua');
  $('#filter_bulan').val('');
  $('#filter_tahun').val(new Date().getFullYear());

  loadData();
}
 </script>