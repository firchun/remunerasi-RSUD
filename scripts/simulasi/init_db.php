<?php
require_once __DIR__ . '/../../config/simulasi.php';
$db = getSimulasiDB();

$db->exec("
CREATE TABLE IF NOT EXISTS pendidikan (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS set_index (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    rating REAL NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS tempat_tugas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    index_resiko TEXT NOT NULL,
    index_emergensi TEXT NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS resiko (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS emergensi (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS beban_kerja (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS sertifikasi (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS pajak (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai TEXT NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS jabatan_ruangan (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS kepangkatan (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS status_kepegawaian (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS ruangan_manajemen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai TEXT NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS jabatan_manajemen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai REAL NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS pengurangan_manajemen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS penilaian_atasan (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kategori TEXT NOT NULL,
    nama TEXT NOT NULL,
    nilai INTEGER NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS rumus (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL UNIQUE,
    ekspresi TEXT NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS pegawai_count (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL
)");

$db->exec("
CREATE TABLE IF NOT EXISTS bekai_non_medis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    total_manajemen REAL NOT NULL DEFAULT 222884512,
    direktur REAL NOT NULL DEFAULT 20,
    pelayanan REAL NOT NULL DEFAULT 18.82,
    program_keuangan REAL NOT NULL DEFAULT 18.82,
    penunjang_sarpras REAL NOT NULL DEFAULT 18.82,
    tata_usaha REAL NOT NULL DEFAULT 23.54
)");

$db->exec("
CREATE TABLE IF NOT EXISTS pegawai (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nama TEXT NOT NULL,
    ruang_kerja_id INTEGER,
    posisi_ruangan_id INTEGER,
    index_koreksi REAL DEFAULT 1,
    pangkat_id INTEGER,
    status_kepegawaian_id INTEGER,
    pendidikan_id INTEGER,
    sertifikat_id INTEGER,
    beban_kerja_id INTEGER,
    iki_id INTEGER,
    absensi_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ruang_kerja_id) REFERENCES tempat_tugas(id),
    FOREIGN KEY (posisi_ruangan_id) REFERENCES jabatan_ruangan(id),
    FOREIGN KEY (pangkat_id) REFERENCES kepangkatan(id),
    FOREIGN KEY (status_kepegawaian_id) REFERENCES status_kepegawaian(id),
    FOREIGN KEY (pendidikan_id) REFERENCES pendidikan(id),
    FOREIGN KEY (sertifikat_id) REFERENCES sertifikasi(id),
    FOREIGN KEY (beban_kerja_id) REFERENCES beban_kerja(id),
    FOREIGN KEY (iki_id) REFERENCES penilaian_atasan(id),
    FOREIGN KEY (absensi_id) REFERENCES penilaian_atasan(id)
)");

$count = $db->query("SELECT COUNT(*) as c FROM pendidikan")->fetch()['c'];

// Migration for existing databases — seed new tables if empty
if ($count > 0) {
    $emCount = $db->query("SELECT COUNT(*) as c FROM emergensi")->fetch()['c'];
    if ($emCount == 0) {
        $s2 = $db->prepare("INSERT OR IGNORE INTO emergensi (nama, nilai) VALUES (?, ?)");
        foreach ([['I', 1], ['II', 2], ['III', 4], ['IV', 6]] as $p) $s2->execute($p);
    }
    $rumusCount = $db->query("SELECT COUNT(*) as c FROM rumus")->fetch()['c'];
    if ($rumusCount == 0) {
        $db->prepare("INSERT OR IGNORE INTO rumus (nama, ekspresi) VALUES (?, ?)")->execute(['JUMLAH INDEX', '(pend+resiko+emergensi+kepeg+jabatan+sertifikat+bkerja)*(iki+penilaian)']);
    }
    $bekaiCount = $db->query("SELECT COUNT(*) as c FROM bekai_non_medis")->fetch()['c'];
    if ($bekaiCount == 0) {
        $db->prepare("INSERT INTO bekai_non_medis (total_manajemen,direktur,pelayanan,program_keuangan,penunjang_sarpras,tata_usaha) VALUES (222884512,20,18.82,18.82,18.82,23.54)")->execute();
    }
    echo "Database sudah terinisialisasi. <a href='?'>Kembali</a>";
    exit;
}

$db->beginTransaction();

$pendidikan = [
    ['SD sederajat', 10], ['SMP sederajat', 20], ['SMA sederajat', 30],
    ['D1 sederajat', 40], ['D3 NonKes', 50], ['D3 Prwt', 60],
    ['D3 Bidan', 60], ['D3 Kesh lain', 60], ['S1/D4 Nonkes', 70],
    ['S1/D4 Prwt', 80], ['S1/D4 Bidan', 80], ['S1/D4 Kesh lain', 80],
    ['Ners', 90], ['Apt', 90], ['Dietician', 90], ['S2 NonKes', 100],
    ['Dr Umum', 100], ['Dr Gigi', 100], ['S2 Kesh', 110],
    ['Spesialis', 120], ['S3 sederajat', 130], ['Subspesialis sederajat', 140],
    ['Prof Medis', 150]
];
$s = $db->prepare("INSERT INTO pendidikan (nama, nilai) VALUES (?, ?)");
foreach ($pendidikan as $p) $s->execute($p);

$set_index = [
    ['pendidikan', 3], ['beban kerja', 2], ['resiko kerja', 5],
    ['emergensi', 5], ['Kepegawaian', 2], ['Jabatan Ruangan', 2],
    ['Keahlian/Sertifikat', 1], ['Indeks Kinerja Individu', 0]
];
$s = $db->prepare("INSERT INTO set_index (nama, rating) VALUES (?, ?)");
foreach ($set_index as $p) $s->execute($p);

$tempat_tugas_list = [
    ['BENDAHARA PENERIMA','I','I'], ['DIKLAT','I','I'], ['DIREKTUR','I','I'],
    ['FASTRACK','I','I'], ['HUMAS DAN HUKUM','I','I'], ['KEPEGAWAIAN','I','I'],
    ['KOMITE ETIK','I','I'], ['KOMITE FARMASI','I','I'], ['KOMITE KEPERAWATAN','I','I'],
    ['KOMITE MEDIK','I','I'], ['KOMITE MUTU','I','I'], ['KOMITE PPI','I','I'],
    ['LOGISTIK','I','I'], ['LOKET PEMBAYARAN','I','I'], ['PBJ','I','I'],
    ['PELAYANAN','I','I'], ['PENUNJANG DAN SARPRAS','I','I'], ['POLI GIZI','I','I'],
    ['REKAM MEDIK','I','I'], ['SPI','I','I'], ['SURVEILANCE','I','I'],
    ['TATA USAHA','I','I'], ['TIM AKREDITASI','I','I'], ['TIM JKN','I','I'],
    ['KEUANGAN','II','I'], ['PROGRAM & KEUANGAN','II','I'], ['SANITASI','II','I'],
    ['CLEANING SERVICE','III','I'], ['KAMAR JENAZAH','III','I'], ['POLI BEDAH','III','I'],
    ['INST BDRS','I','II'], ['ADMISI','II','II'], ['CSSD','II','II'],
    ['INST FARMASI','II','II'], ['INST GIZI','II','II'], ['INST REHAB MEDIK','II','II'],
    ['PIT','II','II'], ['POLI ANAK','II','II'], ['POLI JANTUNG','II','II'],
    ['POLI JIWA','II','II'], ['POLI MATA','II','II'], ['POLI MCU (DOKTER)','II','II'],
    ['POLI MCU (PERAWAT)','II','II'], ['POLI PENYAKIT DALAM','II','II'],
    ['POLI REHAB MEDIK','II','II'], ['POLI SARAF','II','II'], ['POLI THT','II','II'],
    ['INST LABORATORIUM','III','II'], ['INST PA','III','II'], ['LAUNDRY','III','II'],
    ['POLI GIGI DAN MULUT','III','II'], ['POLI HEMODIALISA','III','II'],
    ['POLI KANDUNGAN','III','II'], ['POLI KULIT DAN KELAMIN','III','II'],
    ['POLI ORTHOPEDI','III','II'], ['SECURITY','III','II'], ['POLI ANIMHA','IV','II'],
    ['POLI PARU','IV','II'], ['DRIVER','II','III'], ['IPSRS','II','III'],
    ['HEMODIALISA','III','III'], ['INST RADIOLOGI','III','III'],
    ['RUANGAN BOHA','III','III'], ['RUANGAN CENDRAWASIH','III','III'],
    ['RUANGAN MALEO','III','III'], ['RUANGAN MAMBRUK','III','III'],
    ['RUANGAN RUSA 1','III','III'], ['RUANGAN RUSA 2','III','III'],
    ['RUANGAN URIP','III','III'], ['RUANGAN ICU','IV','III'],
    ['RUANGAN KUSKUS','IV','III'], ['RUANGAN PICU','IV','III'],
    ['RUANGAN KANGURU','III','IV'], ['RUANGAN KASUARI','III','IV'],
    ['RUANGAN PKK','III','IV'], ['RUANGAN PONEK','III','IV'],
    ['IBS','IV','IV'], ['IBS (ANESTESI)','IV','IV'], ['IGD (DOKTER)','IV','IV'],
    ['IGD (PERAWAT)','IV','IV'], ['PENATA ANESTESI','IV','IV']
];
$s = $db->prepare("INSERT INTO tempat_tugas (nama, index_resiko, index_emergensi) VALUES (?, ?, ?)");
foreach ($tempat_tugas_list as $p) $s->execute($p);

$resiko_list = [['I', 1], ['II', 2], ['III', 4], ['IV', 6]];
$s = $db->prepare("INSERT INTO resiko (nama, nilai) VALUES (?, ?)");
foreach ($resiko_list as $p) $s->execute($p);

$emergensi_list = [['I', 10], ['II', 20], ['III', 40], ['IV', 60]];
$s = $db->prepare("INSERT INTO emergensi (nama, nilai) VALUES (?, ?)");
foreach ($emergensi_list as $p) $s->execute($p);

$beban_kerja_list = [
    ['SIMRS', 100], ['TIM JKN', 500], ['KEUANGAN', 50], ['TIM REMUN', 400],
    ['TIM AKREDITASI', 30], ['KOMITE ETIK', 10], ['KOMITE FARMASI', 10],
    ['KOMITE KEPERAWATAN', 10], ['KOMITE MEDIK', 10], ['KOMITE MUTU', 10], ['KOMITE PPI', 10]
];
$s = $db->prepare("INSERT INTO beban_kerja (nama, nilai) VALUES (?, ?)");
foreach ($beban_kerja_list as $p) $s->execute($p);

$sertifikasi_list = [['ADA', 1], ['TIDAK ADA', 0]];
$s = $db->prepare("INSERT INTO sertifikasi (nama, nilai) VALUES (?, ?)");
foreach ($sertifikasi_list as $p) $s->execute($p);

$pajak_list = [
    ['IV/a, IV/b, IV/c, IV/d', '15%'], ['III/a, III/b, III/c, III/d', '5%'],
    ['pppk', '5%'], ['non PNS', '0%']
];
$s = $db->prepare("INSERT INTO pajak (nama, nilai) VALUES (?, ?)");
foreach ($pajak_list as $p) $s->execute($p);

$jabatan_ruangan_list = [
    ['KOORDINATOR', 70], ['KEPALA RUANGA/KETUA', 100],
    ['STAF ADMINISTRASI', 20], ['STAF FUNGSIONAL NON KESEHATAN', 10],
    ['STAF FUNGSIONAL KESEHATAN', 30]
];
$s = $db->prepare("INSERT INTO jabatan_ruangan (nama, nilai) VALUES (?, ?)");
foreach ($jabatan_ruangan_list as $p) $s->execute($p);

$kepangkatan_list = [
    ['II.A', 1], ['II.B', 2], ['II C', 3], ['II D', 4],
    ['III A', 5], ['III B', 6], ['III C', 7], ['III D', 8],
    ['IV A', 9], ['IV B', 10], ['IV C', 11], ['IV D', 12]
];
$s = $db->prepare("INSERT INTO kepangkatan (nama, nilai) VALUES (?, ?)");
foreach ($kepangkatan_list as $p) $s->execute($p);

$status_peg_list = [['PNS', 5], ['NON PNS', 3], ['PPPK', 4]];
$s = $db->prepare("INSERT INTO status_kepegawaian (nama, nilai) VALUES (?, ?)");
foreach ($status_peg_list as $p) $s->execute($p);

$ruangan_manajemen_list = [
    ['DIREKTUR', '700'], ['PELAYANAN', '790'],
    ['PROGRAM & KEUANGAN', '800'], ['PENUNJANG DAN SARPRAS', '800'], ['TATA USAHA', '962,6']
];
$s = $db->prepare("INSERT INTO ruangan_manajemen (nama, nilai) VALUES (?, ?)");
foreach ($ruangan_manajemen_list as $p) $s->execute($p);

$jabatan_manajemen_list = [
    ['DIREKTUR', 100], ['KABAG/KABID', 49.6], ['KASUBAG/KASI', 25.2]
];
$s = $db->prepare("INSERT INTO jabatan_manajemen (nama, nilai) VALUES (?, ?)");
foreach ($jabatan_manajemen_list as $p) $s->execute($p);

$pengurangan_list = [['TIDAK IKUT APEL', 5], ['TIDAK IKUT RAPAT MENEJEMEN', 3]];
$s = $db->prepare("INSERT INTO pengurangan_manajemen (nama, nilai) VALUES (?, ?)");
foreach ($pengurangan_list as $p) $s->execute($p);

$penilaian_list = [
    ['IKI', 'sangat baik', 5], ['IKI', 'baik', 4], ['IKI', 'cukup', 3],
    ['IKI', 'kurang', 2], ['IKI', 'sangat kurang', 1],
    ['ABSENSI', '> 200', 5], ['ABSENSI', '151-200', 4],
    ['ABSENSI', '101-150', 3], ['ABSENSI', '51-100', 2],
    ['ABSENSI', '< 50', 1], ['ABSENSI', '0', 0]
];
$s = $db->prepare("INSERT INTO penilaian_atasan (kategori, nama, nilai) VALUES (?, ?, ?)");
foreach ($penilaian_list as $p) $s->execute($p);

// Default formula
$s = $db->prepare("INSERT OR IGNORE INTO rumus (nama, ekspresi) VALUES (?, ?)");
$s->execute(['JUMLAH INDEX', '(pend+resiko+emergensi+kepeg+jabatan+sertifikat+bkerja)*(iki+penilaian)']);

// BEKAI B NON MEDIS
$db->prepare("INSERT INTO bekai_non_medis (total_manajemen,direktur,pelayanan,program_keuangan,penunjang_sarpras,tata_usaha) VALUES (222884512,20,18.82,18.82,18.82,23.54)")->execute();

$db->commit();

echo "Master data berhasil diinisialisasi.<br>";
echo "<a href='import_pegawai.php'>Lanjut Import Data Pegawai</a>";
