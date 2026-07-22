<?php
$title = "Perbaikan Operasi - " . $_ENV['APP_NAME'];
ob_start();
?>
<style>
  .nav-tabs {
    display: flex;
    border-bottom: 2px solid #e5e7eb;
    margin-bottom: 20px;
  }

  .nav-tabs .nav-link {
    background: none;
    border: none;
    padding: 10px 20px;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
  }

  .nav-tabs .nav-link.active {
    color: #047857;
    border-color: #10b981;
    background: #f0fdf4;
  }

  .form-range {
    display: flex;
    gap: 12px;
    align-items: flex-end;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }

  .form-range label {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    display: block;
    margin-bottom: 4px;
  }

  .form-range select,
  .form-range input {
    padding: 7px 10px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 13px;
  }

  .btn-cari {
    background: #2563eb;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 18px;
    font-size: 13px;
    cursor: pointer;
    transition: background .2s;
  }

  .btn-cari:hover {
    background: #1d4ed8;
  }

  .btn-cari:disabled {
    background: #9ca3af;
    cursor: not-allowed;
  }
</style>
<?php
$extraHead = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<style>
  /* Override Datatables search input border for better UI */
  .dataTables_filter input { border: 1px solid #d1d5db; border-radius: 6px; padding: 4px 8px; margin-left: 8px; }
  .dataTables_length select { border: 1px solid #d1d5db; border-radius: 6px; padding: 4px; }
</style>';

$rootPath = '../';
require_once '../layouts/header.php';
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

// Ambil tindakan Anestesi (KP049 dan KP050) dari jns_perawatan_inap
$tindakan_kp049 = [];
$tindakan_kp050 = [];
$resTindakan = mysqli_query($koneksi, "SELECT kd_jenis_prw, nm_perawatan, total_byrdrpr, kd_kategori FROM jns_perawatan_inap WHERE kd_kategori IN ('KP049', 'KP050') AND status = '1' ORDER BY nm_perawatan ASC");
if ($resTindakan) {
  while ($row = mysqli_fetch_assoc($resTindakan)) {
    if ($row['kd_kategori'] == 'KP049')
      $tindakan_kp049[] = $row;
    else if ($row['kd_kategori'] == 'KP050')
      $tindakan_kp050[] = $row;
  }
}

// Ambil Petugas
$petugas = [];
$resPetugas = mysqli_query($koneksi, "SELECT nip, nama FROM petugas WHERE status = '1' ORDER BY nama ASC");
while ($row = mysqli_fetch_assoc($resPetugas)) {
  $petugas[] = $row;
}

// Ambil Dokter
$dokter = [];
$resDokter = mysqli_query($koneksi, "SELECT kd_dokter, nm_dokter FROM dokter WHERE status = '1' ORDER BY nm_dokter ASC");
while ($row = mysqli_fetch_assoc($resDokter)) {
  $dokter[] = $row;
}
?>

<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <!-- Tabs Navigation -->
  <div class="nav-tabs">
    <button class="nav-link active" data-target="#tab-operasi">Booking Operasi Selesai</button>
  </div>

  <div class="form-range" style="padding:0 10px;">
    <div>
      <label for="bulan">Bulan Booking</label>
      <select id="bulan">
        <?php for ($i = 1; $i <= 12; $i++): ?>
          <option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= date('m') == $i ? 'selected' : '' ?>>
            <?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>
    <div>
      <label for="tahun">Tahun</label>
      <select id="tahun">
        <?php for ($i = date('Y'); $i >= date('Y') - 3; $i--): ?>
          <option value="<?= $i ?>"><?= $i ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <button class="btn-cari" id="btnFilter">&#128269; Tampilkan Data</button>
  </div>
</div>

<div class="bg-white rounded-2xl border border-green-700 p-3 mb-3">
  <div style="margin-bottom: 15px; display:flex; gap:10px;">
    <button id="btnInputAnestesi" class="btn-cari btn-action" data-type="KP049"
      style="display: none; background: #047857;">+ Input Anestesi </button>
    <button id="btnInputOperasi" class="btn-cari btn-action" data-type="KP050"
      style="display: none; background: #2563eb;">+ Input Operasi </button>
  </div>
  <div class="tab-content">
    <div id="tab-operasi" class="tab-pane">
      <div style="overflow-x:auto;">
        <table id="tableAnestesi" class="display" style="width:100%; font-size:12px;">
          <thead>
            <tr>
              <th style="width:30px; text-align:center;"><input type="checkbox" id="chkAll"></th>
              <th>No Rawat</th>
              <th>No RM</th>
              <th>Nama Pasien</th>
              <th>Tanggal Masuk</th>
              <th>Status Operasi</th>
              <th>Jml tindakan Anestesi </th>
              <th>Jml tindakan Operasi </th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Input Operasi -->
<div class="modal-overlay" id="modalInputAnestesi"
  style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.5); align-items:center; justify-content:center;">
  <div class="modal-box"
    style="background:#fff; border-radius:16px; width:92%; max-width:500px; display:flex; flex-direction:column; box-shadow:0 20px 60px rgba(0,0,0,.3); overflow:hidden;">
    <div class="modal-header"
      style="background:#047857; color:#fff; padding:14px 20px; display:flex; justify-content:space-between; align-items:center;">
      <h3 style="margin:0; font-size:16px;" id="modalTitle">Input Massal</h3>
      <button class="modal-close" id="btnCloseModalAnestesi"
        style="background:none; border:none; color:#fff; font-size:20px; cursor:pointer;">&times;</button>
    </div>
    <div class="modal-body" style="padding:20px;">
      <div id="alertAnestesiSuccess"
        style="background:#d1fae5; border:1px solid #6ee7b7; border-radius:8px; padding:12px 16px; font-size:13px; color:#065f46; margin-bottom:10px; display:none;">
      </div>
      <div id="alertAnestesiError"
        style="background:#fee2e2; border:1px solid #fca5a5; border-radius:8px; padding:12px 16px; font-size:13px; color:#991b1b; margin-bottom:10px; display:none;">
      </div>

      <form id="formInputAnestesi">
        <div style="margin-bottom:15px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px;">Tindakan</label>
          <select name="kd_jenis_prw" id="kd_jenis_prw"
            style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;" required>
            <option value="">-- Pilih Tindakan --</option>
          </select>
        </div>
        <div style="margin-bottom:15px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px;">Dokter Operasi</label>
          <select name="kd_dokter" id="kd_dokter"
            style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;" required>
            <option value="">-- Pilih Dokter --</option>
            <?php foreach ($dokter as $d): ?>
              <option value="<?= $d['kd_dokter'] ?>"><?= $d['nm_dokter'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-bottom:15px;">
          <label style="display:block; font-size:13px; font-weight:600; margin-bottom:5px;">Petugas / Penata
            Operasi</label>
          <select name="nip" id="nip"
            style="width:100%; padding:8px; border:1px solid #d1d5db; border-radius:6px; font-size:13px;" required>
            <option value="">-- Pilih Petugas --</option>
            <?php foreach ($petugas as $p): ?>
              <option value="<?= $p['nip'] ?>"><?= $p['nama'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
    </div>
    <div class="modal-footer"
      style="padding:14px 20px; border-top:1px solid #e5e7eb; display:flex; justify-content:flex-end; gap:10px;">
      <button id="btnBatalAnestesi"
        style="background:#6b7280; color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer;">Batal</button>
      <button id="btnSimpanAnestesi"
        style="background:#047857; color:#fff; border:none; border-radius:8px; padding:8px 18px; font-size:13px; cursor:pointer;">&#128190;
        Simpan</button>
    </div>
  </div>
</div>

<script>
  $(document).ready(function () {
    const tableConfig = {
      processing: true,
      serverSide: true,
      searching: true,
      paging: true,
      ordering: false,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      language: {
        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/id.json',
      },
      dom: '<"top"lf>rt<"bottom"ip><"clear">',
    };

    const tableAnestesi = $('#tableAnestesi').DataTable({
      ...tableConfig,
      ajax: {
        url: window.BASE_URL + '/api/get_data_perbaikan_operasi.php',
        type: 'POST',
        data: function (d) {
          d.bulan = $('#bulan').val();
          d.tahun = $('#tahun').val();
        }
      },
      columns: [
        {
          data: 'no_rawat',
          orderable: false,
          className: 'text-center',
          render: function (data) {
            return '<input type="checkbox" class="row-cb" value="' + data + '">';
          }
        },
        { data: 'no_rawat' },
        { data: 'no_rkm_medis' },
        { data: 'nm_pasien' },
        { data: 'tanggal' },
        {
          data: 'status', render: function (data) {
            return `<span style="background:#dcfce7; color:#166534; padding:2px 8px; border-radius:4px; font-weight:bold;">${data}</span>`;
          }
        },
        {
          data: 'kp049_count', className: 'text-center', render: function (data) {
            const color = data == 0 ? '#991b1b' : '#047857';
            return `<span style="font-weight:bold; color:${color};">${data}</span>`;
          }
        },
        {
          data: 'kp050_count', className: 'text-center', render: function (data) {
            const color = data == 0 ? '#991b1b' : '#047857';
            return `<span style="font-weight:bold; color:${color};">${data}</span>`;
          }
        }
      ]
    });

    $('#btnFilter').click(function () {
      $('#chkAll').prop('checked', false);
      $('#btnInputAnestesi, #btnInputOperasi').hide();
      tableAnestesi.ajax.reload();
    });

    // Checkbox logic
    $('#chkAll').change(function () {
      $('.row-cb').prop('checked', $(this).prop('checked'));
      toggleInputBtn();
    });

    $(document).on('change', '.row-cb', function () {
      toggleInputBtn();
    });

    function toggleInputBtn() {
      if ($('.row-cb:checked').length > 0) {
        $('#btnInputAnestesi, #btnInputOperasi').show();
      } else {
        $('#btnInputAnestesi, #btnInputOperasi').hide();
      }
    }

    const dataKP049 = <?= json_encode($tindakan_kp049) ?>;
    const dataKP050 = <?= json_encode($tindakan_kp050) ?>;

    function formatRupiah(number) {
      return new Intl.NumberFormat('id-ID').format(number);
    }

    // Modal logic
    $('.btn-action').click(function () {
      $('#alertAnestesiSuccess, #alertAnestesiError').hide();

      const type = $(this).data('type');
      const selectBox = $('#kd_jenis_prw');
      selectBox.html('<option value="">-- Pilih Tindakan --</option>');

      let dataSource = type === 'KP049' ? dataKP049 : dataKP050;
      let title = type === 'KP049' ? 'Input Anestesi (KP049)' : 'Input Operasi (KP050)';

      $('#modalTitle').text(title);

      dataSource.forEach(function (item) {
        selectBox.append(`<option value="${item.kd_jenis_prw}">${item.nm_perawatan} - Rp ${formatRupiah(item.total_byrdrpr)}</option>`);
      });

      $('#modalInputAnestesi').css('display', 'flex');
    });

    $('#btnCloseModalAnestesi, #btnBatalAnestesi').click(function () {
      $('#modalInputAnestesi').css('display', 'none');
    });

    $('#btnSimpanAnestesi').click(function () {
      const selectedNos = [];
      $('.row-cb:checked').each(function () {
        selectedNos.push($(this).val());
      });

      const kdJenis = $('#kd_jenis_prw').val();
      const kdDokter = $('#kd_dokter').val();
      const nip = $('#nip').val();

      if (selectedNos.length === 0) {
        alert('Pilih minimal 1 pasien');
        return;
      }
      if (!kdJenis || !kdDokter || !nip) {
        alert('Lengkapi tindakan, dokter, dan petugas');
        return;
      }

      const btn = $(this);
      btn.text('Menyimpan...').prop('disabled', true);
      $('#alertAnestesiError, #alertAnestesiSuccess').hide();

      $.ajax({
        url: window.BASE_URL + '/api/save_input_operasi.php',
        method: 'POST',
        data: {
          no_rawat: selectedNos,
          kd_jenis_prw: kdJenis,
          kd_dokter: kdDokter,
          nip: nip
        },
        success: function (res) {
          if (res.success) {
            $('#alertAnestesiSuccess').text('\u2713 ' + res.message).show();
            setTimeout(function () {
              $('#modalInputAnestesi').css('display', 'none');
              $('#chkAll').prop('checked', false);
              $('#btnInputAnestesi, #btnInputOperasi').hide();
              tableAnestesi.ajax.reload();
            }, 1500);
          } else {
            $('#alertAnestesiError').text('Gagal: ' + res.message).show();
          }
        },
        error: function () {
          $('#alertAnestesiError').text('Terjadi kesalahan jaringan.').show();
        },
        complete: function () {
          btn.html('\uD83D\uDCBE Simpan').prop('disabled', false);
        }
      });
    });

  });
</script>

<?php require_once '../layouts/footer.php'; ?>