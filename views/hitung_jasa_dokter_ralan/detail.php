<?php
set_time_limit(180);

require_once '../../config/conf.php';
$koneksi = bukakoneksi();

$kd_dokter = $_GET['kd_dokter'] ?? '';
$bulan     = $_GET['bulan'] ?? date('m');
$tahun     = $_GET['tahun'] ?? date('Y');

if (empty($kd_dokter)) {
    die('Dokter tidak ditemukan');
}

$bulan_padded = str_pad($bulan, 2, '0', STR_PAD_LEFT);
$tgl_awal  = "$tahun-$bulan_padded-01 00:00:00";
$tgl_akhir = date("Y-m-t 23:59:59", strtotime($tgl_awal));
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Nama dokter
$dr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nm_dokter FROM dokter WHERE kd_dokter='$kd_dokter'"));
$nm_dokter = $dr['nm_dokter'] ?? 'DOKTER';

// ============= DATA PER PASIEN =============
$rows = [];
$base = "
    FROM reg_periksa
    JOIN pasien ON reg_periksa.no_rkm_medis = pasien.no_rkm_medis
    JOIN poliklinik ON reg_periksa.kd_poli = poliklinik.kd_poli
    JOIN penjab ON reg_periksa.kd_pj = penjab.kd_pj
    LEFT JOIN bridging_sep ON bridging_sep.no_rawat = reg_periksa.no_rawat
    LEFT JOIN rawat_jl_drpr ON rawat_jl_drpr.no_rawat = reg_periksa.no_rawat
    LEFT JOIN jns_perawatan ON rawat_jl_drpr.kd_jenis_prw = jns_perawatan.kd_jenis_prw
    LEFT JOIN dokter ON reg_periksa.kd_dokter = dokter.kd_dokter
    WHERE reg_periksa.kd_dokter = '$kd_dokter'
    AND reg_periksa.status_lanjut = 'Ralan'
    AND reg_periksa.stts != 'Batal' AND reg_periksa.stts != 'Belum'
    AND NOT (reg_periksa.kd_poli = 'IGDK' AND EXISTS (SELECT 1 FROM kamar_inap ki WHERE ki.no_rawat = reg_periksa.no_rawat))
    AND (CONCAT(reg_periksa.tgl_registrasi,' ',reg_periksa.jam_reg) BETWEEN '$tgl_awal' AND '$tgl_akhir'
         OR CONCAT(rawat_jl_drpr.tgl_perawatan,' ',rawat_jl_drpr.jam_rawat) BETWEEN '$tgl_awal' AND '$tgl_akhir')
";

$q = mysqli_query($koneksi, "SELECT
    reg_periksa.no_rawat, reg_periksa.no_rkm_medis, reg_periksa.tgl_registrasi,
    pasien.nm_pasien, penjab.png_jawab, poliklinik.nm_poli,
    IFNULL(bridging_sep.no_sep, '-') AS no_sep,
    MIN(dokter.nm_dokter) AS nm_dokter,
    IFNULL(SUM(jns_perawatan.tarif_tindakandr),0) AS total_tindakan_dr,
    IFNULL(SUM(jns_perawatan.tarif_tindakanpr),0) AS total_tindakan_pr,
    IFNULL(SUM(jns_perawatan.menejemen),0) AS total_menejemen_tindakan
$base
GROUP BY reg_periksa.no_rawat, bridging_sep.no_sep, reg_periksa.kd_dokter
ORDER BY reg_periksa.tgl_registrasi, reg_periksa.jam_reg
");
if ($q) while ($r = mysqli_fetch_assoc($q)) $rows[] = $r;

// ============= BPJS LOOKUP =============
$bpjs_lookup = [];
$bpjs_result = mysqli_query($koneksi, "SELECT data FROM bpjs_verifikasi ORDER BY created_at DESC");
while ($brow = mysqli_fetch_assoc($bpjs_result)) {
    $data = json_decode($brow['data'], true);
    if (is_array($data)) {
        foreach ($data as $r) {
            if (!empty($r['no_sep'])) $bpjs_lookup[$r['no_sep']] = floatval($r['disetujui'] ?? 0);
        }
    }
}

// ============= HITUNG SUMMARY =============
$summary = [
    'jumlah_pasien' => 0, 'jumlah_pasien_bpjs' => 0, 'jumlah_pasien_non_bpjs' => 0,
    'jumlah_pasien_klaim_bpjs' => 0, 'jumlah_konsul' => 0, 'jumlah_tindakan_lain' => 0,
    'total_bpjs' => 0, 'kolom_44' => 0, 'total_tindakan_dr' => 0, 'jumlah_dpjp' => 0, 'persen_dokter' => 0
];

$konsul_list = ['RJ00769','RJ00768','RJ00764','RJ00644','RJ00012','RJ00011','RJ00010','RJ00009','RJ000008','RJ000007','RJ000003'];

foreach ($rows as &$row) {
    $nr = $row['no_rawat'];

    // Konsul / Tindakan Lain
    $kd = mysqli_query($koneksi, "SELECT kd_jenis_prw FROM rawat_jl_drpr WHERE no_rawat='$nr'");
    $row['jumlah_konsul'] = 0;
    $row['jumlah_tindakan_lain'] = 0;
    if ($kd) while ($k = mysqli_fetch_assoc($kd)) {
        if (in_array($k['kd_jenis_prw'], $konsul_list)) $row['jumlah_konsul']++;
        else $row['jumlah_tindakan_lain']++;
    }

    // Lab
    $lab = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT
        SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_dokter,0)) AS total_dokter_lab,
        SUM(IFNULL(jns_perawatan_lab.tarif_tindakan_petugas,0)) AS total_petugas_lab,
        SUM(IFNULL(jns_perawatan_lab.menejemen,0)) AS total_menejemen_lab
        FROM periksa_lab JOIN jns_perawatan_lab ON periksa_lab.kd_jenis_prw = jns_perawatan_lab.kd_jenis_prw
        WHERE periksa_lab.no_rawat='$nr'"));
    $row['jasa_lab'] = floatval($lab['total_dokter_lab'] ?? 0) + floatval($lab['total_petugas_lab'] ?? 0) + floatval($lab['total_menejemen_lab'] ?? 0);

    // Radiologi
    $rad = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT
        COALESCE(SUM(t2.tarif_tindakan_dokter),0) AS total_dokter_radiologi,
        COALESCE(SUM(t2.tarif_tindakan_petugas),0) AS total_petugas_radiologi,
        COALESCE(SUM(t2.menejemen),0) AS total_menejemen_radiologi
        FROM permintaan_radiologi t1
        JOIN permintaan_pemeriksaan_radiologi t3 ON t1.noorder = t3.noorder
        JOIN jns_perawatan_radiologi t2 ON t3.kd_jenis_prw = t2.kd_jenis_prw
        WHERE t1.no_rawat='$nr' AND t1.status='ralan'"));
    $row['jasa_radiologi'] = floatval($rad['total_dokter_radiologi'] ?? 0) + floatval($rad['total_petugas_radiologi'] ?? 0) + floatval($rad['total_menejemen_radiologi'] ?? 0);

    // Obat
    $obat_r = mysqli_query($koneksi, "SELECT SUM(IFNULL(total,0)) AS total_obat FROM detail_pemberian_obat WHERE no_rawat='$nr' AND status='Ralan'");
    $row['total_obat'] = 0;
    if ($obat_r && $od = mysqli_fetch_assoc($obat_r)) $row['total_obat'] = floatval($od['total_obat']);

    // Resep / Farmasi
    $resep_result = mysqli_query($koneksi, "SELECT no_resep FROM resep_obat WHERE no_rawat='$nr' AND tgl_perawatan!='0000-00-00' AND status='ralan'");
    $racikan = 0; $non_racikan = 0; $operasi = 0;
    if ($resep_result) while ($rs = mysqli_fetch_assoc($resep_result)) {
        if (substr($rs['no_resep'], 0, 2) === 'OK') { $operasi++; continue; }
        $cr = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS ada FROM resep_dokter_racikan WHERE no_resep='{$rs['no_resep']}' LIMIT 1"));
        $cn = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS ada FROM resep_dokter WHERE no_resep='{$rs['no_resep']}' LIMIT 1"));
        if ($cr && $cr['ada'] > 0) $racikan++;
        elseif ($cn && $cn['ada'] > 0) $non_racikan++;
    }
    $jasa_obat = $racikan > 0 ? 25000 : ($non_racikan > 0 ? 15000 : 0);
    $row['jasa_farmasi'] = $jasa_obat + ($operasi > 0 ? 35000 : 0);

    $row['jasa_tindakan'] = $row['total_tindakan_dr'] + $row['total_tindakan_pr'] + $row['total_menejemen_tindakan'];
    $row['total_non_medis'] = $row['total_menejemen_tindakan'] + floatval($lab['total_menejemen_lab'] ?? 0) + floatval($rad['total_menejemen_radiologi'] ?? 0);
    $row['total_jasa'] = $row['jasa_tindakan'] + $row['jasa_farmasi'] + $row['jasa_lab'] + $row['jasa_radiologi'];

    $tj = $row['total_jasa'];
    $pct_dpjp = $tj > 0 ? round($row['total_tindakan_dr'] / $tj * 100, 2) : 0;
    $total_bpjs = $bpjs_lookup[$row['no_sep']] ?? 0;
    $kolom_44 = $total_bpjs * 0.44;
    $jml_dpjp = $kolom_44 > 0 ? round($pct_dpjp / 100 * $kolom_44) : 0;

    $row['total_bpjs'] = $total_bpjs;
    $row['kolom_44'] = $kolom_44;
    $row['jumlah_dpjp'] = $jml_dpjp;
    $row['nominal_rs'] = $row['jasa_tindakan'] + ($row['total_obat'] ?? 0) + $row['jasa_farmasi'] + $row['jasa_lab'] + $row['jasa_radiologi'];

    // Accumulate summary
    $is_bpjs = strpos($row['png_jawab'], 'BPJS') !== false || strpos($row['png_jawab'], 'BPJ') !== false;
    $summary['jumlah_pasien']++;
    if ($is_bpjs) $summary['jumlah_pasien_bpjs']++;
    else $summary['jumlah_pasien_non_bpjs']++;
    if ($total_bpjs > 0) $summary['jumlah_pasien_klaim_bpjs']++;
    $summary['jumlah_konsul'] += $row['jumlah_konsul'];
    $summary['jumlah_tindakan_lain'] += $row['jumlah_tindakan_lain'];
    $summary['total_bpjs'] += $total_bpjs;
    $summary['kolom_44'] += $kolom_44;
    $summary['total_tindakan_dr'] += $row['total_tindakan_dr'];
    $summary['jumlah_dpjp'] += $jml_dpjp;
}
$summary['persen_dokter'] = $summary['kolom_44'] > 0 ? round(($summary['jumlah_dpjp'] / $summary['kolom_44']) * 100, 2) : 0;

// Hitung total untuk footer
$tNominal = 0; $tBPJS = 0; $t44 = 0; $tJasa = 0; $tDpjp = 0;
foreach ($rows as $row) {
    $tNominal += floatval($row['nominal_rs'] ?? 0);
    $tBPJS += floatval($row['total_bpjs'] ?? 0);
    $t44 += floatval($row['kolom_44'] ?? 0);
    $tJasa += floatval($row['total_tindakan_dr'] ?? 0);
    $tDpjp += floatval($row['jumlah_dpjp'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Detail Jasa Dokter - <?= $nm_dokter ?> - <?= $namaBulan[(int)$bulan] ?> <?= $tahun ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @media print {
    @page { margin: 15mm; size: landscape; }
    .no-print { display: none !important; }
    .page-break { page-break-after: always; }
  }
  body { font-family: 'Courier New', monospace; font-size: 12px; }
  .summary-card { border: 2px solid #166534; border-radius: 8px; }
  table.detail { width: 100%; border-collapse: collapse; }
  table.detail th { background: #166534; color: #fff; padding: 4px 6px; text-align: left; font-size: 11px; }
  table.detail td { padding: 3px 6px; border-bottom: 1px solid #ddd; font-size: 11px; }
  table.detail tr:nth-child(even) { background: #f9fafb; }
  table.detail .num { text-align: right; }
  table.detail tfoot td { background: #064e3b; color: #fff; font-weight: bold; padding: 4px 6px; }
</style>
</head>
<body class="p-6">

<div class="no-print mb-4">
  <button onclick="window.print()" class="bg-green-700 text-white px-4 py-2 rounded">Cetak / Print</button>
  <button onclick="window.close()" class="border border-gray-600 text-gray-600 px-4 py-2 rounded ml-2">Tutup</button>
</div>

<!-- ============= PAGE 1: SUMMARY ============= -->
<div class="summary-card p-6 max-w-xl mx-auto">
  <div class="text-center border-b-2 border-green-700 pb-3 mb-4">
    <h1 class="text-lg font-bold text-green-800">DETAIL JASA DOKTER</h1>
    <p class="text-base font-bold"><?= $nm_dokter ?></p>
    <p class="text-gray-500 text-xs"><?= $namaBulan[(int)$bulan] ?> <?= $tahun ?></p>
  </div>

  <div class="text-sm space-y-2">
    <div class="flex justify-between font-bold"><span>Jumlah Pasien</span><span><?= number_format($summary['jumlah_pasien'], 0, ',', '.') ?> orang</span></div>
    <div class="flex justify-between pl-4"><span>Pasien BPJS</span><span><?= number_format($summary['jumlah_pasien_bpjs'], 0, ',', '.') ?> orang</span></div>
    <div class="flex justify-between pl-4"><span>Pasien Non BPJS</span><span><?= number_format($summary['jumlah_pasien_non_bpjs'], 0, ',', '.') ?> orang</span></div>
    <div class="flex justify-between pl-4"><span>Klaim BPJS</span><span><?= number_format($summary['jumlah_pasien_klaim_bpjs'], 0, ',', '.') ?> orang</span></div>
    <div class="border-t border-gray-300 my-2"></div>
    <div class="flex justify-between"><span>Jumlah Konsul</span><span><?= number_format($summary['jumlah_konsul'], 0, ',', '.') ?></span></div>
    <div class="flex justify-between"><span>Tindakan Lain</span><span><?= number_format($summary['jumlah_tindakan_lain'], 0, ',', '.') ?></span></div>
    <div class="border-t border-gray-300 my-2"></div>
    <div class="flex justify-between"><span>Total BPJS</span><span>Rp <?= number_format(round($summary['total_bpjs']), 0, ',', '.') ?></span></div>
    <div class="flex justify-between"><span>44%</span><span>Rp <?= number_format(round($summary['kolom_44']), 0, ',', '.') ?></span></div>
    <div class="flex justify-between"><span>Jasa Dokter (Tarif)</span><span>Rp <?= number_format(round($summary['total_tindakan_dr']), 0, ',', '.') ?></span></div>
    <div class="flex justify-between"><span>% Dokter</span><span><?= $summary['persen_dokter'] ?>%</span></div>
    <div class="border-t-2 border-green-700 my-2"></div>
    <div class="flex justify-between text-base font-bold text-green-800">
      <span>Nominal Jasa (44% x %Dokter)</span>
      <span>Rp <?= number_format(round($summary['jumlah_dpjp']), 0, ',', '.') ?></span>
    </div>
  </div>
</div>

<div class="page-break"></div>

<!-- ============= PAGE 2+: DETAIL PER PASIEN ============= -->
<?php if (!empty($rows)): ?>
<div>
  <h2 class="text-base font-bold mb-2">RINCIAN PER PASIEN</h2>
  <p class="text-xs text-gray-500 mb-3">Dokter: <?= $nm_dokter ?> | Periode: <?= $namaBulan[(int)$bulan] ?> <?= $tahun ?> | Total Pasien: <?= count($rows) ?></p>

  <table class="detail">
    <thead>
      <tr>
        <th>No</th>
        <th>No.Rawat</th>
        <th>No.SEP</th>
        <th>Nominal RS</th>
        <th>Total BPJS</th>
        <th>44%</th>
        <th class="num">Konsul</th>
        <th class="num">Tindakan Lain</th>
        <th>No.RM</th>
        <th>Pasien</th>
        <th>Poli</th>
        <th>Tgl</th>
        <th class="num">Jasa Dr</th>
        <th class="num">Jml Jasa</th>
      </tr>
    </thead>
    <tbody>
      <?php $no = 0; $tNominal = 0; $tBPJS = 0; $t44 = 0; $tKonsul = 0; $tTindakanLain = 0; $tJasa = 0; $tDpjp = 0; ?>
      <?php foreach ($rows as $row): $no++; ?>
        <?php
        $tNominal += floatval($row['nominal_rs'] ?? 0);
        $tBPJS += floatval($row['total_bpjs'] ?? 0);
        $t44 += floatval($row['kolom_44'] ?? 0);
        $tKonsul += floatval($row['jumlah_konsul'] ?? 0);
        $tTindakanLain += floatval($row['jumlah_tindakan_lain'] ?? 0);
        $tJasa += floatval($row['total_tindakan_dr'] ?? 0);
        $tDpjp += floatval($row['jumlah_dpjp'] ?? 0);
        ?>
      <tr>
        <td><?= $no ?></td>
        <td class="text-blue-600"><?= $row['no_rawat'] ?></td>
        <td><?= $row['no_sep'] ?></td>
        <td class="num"><?= number_format(round($row['nominal_rs'] ?? 0), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($row['total_bpjs'] ?? 0), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($row['kolom_44'] ?? 0), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($row['jumlah_konsul'] ?? 0), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($row['jumlah_tindakan_lain'] ?? 0), 0, ',', '.') ?></td>
        <td><?= $row['no_rkm_medis'] ?></td>
        <td><?= $row['nm_pasien'] ?></td>
        <td><?= $row['nm_poli'] ?></td>
        <td><?= $row['tgl_registrasi'] ?></td>
        <td class="num"><?= number_format(round($row['total_tindakan_dr'] ?? 0), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($row['jumlah_dpjp'] ?? 0), 0, ',', '.') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3">TOTAL</td>
        <td class="num"><?= number_format(round($tNominal), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($tBPJS), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($t44), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($tKonsul), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($tTindakanLain), 0, ',', '.') ?></td>
        <td colspan="4"></td>
        <td class="num"><?= number_format(round($tJasa), 0, ',', '.') ?></td>
        <td class="num"><?= number_format(round($tDpjp), 0, ',', '.') ?></td>
      </tr>
    </tfoot>
  </table>
</div>
<?php endif; ?>

</body>
</html>
<?php mysqli_close($koneksi); ?>
