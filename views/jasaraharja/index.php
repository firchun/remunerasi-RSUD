<?php
/**
 * Halaman Tagihan Jasa Raharja
 * Filter: tanggal registrasi, jenis bayar, nama pasien / no RM, no SEP / no rawat
 * Tampil: tabel detail per tindakan per ruangan (format PDF)
 * Download: export_excel.php
 */
require_once '../../config/conf.php';
$koneksi = bukakoneksi();

// ── Ambil daftar penjab untuk dropdown ────────────────────────────────────────
$penjab_list = [];
$r = mysqli_query($koneksi, "SELECT kd_pj, png_jawab FROM penjab ORDER BY png_jawab");
while ($p = mysqli_fetch_assoc($r))
    $penjab_list[] = $p;

// ── Parameter filter ──────────────────────────────────────────────────────────
$tgl1 = $_GET['tgl1'] ?? date('Y-m-01');
$tgl2 = $_GET['tgl2'] ?? date('Y-m-d');
$kd_pj = $_GET['kd_pj'] ?? '';
$tcari = $_GET['tcari'] ?? '';
$no_sep = $_GET['no_sep'] ?? '';

// ── WHERE builder ─────────────────────────────────────────────────────────────
$where = "WHERE rp.status_lanjut = 'Ranap'";
if (!empty($tgl1) && !empty($tgl2)) {
    $t1 = mysqli_real_escape_string($koneksi, $tgl1);
    $t2 = mysqli_real_escape_string($koneksi, $tgl2);
    $where .= " AND DATE(rp.tgl_registrasi) BETWEEN '$t1' AND '$t2'";
}
if (!empty($kd_pj)) {
    $kp = mysqli_real_escape_string($koneksi, $kd_pj);
    $where .= " AND rp.kd_pj = '$kp'";
}
if (!empty($tcari)) {
    $tc = mysqli_real_escape_string($koneksi, $tcari);
    $where .= " AND (p.nm_pasien LIKE '%$tc%' OR rp.no_rkm_medis LIKE '%$tc%')";
}
if (!empty($no_sep)) {
    $ns = mysqli_real_escape_string($koneksi, $no_sep);
    $where .= " AND (rp.no_rawat LIKE '%$ns%'
                 OR EXISTS (SELECT 1 FROM bridging_sep bs WHERE bs.no_rawat=rp.no_rawat AND bs.no_sep LIKE '%$ns%'))";
}

// ── Query daftar pasien ───────────────────────────────────────────────────────
$sql_pasien = "
SELECT
    rp.no_rawat, rp.no_rkm_medis, rp.tgl_registrasi,
    p.nm_pasien,
    pj.png_jawab,
    dok.nm_dokter,
    IFNULL((SELECT GROUP_CONCAT(DISTINCT bs2.no_sep SEPARATOR ' | ')
            FROM bridging_sep bs2
            WHERE bs2.no_rawat=rp.no_rawat AND bs2.no_sep NOT IN ('','-')),'-') AS no_sep,
    IFNULL(ka.nm_bangsal,'Belum Masuk Kamar') AS kamar_terakhir,
    IFNULL(ka.stts_pulang,'-') AS status_pulang,
    IFNULL(ka.tgl_keluar,'') AS tgl_keluar
FROM reg_periksa rp
JOIN pasien p ON rp.no_rkm_medis = p.no_rkm_medis
JOIN penjab pj ON rp.kd_pj = pj.kd_pj
LEFT JOIN dokter dok ON rp.kd_dokter = dok.kd_dokter
LEFT JOIN (
    SELECT ki.no_rawat, b.nm_bangsal, ki.stts_pulang, ki.tgl_keluar
    FROM kamar_inap ki
    JOIN kamar k ON ki.kd_kamar = k.kd_kamar
    JOIN bangsal b ON k.kd_bangsal = b.kd_bangsal
    WHERE ki.stts_pulang != 'Pindah Kamar'
    GROUP BY ki.no_rawat
) ka ON ka.no_rawat = rp.no_rawat
$where
ORDER BY rp.tgl_registrasi DESC
LIMIT 200
";
$res_pasien = mysqli_query($koneksi, $sql_pasien);
$pasien_rows = [];
if ($res_pasien) {
    while ($row = mysqli_fetch_assoc($res_pasien))
        $pasien_rows[] = $row;
}

// ── Jika ada pilih pasien (AJAX detail) ──────────────────────────────────────
$detail_no_rawat = $_GET['detail'] ?? '';
$detail_data = [];

if (!empty($detail_no_rawat)) {
    $nr = mysqli_real_escape_string($koneksi, $detail_no_rawat);

    // Info pasien header
    $hdr = mysqli_fetch_assoc(mysqli_query($koneksi, "
        SELECT p.nm_pasien, rp.no_rkm_medis, rp.tgl_registrasi, pj.png_jawab,
               IFNULL((SELECT bs.no_sep FROM bridging_sep bs WHERE bs.no_rawat=rp.no_rawat AND bs.no_sep NOT IN ('','-') LIMIT 1),'-') AS no_sep,
               IFNULL(SUM(ki.lama*ki.trf_kamar),0) AS total_kamar,
               IFNULL(SUM(ki.lama),0) AS total_lama
        FROM reg_periksa rp
        JOIN pasien p ON rp.no_rkm_medis=p.no_rkm_medis
        JOIN penjab pj ON rp.kd_pj=pj.kd_pj
        LEFT JOIN kamar_inap ki ON ki.no_rawat=rp.no_rawat
        WHERE rp.no_rawat='$nr'
        GROUP BY rp.no_rawat
    "));

    // Riwayat kamar
    $kamar_rows = [];
    $q = mysqli_query($koneksi, "
        SELECT b.nm_bangsal, ki.tgl_masuk, ki.jam_masuk, ki.tgl_keluar, ki.jam_keluar,
               ki.lama, ki.trf_kamar, ki.stts_pulang
        FROM kamar_inap ki
        JOIN kamar k ON ki.kd_kamar=k.kd_kamar
        JOIN bangsal b ON k.kd_bangsal=b.kd_bangsal
        WHERE ki.no_rawat='$nr'
        ORDER BY ki.tgl_masuk, ki.jam_masuk
    ");
    while ($rw = mysqli_fetch_assoc($q))
        $kamar_rows[] = $rw;

    // Tindakan rawat inap
    $tind_inap = [];
    $q = mysqli_query($koneksi, "
        SELECT ji.nm_perawatan, COUNT(*) AS qty, ji.tarif_tindakandr, ji.tarif_tindakanpr,
               ji.material, ji.menejemen, ji.total_byrdrpr,
               ri.tgl_perawatan, b.nm_bangsal
        FROM rawat_inap_drpr ri
        JOIN jns_perawatan_inap ji ON ri.kd_jenis_prw=ji.kd_jenis_prw
        LEFT JOIN kamar_inap ki ON ki.no_rawat=ri.no_rawat
            AND ri.tgl_perawatan BETWEEN ki.tgl_masuk AND IF(ki.tgl_keluar='0000-00-00',CURDATE(),ki.tgl_keluar)
            AND ki.stts_pulang != 'Pindah Kamar'
        LEFT JOIN kamar k ON ki.kd_kamar=k.kd_kamar
        LEFT JOIN bangsal b ON k.kd_bangsal=b.kd_bangsal
        WHERE ri.no_rawat='$nr'
        GROUP BY ji.nm_perawatan, b.nm_bangsal
        ORDER BY b.nm_bangsal, ji.nm_perawatan
    ");
    while ($row = mysqli_fetch_assoc($q))
        $tind_inap[] = $row;

    // Tindakan rajal
    $tind_rajal = [];
    $q = mysqli_query($koneksi, "
        SELECT jj.nm_perawatan, COUNT(*) AS qty, jj.tarif_tindakandr, jj.tarif_tindakanpr, jj.total_byrdrpr
        FROM rawat_jl_drpr rj
        JOIN jns_perawatan jj ON rj.kd_jenis_prw=jj.kd_jenis_prw
        WHERE rj.no_rawat='$nr'
        GROUP BY jj.nm_perawatan
        ORDER BY jj.nm_perawatan
    ");
    while ($row = mysqli_fetch_assoc($q))
        $tind_rajal[] = $row;

    // Operasi
    $operasi = [];
    $q = mysqli_query($koneksi, "
        SELECT pk.nm_perawatan, 1 AS qty,
               (IFNULL(o.biayaoperator1,0)+IFNULL(o.biayaoperator2,0)+IFNULL(o.biayaoperator3,0)) AS jasa_operator,
               (IFNULL(o.biayaasisten_operator1,0)+IFNULL(o.biayaasisten_operator2,0)+IFNULL(o.biayaasisten_operator3,0)) AS jasa_asisten,
               IFNULL(o.biayadokter_anestesi,0) AS jasa_anestesi,
               (IFNULL(o.biayaasisten_anestesi,0)+IFNULL(o.biayaasisten_anestesi2,0)) AS jasa_asisten_anestesi,
               IFNULL(o.biayabidan,0)+IFNULL(o.biayabidan2,0)+IFNULL(o.biayabidan3,0) AS jasa_bidan,
               IFNULL(o.biaya_omloop,0)+IFNULL(o.biaya_omloop2,0) AS jasa_onloop,
               IFNULL(o.biayadokter_anak,0) AS jasa_perina,
               (IFNULL(o.akomodasi,0)+IFNULL(o.bagian_rs,0)+IFNULL(o.biayasarpras,0)+IFNULL(o.biayasewaok,0)) AS jasa_sarana,
               o.tgl_operasi
        FROM operasi o
        LEFT JOIN paket_operasi pk ON pk.kode_paket=o.kode_paket
        WHERE o.no_rawat='$nr' AND o.status='Ranap'
    ");
    while ($row = mysqli_fetch_assoc($q))
        $operasi[] = $row;

    // Lab
    $lab = [];
    $q = mysqli_query($koneksi, "
        SELECT jpl.nm_perawatan, COUNT(*) AS qty, jpl.bagian_rs, jpl.tarif_tindakan_dokter,
               jpl.tarif_tindakan_petugas, jpl.total_byr
        FROM periksa_lab pl
        JOIN jns_perawatan_lab jpl ON pl.kd_jenis_prw=jpl.kd_jenis_prw
        WHERE pl.no_rawat='$nr'
        GROUP BY jpl.nm_perawatan
        ORDER BY jpl.nm_perawatan
    ");
    while ($row = mysqli_fetch_assoc($q))
        $lab[] = $row;

    // Radiologi
    $radiologi = [];
    $q = mysqli_query($koneksi, "
        SELECT jr.nm_perawatan, COUNT(*) AS qty, jr.bagian_rs, jr.tarif_tindakan_dokter,
               jr.tarif_tindakan_petugas, jr.total_byr
        FROM permintaan_radiologi pr
        JOIN permintaan_pemeriksaan_radiologi ppr ON pr.noorder=ppr.noorder
        JOIN jns_perawatan_radiologi jr ON ppr.kd_jenis_prw=jr.kd_jenis_prw
        WHERE pr.no_rawat='$nr'
        GROUP BY jr.nm_perawatan
    ");
    while ($row = mysqli_fetch_assoc($q))
        $radiologi[] = $row;

    // Obat
    $obat = [];
    $q = mysqli_query($koneksi, "
        SELECT db.nama_brng AS nama_obat, SUM(dpo.jml) AS qty,
               dpo.biaya_obat AS harga_jual, SUM(dpo.total) AS subtotal
        FROM detail_pemberian_obat dpo
        JOIN databarang db ON db.kode_brng = dpo.kode_brng
        WHERE dpo.no_rawat='$nr'
        GROUP BY dpo.kode_brng, dpo.biaya_obat
        ORDER BY db.nama_brng
    ");
    while ($row = mysqli_fetch_assoc($q))
        $obat[] = $row;

    // Obat pulang
    $obat_pulang = [];
    $q = mysqli_query($koneksi, "
        SELECT db.nama_brng AS nama_obat, SUM(rpo.jml_barang) AS qty,
               rpo.harga AS harga_jual, SUM(rpo.total) AS subtotal
        FROM resep_pulang rpo
        JOIN databarang db ON db.kode_brng = rpo.kode_brng
        WHERE rpo.no_rawat='$nr'
        GROUP BY rpo.kode_brng, rpo.harga
        ORDER BY db.nama_brng
    ");
    while ($row = mysqli_fetch_assoc($q))
        $obat_pulang[] = $row;

    $detail_data = compact('hdr', 'kamar_rows', 'tind_inap', 'tind_rajal', 'operasi', 'lab', 'radiologi', 'obat', 'obat_pulang');
}
$pageTitle = 'Tagihan Jasa Raharja - RSUD MERAUKE';
$extraHead = '<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;600&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root { --bg: #f4f3ef; --surface: #ffffff; --border: #d6d3c8; --border2: #e8e5de; --navy: #1a2744; --navy2: #253560; --accent: #c8391a; --text: #1e1e1e; --muted: #6b6860; --mono: \'IBM Plex Mono\', monospace; --sans: \'IBM Plex Sans\', sans-serif; --radius: 4px; }
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: var(--sans); background: var(--bg); color: var(--text); font-size: 13px; line-height: 1.5; }
/* ── Filter panel ── */
.filter-wrap { background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 28px; }
.filter-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; }
.filter-group { display: flex; flex-direction: column; gap: 4px; }
.filter-group label { font-family: var(--mono); font-size: 9px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
.filter-group input, .filter-group select { font-family: var(--sans); font-size: 12px; padding: 6px 10px; border: 1px solid var(--border); border-radius: var(--radius); background: var(--bg); color: var(--text); outline: none; transition: border-color .15s; min-width: 150px; }
.filter-group input:focus, .filter-group select:focus { border-color: var(--navy2); background: #fff; }
.filter-group.wide input { min-width: 200px; }
.btn { font-family: var(--sans); font-size: 12px; font-weight: 500; padding: 7px 18px; border: none; border-radius: var(--radius); cursor: pointer; transition: opacity .15s, transform .1s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.btn:active { transform: scale(.98); }
.btn-primary { background: var(--navy); color: #fff; }
.btn-primary:hover { opacity: .88; }
.btn-success { background: #1a6e3c; color: #fff; }
.btn-success:hover { opacity: .88; }
.btn-sm { padding: 4px 12px; font-size: 11px; }
/* ── Layout split ── */
.main-wrap { display: flex; height: calc(100vh - 200px); overflow: hidden; }
/* ── Pasien list (kiri) ── */
.pasien-panel { width: 360px; min-width: 280px; flex-shrink: 0; border-right: 1px solid var(--border); background: var(--surface); display: flex; flex-direction: column; overflow: hidden; }
.panel-head { padding: 10px 14px; border-bottom: 1px solid var(--border2); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.panel-head h2 { font-family: var(--mono); font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: var(--muted); font-weight: 600; }
.panel-head .count { background: var(--navy); color: #fff; font-size: 10px; font-family: var(--mono); padding: 2px 8px; border-radius: 20px; }
.pasien-list { overflow-y: auto; flex: 1; }
.pasien-item { padding: 10px 14px; border-bottom: 1px solid var(--border2); cursor: pointer; transition: background .1s; }
.pasien-item:hover { background: #f0ede6; }
.pasien-item.active { background: #e8ecf7; border-left: 3px solid var(--navy2); }
.pasien-item .nm { font-weight: 500; font-size: 13px; color: var(--text); }
.pasien-item .meta { font-family: var(--mono); font-size: 10px; color: var(--muted); margin-top: 2px; display: flex; gap: 8px; flex-wrap: wrap; }
.pasien-item .pj { font-size: 10px; padding: 1px 6px; border-radius: 3px; background: #e8ecf7; color: var(--navy2); font-weight: 500; }
.pasien-item .stts-pulang { color: #1a6e3c; font-size: 10px; }
.pasien-item .stts-rawat { color: var(--accent); font-size: 10px; }
/* ── Detail panel (kanan) ── */
.detail-panel { flex: 1; overflow-y: auto; background: var(--bg); padding: 20px 24px; }
/* ── Invoice detail ── */
.inv-header { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px 20px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
.inv-header .hospital { font-weight: 600; font-size: 14px; color: var(--navy); }
.inv-header .hospital small { display: block; font-size: 11px; color: var(--muted); font-weight: 400; margin-top: 2px; }
.inv-meta { text-align: right; }
.inv-meta .no-rawat { font-family: var(--mono); font-size: 11px; color: var(--muted); }
.inv-meta .nm-pasien { font-size: 16px; font-weight: 600; color: var(--navy); margin-top: 2px; }
.inv-meta .tags { display: flex; gap: 6px; justify-content: flex-end; margin-top: 4px; }
.tag { font-size: 10px; padding: 2px 8px; border-radius: 3px; font-weight: 500; }
.tag-pj { background: #e8ecf7; color: var(--navy2); }
.tag-sep { background: #f5e8e4; color: #7a1f0d; font-family: var(--mono); }
.section { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 12px; overflow: hidden; }
.section-head { background: var(--navy); color: #fff; padding: 7px 14px; font-family: var(--mono); font-size: 10px; letter-spacing: .1em; text-transform: uppercase; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
.section-head.room { background: var(--navy2); }
.section-head.sub { background: #3d5a9e; font-size: 9px; }
table.inv { width: 100%; border-collapse: collapse; font-size: 12px; }
table.inv th { background: #f0ede6; padding: 5px 10px; text-align: left; font-family: var(--mono); font-size: 9px; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); font-weight: 600; border-bottom: 1px solid var(--border); }
table.inv th.right, table.inv td.right { text-align: right; }
table.inv td { padding: 5px 10px; border-bottom: 1px solid var(--border2); color: var(--text); }
table.inv tr:last-child td { border-bottom: none; }
table.inv tr:hover td { background: #f8f6f1; }
table.inv .subtotal-row td { background: #f0ede6; font-weight: 600; font-family: var(--mono); font-size: 11px; color: var(--navy); border-top: 1px solid var(--border); }
table.inv .grand-total td { background: var(--navy); color: #fff; font-weight: 600; font-size: 13px; font-family: var(--mono); border-top: 2px solid var(--accent); }
.num { font-family: var(--mono); font-size: 11px; }
.action-bar { display: flex; gap: 10px; margin-bottom: 16px; align-items: center; }
.empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 60%; color: var(--muted); gap: 8px; }
.empty svg { opacity: .3; }
.empty p { font-family: var(--mono); font-size: 11px; letter-spacing: .08em; }
@media (max-width: 700px) { .main-wrap { flex-direction: column; height: auto; } .pasien-panel { width: 100%; height: 220px; border-right: none; border-bottom: 1px solid var(--border); } .detail-panel { padding: 12px; } }
</style>';
$rootPath = '../';
require_once '../layouts/header.php';
?>


    <!-- ── Filter ── -->
    <div class="filter-wrap">
        <form class="filter-form" method="GET">
            <div class="filter-group">
                <label>Tgl Registrasi</label>
                <input type="date" name="tgl1" value="<?= htmlspecialchars($tgl1) ?>">
            </div>
            <div class="filter-group">
                <label>S/D</label>
                <input type="date" name="tgl2" value="<?= htmlspecialchars($tgl2) ?>">
            </div>
            <div class="filter-group">
                <label>Jenis Bayar</label>
                <select name="kd_pj">
                    <option value="">— Semua —</option>
                    <?php foreach ($penjab_list as $pj): ?>
                        <option value="<?= htmlspecialchars($pj['kd_pj']) ?>" <?= $kd_pj === $pj['kd_pj'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pj['png_jawab']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group wide">
                <label>Nama Pasien / No RM</label>
                <input type="text" name="tcari" placeholder="cari nama atau no RM..."
                    value="<?= htmlspecialchars($tcari) ?>">
            </div>
            <div class="filter-group wide">
                <label>No SEP / No Rawat</label>
                <input type="text" name="no_sep" placeholder="no SEP atau no rawat..."
                    value="<?= htmlspecialchars($no_sep) ?>">
            </div>
            <?php if (!empty($detail_no_rawat)): ?>
                <input type="hidden" name="detail" value="<?= htmlspecialchars($detail_no_rawat) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                    <circle cx="5.5" cy="5.5" r="4" stroke="#fff" stroke-width="1.4" />
                    <path d="M9 9l2.5 2.5" stroke="#fff" stroke-width="1.4" stroke-linecap="round" />
                </svg>
                Cari
            </button>
        </form>
    </div>

    <!-- ── Main split layout ── -->
    <div class="main-wrap">

        <!-- Daftar pasien -->
        <div class="pasien-panel">
            <div class="panel-head">
                <h2>Daftar Pasien</h2>
                <span class="count">
                    <?= count($pasien_rows) ?>
                </span>
            </div>
            <div class="pasien-list">
                <?php if (empty($pasien_rows)): ?>
                    <div style="padding:20px;color:var(--muted);font-size:12px;text-align:center">Tidak ada data</div>
                <?php else: ?>
                    <?php foreach ($pasien_rows as $pr): ?>
                        <div class="pasien-item <?= $detail_no_rawat === $pr['no_rawat'] ? 'active' : '' ?>"
                            onclick="loadDetail('<?= htmlspecialchars($pr['no_rawat']) ?>')">
                            <div class="nm">
                                <?= htmlspecialchars($pr['nm_pasien']) ?>
                            </div>
                            <div class="meta">
                                <span>
                                    <?= htmlspecialchars($pr['no_rkm_medis']) ?>
                                </span>
                                <span>
                                    <?= $pr['tgl_registrasi'] ?>
                                </span>
                            </div>
                            <div class="meta" style="margin-top:3px">
                                <span class="pj">
                                    <?= htmlspecialchars($pr['png_jawab']) ?>
                                </span>
                                <?php if ($pr['status_pulang'] && $pr['status_pulang'] !== '-'): ?>
                                    <span class="stts-pulang">✓
                                        <?= htmlspecialchars($pr['status_pulang']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="stts-rawat">⬤ Masih Dirawat</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Detail panel -->
        <div class="detail-panel" id="detailPanel">

            <?php if (!empty($detail_data)): ?>
                <?php
                $hdr = $detail_data['hdr'];
                $kamar_r = $detail_data['kamar_rows'];
                $t_inap = $detail_data['tind_inap'];
                $t_rajal = $detail_data['tind_rajal'];
                $op = $detail_data['operasi'];
                $lab = $detail_data['lab'];
                $rad = $detail_data['radiologi'];
                $obat = $detail_data['obat'];
                $obat_plg = $detail_data['obat_pulang'];

                $grand = 0;

                function rupiah($n)
                {
                    return 'Rp ' . number_format((float) $n, 2, ',', '.');
                }
                function safeFloat($v)
                {
                    return (float) ($v ?? 0);
                }
                ?>

                <!-- Action bar -->
                <div class="action-bar">
                    <a href="<?= $baseUrl ?>/api/export_jasaraharja.php?no_rawat=<?= urlencode($detail_no_rawat) ?>"
                        class="btn btn-success">
                        <svg width="13" height="13" viewBox="0 0 13 13" fill="none">
                            <path d="M6.5 1v8M3.5 6l3 3 3-3M1 11h11" stroke="#fff" stroke-width="1.4" stroke-linecap="round"
                                stroke-linejoin="round" />
                        </svg>
                        Download Excel
                    </a>
                    <span style="font-family:var(--mono);font-size:10px;color:var(--muted)">
                        No Rawat:
                        <?= htmlspecialchars($detail_no_rawat) ?>
                    </span>
                </div>

                <!-- Header invoice -->
                <div class="inv-header">
                    <div>
                        <div class="hospital">RSUD MERAUKE
                            <small>Jl. Sukarjo Wiryopranoto No. 1 — Telp. 0971 321124</small>
                        </div>
                        <div style="margin-top:8px;font-size:11px;color:var(--muted)">
                            Tgl Registrasi: <b>
                                <?= $hdr['tgl_registrasi'] ?>
                            </b>
                            &nbsp;|&nbsp; Lama: <b>
                                <?= $hdr['total_lama'] ?> hari
                            </b>
                        </div>
                    </div>
                    <div class="inv-meta">
                        <div class="no-rawat">
                            <?= htmlspecialchars($detail_no_rawat) ?>
                        </div>
                        <div class="nm-pasien">
                            <?= htmlspecialchars($hdr['nm_pasien']) ?>
                        </div>
                        <div class="tags">
                            <span class="tag tag-pj">
                                <?= htmlspecialchars($hdr['png_jawab']) ?>
                            </span>
                            <?php if ($hdr['no_sep'] !== '-'): ?>
                                <span class="tag tag-sep">SEP:
                                    <?= htmlspecialchars($hdr['no_sep']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php
                // ── Per ruangan (kamar) ──────────────────────────────────────────────────
                foreach ($kamar_r as $kr):
                    $biaya_kamar = safeFloat($kr['lama']) * safeFloat($kr['trf_kamar']);
                    $grand += $biaya_kamar;

                    // Filter tindakan inap di ruangan ini
                    $ti_ruang = array_filter($t_inap, fn($t) => ($t['nm_bangsal'] ?? '') === $kr['nm_bangsal']);
                    $total_ti = array_sum(array_column($ti_ruang, 'total_byrdrpr'));
                    $grand += $total_ti;
                    ?>
                    <div class="section">
                        <div class="section-head room">
                            <?= htmlspecialchars(strtoupper($kr['nm_bangsal'])) ?>
                            &nbsp;
                            <small style="font-weight:400;font-size:9px">
                                <?= $kr['tgl_masuk'] ?> →
                                <?= ($kr['tgl_keluar'] === '0000-00-00' ? '(masih dirawat)' : $kr['tgl_keluar']) ?>
                            </small>
                            <span>
                                <?= rupiah(($biaya_kamar + $total_ti)) ?>
                            </span>
                        </div>
                        <table class="inv">
                            <tr>
                                <th style="width:42%">Nama Tindakan / Layanan</th>
                                <th class="right" style="width:8%">Qty</th>
                                <th class="right" style="width:18%">Satuan (Rp)</th>
                                <th class="right" style="width:18%">Subtotal (Rp)</th>
                            </tr>
                            <!-- Biaya kamar -->
                            <tr>
                                <td>Rawat Inap —
                                    <?= htmlspecialchars($kr['nm_bangsal']) ?>
                                </td>
                                <td class="right num">
                                    <?= $kr['lama'] ?>
                                </td>
                                <td class="right num">
                                    <?= rupiah($kr['trf_kamar']) ?>
                                </td>
                                <td class="right num">
                                    <?= rupiah($biaya_kamar) ?>
                                </td>
                            </tr>
                            <!-- Tindakan di ruangan ini -->
                            <?php foreach ($ti_ruang as $ti): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($ti['nm_perawatan']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= $ti['qty'] ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($ti['total_byrdrpr']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah(safeFloat($ti['total_byrdrpr']) * safeFloat($ti['qty'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!empty($ti_ruang)): ?>
                                <tr class="subtotal-row">
                                    <td colspan="3">Subtotal
                                        <?= htmlspecialchars($kr['nm_bangsal']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($biaya_kamar + $total_ti) ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                <?php endforeach; ?>

                <?php
                // ── Tindakan Rajal ──────────────────────────────────────────────────────
                if (!empty($t_rajal)):
                    $total_rajal = array_sum(array_column($t_rajal, 'total_byrdrpr'));
                    $grand += $total_rajal;
                    ?>
                    <div class="section">
                        <div class="section-head">TINDAKAN RAWAT JALAN <span>
                                <?= rupiah($total_rajal) ?>
                            </span></div>
                        <table class="inv">
                            <tr>
                                <th style="width:42%">Nama Tindakan</th>
                                <th class="right" style="width:8%">Qty</th>
                                <th class="right">Satuan (Rp)</th>
                                <th class="right">Subtotal (Rp)</th>
                            </tr>
                            <?php foreach ($t_rajal as $tr):
                                $sub = safeFloat($tr['total_byrdrpr']) * safeFloat($tr['qty']); ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($tr['nm_perawatan']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= $tr['qty'] ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($tr['total_byrdrpr']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($sub) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="subtotal-row">
                                <td colspan="3">Subtotal Tindakan Rajal</td>
                                <td class="right num">
                                    <?= rupiah($total_rajal) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>

                <?php
                // ── Operasi ─────────────────────────────────────────────────────────────
                if (!empty($op)):
                    $total_op = 0;
                    foreach ($op as $o) {
                        $total_op += safeFloat($o['jasa_operator']) + safeFloat($o['jasa_asisten']) + safeFloat($o['jasa_anestesi'])
                            + safeFloat($o['jasa_asisten_anestesi']) + safeFloat($o['jasa_bidan']) + safeFloat($o['jasa_onloop'])
                            + safeFloat($o['jasa_perina']) + safeFloat($o['jasa_sarana']);
                    }
                    $grand += $total_op;
                    ?>
                    <div class="section">
                        <div class="section-head">OPERASI <span>
                                <?= rupiah($total_op) ?>
                            </span></div>
                        <table class="inv">
                            <tr>
                                <th style="width:42%">Komponen</th>
                                <th class="right">Subtotal (Rp)</th>
                            </tr>
                            <?php foreach ($op as $o):
                                $items = [
                                    'Sarana / Sewa OK' => $o['jasa_sarana'],
                                    'Operator' => $o['jasa_operator'],
                                    'Asisten Operator' => $o['jasa_asisten'],
                                    'Dr. Anestesi' => $o['jasa_anestesi'],
                                    'Asisten Anestesi' => $o['jasa_asisten_anestesi'],
                                    'Bidan' => $o['jasa_bidan'],
                                    'Onloop' => $o['jasa_onloop'],
                                    'Perina (Dr. Anak)' => $o['jasa_perina'],
                                ];
                                foreach ($items as $lbl => $val):
                                    if (safeFloat($val) <= 0)
                                        continue; ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($lbl) ?>
                                            <?= $o['nm_perawatan'] ? '— ' . htmlspecialchars($o['nm_perawatan']) : '' ?>
                                        </td>
                                        <td class="right num">
                                            <?= rupiah($val) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endforeach; ?>
                            <tr class="subtotal-row">
                                <td>Subtotal Operasi</td>
                                <td class="right num">
                                    <?= rupiah($total_op) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>

                <?php
                // ── Lab ─────────────────────────────────────────────────────────────────
                if (!empty($lab)):
                    $total_lab = array_sum(array_column($lab, 'total_byr'));
                    $grand += $total_lab;
                    ?>
                    <div class="section">
                        <div class="section-head">LABORATORIUM <span>
                                <?= rupiah($total_lab) ?>
                            </span></div>
                        <table class="inv">
                            <tr>
                                <th style="width:42%">Pemeriksaan</th>
                                <th class="right" style="width:8%">Qty</th>
                                <th class="right">Satuan (Rp)</th>
                                <th class="right">Subtotal (Rp)</th>
                            </tr>
                            <?php foreach ($lab as $lb):
                                $sub = safeFloat($lb['total_byr']) * safeFloat($lb['qty']); ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($lb['nm_perawatan']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= $lb['qty'] ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($lb['total_byr']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($sub) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="subtotal-row">
                                <td colspan="3">Subtotal Lab</td>
                                <td class="right num">
                                    <?= rupiah($total_lab) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>

                <?php
                // ── Radiologi ────────────────────────────────────────────────────────────
                if (!empty($rad)):
                    $total_rad = array_sum(array_column($rad, 'total_byr'));
                    $grand += $total_rad;
                    ?>
                    <div class="section">
                        <div class="section-head">RADIOLOGI <span>
                                <?= rupiah($total_rad) ?>
                            </span></div>
                        <table class="inv">
                            <tr>
                                <th style="width:42%">Pemeriksaan</th>
                                <th class="right" style="width:8%">Qty</th>
                                <th class="right">Satuan (Rp)</th>
                                <th class="right">Subtotal (Rp)</th>
                            </tr>
                            <?php foreach ($rad as $rd):
                                $sub = safeFloat($rd['total_byr']) * safeFloat($rd['qty']); ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($rd['nm_perawatan']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= $rd['qty'] ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($rd['total_byr']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($sub) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="subtotal-row">
                                <td colspan="3">Subtotal Radiologi</td>
                                <td class="right num">
                                    <?= rupiah($total_rad) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>

                <?php
                // ── Farmasi / Obat ───────────────────────────────────────────────────────
                if (!empty($obat)):
                    $total_obat = array_sum(array_column($obat, 'subtotal'));
                    $grand += $total_obat;
                    ?>
                    <div class="section">
                        <div class="section-head">FARMASI (RAWAT INAP) <span>
                                <?= rupiah($total_obat) ?>
                            </span></div>
                        <table class="inv">
                            <tr>
                                <th style="width:42%">Nama Obat / Alkes</th>
                                <th class="right" style="width:8%">Qty</th>
                                <th class="right">Satuan (Rp)</th>
                                <th class="right">Subtotal (Rp)</th>
                            </tr>
                            <?php foreach ($obat as $ob): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($ob['nama_obat']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= $ob['qty'] ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($ob['harga_jual']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($ob['subtotal']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="subtotal-row">
                                <td colspan="3">Subtotal Farmasi Ranap</td>
                                <td class="right num">
                                    <?= rupiah($total_obat) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>

                <?php
                if (!empty($obat_plg)):
                    $total_obat_plg = array_sum(array_column($obat_plg, 'subtotal'));
                    $grand += $total_obat_plg;
                    ?>
                    <div class="section">
                        <div class="section-head">FARMASI (OBAT PULANG) <span>
                                <?= rupiah($total_obat_plg) ?>
                            </span></div>
                        <table class="inv">
                            <tr>
                                <th style="width:42%">Nama Obat</th>
                                <th class="right" style="width:8%">Qty</th>
                                <th class="right">Satuan (Rp)</th>
                                <th class="right">Subtotal (Rp)</th>
                            </tr>
                            <?php foreach ($obat_plg as $ob): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($ob['nama_obat']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= $ob['qty'] ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($ob['harga_jual']) ?>
                                    </td>
                                    <td class="right num">
                                        <?= rupiah($ob['subtotal']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="subtotal-row">
                                <td colspan="3">Subtotal Obat Pulang</td>
                                <td class="right num">
                                    <?= rupiah($total_obat_plg) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Grand Total -->
                <div class="section">
                    <table class="inv">
                        <tr class="grand-total">
                            <td colspan="3" style="font-size:14px">TOTAL TAGIHAN</td>
                            <td class="right num" style="font-size:15px">
                                <?= rupiah($grand) ?>
                            </td>
                        </tr>
                    </table>
                </div>

            <?php else: ?>
                <div class="empty">
                    <svg width="48" height="48" viewBox="0 0 48 48" fill="none">
                        <rect x="8" y="4" width="32" height="40" rx="3" stroke="#6b6860" stroke-width="1.5" />
                        <path d="M16 16h16M16 22h16M16 28h10" stroke="#6b6860" stroke-width="1.5" stroke-linecap="round" />
                    </svg>
                    <p>Pilih pasien untuk melihat detail tagihan</p>
                </div>
            <?php endif; ?>

        </div><!-- end detail-panel -->
    </div><!-- end main-wrap -->

    <script>
        function loadDetail(noRawat) {
            const url = new URL(window.location.href);
            url.searchParams.set('detail', noRawat);
            window.location.href = url.toString();
        }
    </script>
<?php mysqli_close($koneksi); ?>
<?php require_once '../layouts/footer.php'; ?>