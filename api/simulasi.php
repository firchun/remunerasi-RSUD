<?php
require_once __DIR__ . '/../config/simulasi.php';
header('Content-Type: application/json; charset=utf-8');
$db = getSimulasiDB();
$action = $_GET['action'] ?? '';

// ============================================================
// CONFIG for master data tables
// ============================================================
$masterConfig = [
    'pendidikan' => ['table' => 'pendidikan', 'title' => 'Pendidikan', 'cols' => ['nama' => 'Nama Pendidikan', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'set_index' => ['table' => 'set_index', 'title' => 'Set Index', 'cols' => ['nama' => 'Nama Index', 'rating' => 'Rating'], 'types' => ['nama' => 'text', 'rating' => 'number']],
    'resiko' => ['table' => 'resiko', 'title' => 'Resiko', 'cols' => ['nama' => 'Index', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'emergensi' => ['table' => 'emergensi', 'title' => 'Emergensi', 'cols' => ['nama' => 'Index', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'beban_kerja' => ['table' => 'beban_kerja', 'title' => 'Beban Kerja', 'cols' => ['nama' => 'Nama', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'sertifikasi' => ['table' => 'sertifikasi', 'title' => 'Sertifikasi', 'cols' => ['nama' => 'Sertifikasi', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'pajak' => ['table' => 'pajak', 'title' => 'Pajak', 'cols' => ['nama' => 'Indeks', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'text']],
    'jabatan_ruangan' => ['table' => 'jabatan_ruangan', 'title' => 'Jabatan Ruangan', 'cols' => ['nama' => 'Jabatan', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'kepangkatan' => ['table' => 'kepangkatan', 'title' => 'Kepangkatan', 'cols' => ['nama' => 'Pangkat', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'status_kepegawaian' => ['table' => 'status_kepegawaian', 'title' => 'Status Kepegawaian', 'cols' => ['nama' => 'Status', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'ruangan_manajemen' => ['table' => 'ruangan_manajemen', 'title' => 'Ruangan Manajemen', 'cols' => ['nama' => 'Posisi', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'text']],
    'jabatan_manajemen' => ['table' => 'jabatan_manajemen', 'title' => 'Jabatan Manajemen', 'cols' => ['nama' => 'Jabatan', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'pengurangan_manajemen' => ['table' => 'pengurangan_manajemen', 'title' => 'Pengurangan Manajemen', 'cols' => ['nama' => 'Jenis', 'nilai' => 'Nilai'], 'types' => ['nama' => 'text', 'nilai' => 'number']],
    'tempat_tugas' => ['table' => 'tempat_tugas', 'title' => 'Tempat Tugas', 'cols' => ['nama' => 'Nama Tempat', 'index_resiko' => 'Index Resiko', 'index_emergensi' => 'Index Emergensi'], 'types' => ['nama' => 'text', 'index_resiko' => 'text', 'index_emergensi' => 'text']],
    'penilaian_atasan' => ['table' => 'penilaian_atasan', 'title' => 'Penilaian Atasan', 'cols' => ['kategori' => 'Kategori', 'nama' => 'Nama', 'nilai' => 'Nilai'], 'types' => ['kategori' => 'text', 'nama' => 'text', 'nilai' => 'number']],
];

// ============================================================
// MASTER MODAL (CRUD within modal)
// ============================================================
if ($action === 'master_modal') {
    header('Content-Type: text/html; charset=utf-8');
    $type = $_GET['type'] ?? '';
    if (!isset($masterConfig[$type])) { echo "<div class='text-red-500 p-4'>Unknown type</div>"; exit; }
    $cfg = $masterConfig[$type];
    $table = $cfg['table'];
    $cols = $cfg['cols'];
    $types = $cfg['types'];
    $colNames = array_keys($cols);
    $title = $cfg['title'];

    $data = $db->query("SELECT * FROM $table ORDER BY id")->fetchAll();

    $formFields = '';
    foreach ($colNames as $i => $c) {
        $label = $cols[$c];
        $placeholder = $c === 'nilai' ? 'Nilai' : ($c === 'nama' ? 'Nama' : ($c === 'rating' ? 'Rating' : $label));
        $inputType = $types[$c] === 'number' ? 'number' : 'text';
        $step = $types[$c] === 'number' ? 'step=\"any\"' : '';
        $formFields .= "<div><input type='$inputType' name='$c' placeholder='$placeholder' $step class='w-full px-2 py-1.5 border border-gray-300 rounded text-sm focus:ring-1 focus:ring-primary-500' required></div>";
    }

    $rows = '';
    foreach ($data as $i => $r) {
        $cells = '';
        foreach ($colNames as $c) {
            $cells .= "<td class='px-3 py-2 text-sm text-gray-700'>" . htmlspecialchars((string) $r[$c]) . "</td>";
        }
        $rows .= "<tr class='hover:bg-gray-50' id='mrow_{$r['id']}'>
            $cells
            <td class='px-3 py-2 text-right whitespace-nowrap'>
                <button onclick='modalEditRow(\"$type\",{$r['id']})' class='text-amber-600 hover:text-amber-800 mr-1 text-sm'><i class='fas fa-edit'></i></button>
                <button onclick='modalDeleteRow(\"$type\",{$r['id']})' class='text-red-500 hover:text-red-700 text-sm'><i class='fas fa-trash'></i></button>
            </td>
        </tr>";
    }
    if (!$data) $rows = '<tr><td colspan="' . (count($colNames) + 1) . '" class="text-center text-gray-400 py-6 text-sm">Belum ada data</td></tr>';

    $labels = '';
    foreach ($colNames as $c) {
        $labels .= "<th class='px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase'>" . htmlspecialchars($cols[$c]) . "</th>";
    }

    echo "<div class='p-5'><div class='flex justify-between items-center mb-3'><h3 class='text-lg font-bold text-gray-800'>$title</h3>
        <button onclick='closeModal()' class='text-gray-400 hover:text-gray-600'><i class='fas fa-times text-xl'></i></button></div>

        <div class='mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200'>
            <form onsubmit='return modalAddSubmit(event, \"$type\")' class='flex gap-2 items-end' data-table='$table'>
                $formFields
                <button type='submit' class='px-3 py-1.5 bg-primary-600 text-white rounded text-sm hover:bg-primary-700 whitespace-nowrap'><i class='fas fa-plus'></i> Tambah</button>
            </form>
        </div>

        <div class='overflow-x-auto max-h-80 overflow-y-auto'>
            <table class='w-full'>
                <thead class='bg-gray-50 border-b'><tr>$labels<th class='px-3 py-2 w-20'></th></tr></thead>
                <tbody class='divide-y divide-gray-100' id='modalMasterTbody'>$rows</tbody>
            </table>
        </div>
        </div>";
    exit;
}

// ============================================================
// BEKAI B NON MEDIS
// ============================================================
if ($action === 'bekai_get') {
    header('Content-Type: application/json; charset=utf-8');
    $r = $db->query("SELECT * FROM bekai_non_medis WHERE id=1")->fetch();
    if (!$r) {
        $db->prepare("INSERT INTO bekai_non_medis (id) VALUES (1)")->execute();
        $r = $db->query("SELECT * FROM bekai_non_medis WHERE id=1")->fetch();
    }
    $total = $r['total_manajemen'];
    $items = [
        ['key' => 'direktur', 'label' => 'DIREKTUR', 'persen' => $r['direktur']],
        ['key' => 'pelayanan', 'label' => 'PELAYANAN', 'persen' => $r['pelayanan']],
        ['key' => 'program_keuangan', 'label' => 'PROGRAM & KEUANGAN', 'persen' => $r['program_keuangan']],
        ['key' => 'penunjang_sarpras', 'label' => 'PENUNJANG DAN SARPRAS', 'persen' => $r['penunjang_sarpras']],
        ['key' => 'tata_usaha', 'label' => 'TATA USAHA', 'persen' => $r['tata_usaha']],
    ];

    $rows = '';
    $totalNilai = 0;
    foreach ($items as $it) {
        $k = $it['key'];
        $p = (float) $it['persen'];
        $nilai = $total * $p / 100;
        $totalNilai += $nilai;
        $rows .= "<tr class='hover:bg-gray-50'>
            <td class='px-3 py-2 text-sm'>" . htmlspecialchars($it['label']) . "</td>
            <td class='px-3 py-2'><input type='number' step='any' name='{$k}' value='" . htmlspecialchars((string) $p) . "' oninput='hitungNilai()' class='w-24 px-2 py-1 border border-gray-300 rounded text-sm text-right'></td>
            <td class='px-3 py-2 text-sm text-right font-mono' id='nilai_{$k}'>" . number_format($nilai, 2) . "</td>
        </tr>";
    }
    $totalPersen = array_sum(array_column($items, 'persen'));

    $html = "<div class='p-6'><div class='flex justify-between items-center mb-4'><h3 class='text-lg font-bold text-gray-800'>BEKAI B NON MEDIS</h3>
        <button onclick='closeModal()' class='text-gray-400 hover:text-gray-600'><i class='fas fa-times text-xl'></i></button></div>
        <form onsubmit='return bekaiSave(event)'>
        <div class='mb-4'><label class='block text-sm font-medium text-gray-700 mb-1'>Total Manajemen</label>
            <input type='number' step='any' name='total_manajemen' value='" . htmlspecialchars((string) $total) . "' oninput='hitungNilai()' class='w-64 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'></div>
        <div class='overflow-x-auto'>
            <table class='w-full text-sm'>
                <thead class='bg-gray-50 border-b'><tr>
                    <th class='px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase'>Divisi</th>
                    <th class='px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase'>Persen (%)</th>
                    <th class='px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase'>Nilai</th>
                </tr></thead>
                <tbody class='divide-y divide-gray-100'>$rows</tbody>
                <tfoot class='bg-gray-50 border-t font-semibold'>
                    <tr><td class='px-3 py-2 text-sm'>Total</td>
                        <td class='px-3 py-2 text-sm' id='totalPersen'>" . number_format($totalPersen, 2) . "%</td>
                        <td class='px-3 py-2 text-sm text-right font-mono' id='totalNilai'>" . number_format($totalNilai, 2) . "</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class='flex gap-2 justify-end mt-6'>
            <button type='button' onclick='closeModal()' class='px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50'>Tutup</button>
            <button type='submit' class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700'><i class='fas fa-save mr-1'></i> Simpan</button>
        </div></form></div>
        ";
    echo json_encode(['ok' => true, 'html' => $html]);
    exit;
}

if ($action === 'bekai_save') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $total = (float) ($_POST['total_manajemen'] ?? 222884512);
        $direktur = (float) ($_POST['direktur'] ?? 20);
        $pelayanan = (float) ($_POST['pelayanan'] ?? 18.82);
        $program_keuangan = (float) ($_POST['program_keuangan'] ?? 18.82);
        $penunjang_sarpras = (float) ($_POST['penunjang_sarpras'] ?? 18.82);
        $tata_usaha = (float) ($_POST['tata_usaha'] ?? 23.54);
        $s = $db->prepare("UPDATE bekai_non_medis SET total_manajemen=?, direktur=?, pelayanan=?, program_keuangan=?, penunjang_sarpras=?, tata_usaha=? WHERE id=1");
        $s->execute([$total, $direktur, $pelayanan, $program_keuangan, $penunjang_sarpras, $tata_usaha]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// PAGE HTML
// ============================================================
if ($action === 'page') {
    header('Content-Type: text/html; charset=utf-8');
    $name = $_GET['name'] ?? 'dashboard';
    $customPages = ['simulasi', 'tempat_tugas', 'penilaian_atasan'];
    if (in_array($name, $customPages)) {
        $file = __DIR__ . "/../views/simulasi/pages/$name.php";
        if (!file_exists($file)) {
            echo "<div class='text-red-500 p-4'>Halaman tidak ditemukan</div>";
            exit;
        }
        require $file;
        exit;
    }
    if (isset($masterConfig[$name])) {
        $cfg = $masterConfig[$name];
        $cols = $cfg['cols'];
        echo "<div class='fade-enter'>
        <div class='flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6'>
            <h2 class='text-2xl font-bold text-gray-800'>{$cfg['title']}</h2>
            <button onclick=\"addRecord('$name')\" class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors inline-flex items-center gap-2'><i class='fas fa-plus'></i>Tambah</button>
        </div>
        <div class='bg-white rounded-xl shadow-sm border border-gray-200'>
            <div class='p-4 border-b border-gray-100'>
                <input data-search='$name' placeholder='Cari...' class='w-full sm:w-64 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 outline-none'>
            </div>
            <div class='overflow-x-auto'>
            <table class='w-full'>
                <thead class='bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider'>
                    <tr><th class='px-4 py-3'>#</th>";
        foreach ($cols as $label) {
            echo "<th class='px-4 py-3'>$label</th>";
        }
        echo "<th class='px-4 py-3'>Aksi</th></tr></thead>
                <tbody data-ajax-table='$name' class='divide-y divide-gray-100'></tbody>
            </table>
            </div>
            <div class='pagination px-4 py-3 border-t border-gray-100'></div>
        </div></div>";
        exit;
    }
    echo "<div class='text-red-500 p-4'>Halaman tidak ditemukan</div>";
    exit;
}

// ============================================================
// LIST (server-side table data)
// ============================================================
if ($action === 'list') {
    $type = $_GET['type'] ?? '';
    if ($type === 'pegawai') {
        pegawaiListJSON($db);
        exit;
    }
    if ($type === 'simulasi') {
        simulasiListJSON($db);
        exit;
    }
    if ($type === 'tempat_tugas') {
        tempatTugasListJSON($db);
        exit;
    }
    if ($type === 'penilaian_atasan') {
        penilaianListJSON($db);
        exit;
    }
    if (!isset($masterConfig[$type])) {
        echo json_encode(['ok' => false, 'error' => 'Unknown type']);
        exit;
    }
    masterListJSON($db, $masterConfig[$type], $type);
    exit;
}

// ============================================================
// GET (modal form for add/edit)
// ============================================================
if ($action === 'get') {
    $type = $_GET['type'] ?? '';
    $id = (int) ($_GET['id'] ?? 0);
    if ($type === 'pegawai') {
        pegawaiGetJSON($db, $id);
        exit;
    }
    if ($type === 'set_index') {
        setIndexGetJSON($db);
        exit;
    }
    if ($type === 'tempat_tugas') {
        tempatTugasGetJSON($db, $id);
        exit;
    }
    if ($type === 'penilaian_atasan') {
        penilaianGetJSON($db, $id);
        exit;
    }
    if ($type === 'rumus') {
        rumusGetJSON($db);
        exit;
    }
    if (!isset($masterConfig[$type])) {
        echo json_encode(['ok' => false, 'error' => 'Unknown type']);
        exit;
    }
    masterGetJSON($db, $masterConfig[$type], $type, $id);
    exit;
}

// ============================================================
// SAVE
// ============================================================
if ($action === 'save') {
    $type = $_REQUEST['type'] ?? '';
    if ($type === 'pegawai') {
        pegawaiSaveJSON($db);
        exit;
    }
    if ($type === 'set_index') {
        setIndexSaveJSON($db);
        exit;
    }
    if ($type === 'tempat_tugas') {
        tempatTugasSaveJSON($db);
        exit;
    }
    if ($type === 'penilaian_atasan') {
        penilaianSaveJSON($db);
        exit;
    }
    if ($type === 'rumus') {
        $id = (int) ($_POST['id'] ?? 0);
        $ekspresi = preg_replace('/[^a-z0-9_+\-*()\/\s.]/i', '', $_POST['ekspresi'] ?? '');
        try {
            $db->prepare("UPDATE rumus SET ekspresi=? WHERE id=?")->execute([$ekspresi, $id]);
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    if (!isset($masterConfig[$type])) {
        echo json_encode(['ok' => false, 'error' => 'Unknown type']);
        exit;
    }
    masterSaveJSON($db, $masterConfig[$type], $type);
    exit;
}

// ============================================================
// DELETE
// ============================================================
if ($action === 'delete') {
    $type = $_GET['type'] ?? '';
    $id = (int) ($_GET['id'] ?? 0);
    if ($type === 'pegawai') {
        $db->prepare("DELETE FROM pegawai WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($type === 'tempat_tugas') {
        $db->prepare("DELETE FROM tempat_tugas WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
    if ($type === 'penilaian_atasan') {
        $db->prepare("DELETE FROM penilaian_atasan WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
    if (isset($masterConfig[$type])) {
        $db->prepare("DELETE FROM {$masterConfig[$type]['table']} WHERE id=?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Unknown type']);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);

// ============================================================
// FUNCTIONS
// ============================================================

function masterListJSON($db, $cfg, $type)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $length = max(5, (int) ($_GET['length'] ?? 25));
    $search = $_GET['search'] ?? '';
    $offset = ($page - 1) * $length;
    $table = $cfg['table'];
    $cols = array_keys($cfg['cols']);
    $where = '';
    $params = [];
    if ($search) {
        $likes = [];
        foreach ($cols as $c) {
            $likes[] = "$c LIKE ?";
            $params[] = "%$search%";
        }
        $where = 'WHERE ' . implode(' OR ', $likes);
    }
    $total = $db->prepare("SELECT COUNT(*) as c FROM $table $where")->fetch()['c'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM $table $where ORDER BY id LIMIT ? OFFSET ?");
    $params[] = $length;
    $params[] = $offset;
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    $rows = '';
    foreach ($data as $i => $r) {
        $num = $offset + $i + 1;
        $rows .= '<tr class="hover:bg-gray-50 transition-colors">';
        $rows .= "<td class='px-4 py-2.5 text-gray-500 text-sm w-10'>$num</td>";
        foreach ($cols as $c) {
            $rows .= '<td class="px-4 py-2.5 text-sm text-gray-700">' . htmlspecialchars($r[$c] ?? '') . '</td>';
        }
        $rows .= "<td class='px-4 py-2.5'>
            <button onclick=\"editRecord('$type',{$r['id']})\" class='text-white p-2 hover:bg-amber-800 mr-2 bg-amber-600 rounded' title='Edit'>Ubah</button>
            <button onclick=\"deleteRecord('$type',{$r['id']})\" class='text-white p-2 hover:bg-red-800 mr-2 bg-red-600 rounded' title='Hapus'>Hapus</button>
        </td></tr>";
    }
    if (!$data)
        $rows = '<tr><td colspan="' . (count($cols) + 2) . '" class="text-center text-gray-400 py-8">Tidak ada data</td></tr>';
    $pagination = paginate($total, $page, $length, $type);
    echo json_encode(['ok' => true, 'html' => $rows, 'pagination' => $pagination, 'total' => $total]);
}

function masterGetJSON($db, $cfg, $type, $id)
{
    $cols = array_keys($cfg['cols']);
    $types = $cfg['types'];
    $row = null;
    if ($id > 0) {
        $s = $db->prepare("SELECT * FROM {$cfg['table']} WHERE id=?");
        $s->execute([$id]);
        $row = $s->fetch();
        if (!$row) {
            echo json_encode(['ok' => false, 'error' => 'Data tidak ditemukan']);
            exit;
        }
    }
    $title = $id > 0 ? 'Edit ' . $cfg['title'] : 'Tambah ' . $cfg['title'];
    $html = "<div class='p-6'><div class='flex justify-between items-center mb-4'><h3 class='text-lg font-bold text-gray-800'>$title</h3>
        <button onclick='closeModal()' class='text-gray-400 hover:text-gray-600'><i class='fas fa-times text-xl'></i></button></div>
        <form onsubmit='return submitModalForm(event)' action='api/simulasi.php?action=save&type=$type' method='post'>
        <input type='hidden' name='id' value='$id'>";
    foreach ($cols as $c) {
        $label = $cfg['cols'][$c];
        $val = $row ? htmlspecialchars($row[$c]) : '';
        $html .= "<div class='mb-3'><label class='block text-sm font-medium text-gray-700 mb-1'>$label</label>
            <input type='{$types[$c]}' name='$c' value='$val' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500' required></div>";
    }
    $html .= "<div class='flex gap-2 justify-end mt-6'><button type='button' onclick='closeModal()' class='px-4 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg'>Batal</button>
        <button type='submit' class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700'><i class='fas fa-save mr-1'></i> Simpan</button></div></form></div>";
    echo json_encode(['ok' => true, 'html' => $html]);
}

function masterSaveJSON($db, $cfg, $type)
{
    $id = (int) ($_POST['id'] ?? 0);
    $cols = array_keys($cfg['cols']);
    $types = $cfg['types'];
    $vals = [];
    $set = [];
    foreach ($cols as $c) {
        $v = $_POST[$c] ?? '';
        if ($types[$c] === 'number')
            $v = (float) $v;
        $vals[] = $v;
        $set[] = "$c=?";
    }
    try {
        if ($id > 0) {
            $vals[] = $id;
            $db->prepare("UPDATE {$cfg['table']} SET " . implode(',', $set) . " WHERE id=?")->execute($vals);
        } else {
            $db->prepare("INSERT INTO {$cfg['table']} (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")")->execute($vals);
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

function paginate($total, $page, $length, $type)
{
    if ($total === 0)
        return '';
    $pages = max(1, ceil($total / $length));
    $html = '<div class="flex items-center gap-1 mt-3 flex-wrap">';
    if ($pages > 1) {
        $html .= "<button onclick=\"loadTable('$type',1)\" class=\"px-3 py-1 text-sm rounded " . ($page == 1 ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-white border hover:bg-gray-50') . "\" " . ($page == 1 ? 'disabled' : '') . "><i class='fas fa-chevron-left'></i></button>";
        for ($i = 1; $i <= $pages; $i++) {
            if ($pages > 7 && $i > 2 && $i < $pages - 1 && abs($i - $page) > 2) {
                if ($i == 3 || $i == $pages - 2)
                    $html .= '<span class="px-2 text-gray-400">...</span>';
                continue;
            }
            $active = $i == $page ? 'bg-primary-600 text-white' : 'bg-white border hover:bg-gray-50';
            $html .= "<button onclick=\"loadTable('$type',$i)\" class=\"px-3 py-1 text-sm rounded $active\">$i</button>";
        }
        $html .= "<button onclick=\"loadTable('$type',$pages)\" class=\"px-3 py-1 text-sm rounded " . ($page == $pages ? 'bg-gray-200 text-gray-400 cursor-not-allowed' : 'bg-white border hover:bg-gray-50') . "\" " . ($page == $pages ? 'disabled' : '') . "><i class='fas fa-chevron-right'></i></button>";
    } else {
        $html .= '<span class="text-xs text-gray-400">Semua data ditampilkan</span>';
    }
    $html .= "<span class='text-xs text-gray-400 ml-2'>$total data</span></div>";
    return $html;
}

// ============================================================
// TEMPAT TUGAS
// ============================================================
function tempatTugasListJSON($db)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $length = max(5, (int) ($_GET['length'] ?? 25));
    $search = $_GET['search'] ?? '';
    $offset = ($page - 1) * $length;
    $where = '';
    $params = [];
    if ($search) {
        $where = "WHERE nama LIKE ?";
        $params[] = "%$search%";
    }
    $total = $db->prepare("SELECT COUNT(*) as c FROM tempat_tugas $where")->fetch()['c'] ?? 0;
    $params[] = $length;
    $params[] = $offset;
    $stmt = $db->prepare("SELECT * FROM tempat_tugas $where ORDER BY id LIMIT ? OFFSET ?");
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    $rows = '';
    foreach ($data as $i => $r) {
        $num = $offset + $i + 1;
        $rows .= "<tr class='hover:bg-gray-50 transition-colors'>
            <td class='px-4 py-2.5 text-gray-500 text-sm'>$num</td>
            <td class='px-4 py-2.5 text-sm text-gray-700'>" . htmlspecialchars($r['nama']) . "</td>
            <td class='px-4 py-2.5'><span class='px-2 py-0.5 text-xs rounded-full " . resikoBadge($r['index_resiko']) . "'>" . $r['index_resiko'] . "</span></td>
            <td class='px-4 py-2.5'><span class='px-2 py-0.5 text-xs rounded-full " . resikoBadge($r['index_emergensi']) . "'>" . $r['index_emergensi'] . "</span></td>
            <td class='px-4 py-2.5'>
                <button onclick=\"editRecord('tempat_tugas',{$r['id']})\" class='text-amber-600 hover:text-amber-800 mr-2'>Ubah</button>
                <button onclick=\"deleteRecord('tempat_tugas',{$r['id']})\" class='text-red-500 hover:text-red-700'>Hapus</button>
            </td></tr>";
    }
    if (!$data)
        $rows = '<tr><td colspan="5" class="text-center text-gray-400 py-8">Tidak ada data</td></tr>';
    echo json_encode(['ok' => true, 'html' => $rows, 'pagination' => paginate($total, $page, $length, 'tempat_tugas'), 'total' => $total]);
}

function tempatTugasGetJSON($db, $id)
{
    $resikoList = $db->query("SELECT nama FROM resiko ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    $row = null;
    if ($id > 0) {
        $s = $db->prepare("SELECT * FROM tempat_tugas WHERE id=?");
        $s->execute([$id]);
        $row = $s->fetch();
    }
    $title = $id > 0 ? 'Edit Tempat Tugas' : 'Tambah Tempat Tugas';
    $opts = '';
    foreach ($resikoList as $r) {
        $selR = $row && $row['index_resiko'] == $r ? 'selected' : '';
        $selE = $row && $row['index_emergensi'] == $r ? 'selected' : '';
        $opts .= "<option value=\"$r\">$r</option>";
    }
    $html = "<div class='p-6'><div class='flex justify-between items-center mb-4'><h3 class='text-lg font-bold text-gray-800'>$title</h3>
        <button onclick='closeModal()' class='text-gray-400 hover:text-gray-600'><i class='fas fa-times text-xl'></i></button></div>
        <form onsubmit='return submitModalForm(event)' action='api/simulasi.php?action=save&type=tempat_tugas' method='post'>
        <input type='hidden' name='id' value='$id'>
        <div class='mb-3'><label class='block text-sm font-medium text-gray-700 mb-1'>Nama Tempat Tugas</label>
            <input type='text' name='nama' value='" . ($row ? htmlspecialchars($row['nama']) : '') . "' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500' required></div>
        <div class='mb-3'><label class='block text-sm font-medium text-gray-700 mb-1'>Index Resiko</label>
            <select name='index_resiko' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>";
    foreach ($resikoList as $r) {
        $sel = $row && $row['index_resiko'] == $r ? 'selected' : '';
        $html .= "<option value=\"$r\" $sel>$r</option>";
    }
    $html .= "</select></div><div class='mb-3'><label class='block text-sm font-medium text-gray-700 mb-1'>Index Emergensi</label>
        <select name='index_emergensi' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>";
    foreach ($resikoList as $r) {
        $sel = $row && $row['index_emergensi'] == $r ? 'selected' : '';
        $html .= "<option value=\"$r\" $sel>$r</option>";
    }
    $html .= "</select></div><div class='flex gap-2 justify-end mt-6'><button type='button' onclick='closeModal()' class='px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50'>Batal</button>
        <button type='submit' class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700'><i class='fas fa-save mr-1'></i> Simpan</button></div></form></div>";
    echo json_encode(['ok' => true, 'html' => $html]);
}

function tempatTugasSaveJSON($db)
{
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($id > 0)
            $db->prepare("UPDATE tempat_tugas SET nama=?, index_resiko=?, index_emergensi=? WHERE id=?")->execute([$_POST['nama'], $_POST['index_resiko'], $_POST['index_emergensi'], $id]);
        else
            $db->prepare("INSERT INTO tempat_tugas (nama,index_resiko,index_emergensi) VALUES (?,?,?)")->execute([$_POST['nama'], $_POST['index_resiko'], $_POST['index_emergensi']]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================
// PENILAIAN ATASAN
// ============================================================
function penilaianListJSON($db)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $length = max(5, (int) ($_GET['length'] ?? 25));
    $search = $_GET['search'] ?? '';
    $offset = ($page - 1) * $length;
    $where = '';
    $params = [];
    if ($search) {
        $where = "WHERE nama LIKE ? OR kategori LIKE ?";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $total = $db->prepare("SELECT COUNT(*) as c FROM penilaian_atasan $where")->fetch()['c'] ?? 0;
    $params[] = $length;
    $params[] = $offset;
    $stmt = $db->prepare("SELECT * FROM penilaian_atasan $where ORDER BY kategori, id LIMIT ? OFFSET ?");
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    $rows = '';
    foreach ($data as $i => $r) {
        $num = $offset + $i + 1;
        $badge = $r['kategori'] === 'IKI' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700';
        $rows .= "<tr class='hover:bg-gray-50 transition-colors'>
            <td class='px-4 py-2.5 text-gray-500 text-sm'>$num</td>
            <td class='px-4 py-2.5'><span class='px-2 py-0.5 text-xs rounded-full $badge'>" . $r['kategori'] . "</span></td>
            <td class='px-4 py-2.5 text-sm text-gray-700'>" . htmlspecialchars($r['nama']) . "</td>
            <td class='px-4 py-2.5 text-sm font-medium text-gray-800'>" . $r['nilai'] . "</td>
            <td class='px-4 py-2.5'>
                <button onclick=\"editRecord('penilaian_atasan',{$r['id']})\" class='text-amber-600 hover:text-amber-800 mr-2'>Ubah</button>
                <button onclick=\"deleteRecord('penilaian_atasan',{$r['id']})\" class='text-red-500 hover:text-red-700'>Hapus</button>
            </td></tr>";
    }
    if (!$data)
        $rows = '<tr><td colspan="5" class="text-center text-gray-400 py-8">Tidak ada data</td></tr>';
    echo json_encode(['ok' => true, 'html' => $rows, 'pagination' => paginate($total, $page, $length, 'penilaian_atasan'), 'total' => $total]);
}

function penilaianGetJSON($db, $id)
{
    $row = null;
    if ($id > 0) {
        $s = $db->prepare("SELECT * FROM penilaian_atasan WHERE id=?");
        $s->execute([$id]);
        $row = $s->fetch();
    }
    $title = $id > 0 ? 'Edit Penilaian' : 'Tambah Penilaian';
    $html = "<div class='p-6'><div class='flex justify-between items-center mb-4'><h3 class='text-lg font-bold text-gray-800'>$title</h3>
        <button onclick='closeModal()' class='text-gray-400 hover:text-gray-600'><i class='fas fa-times text-xl'></i></button></div>
        <form onsubmit='return submitModalForm(event)' action='api/simulasi.php?action=save&type=penilaian_atasan' method='post'>
        <input type='hidden' name='id' value='$id'>
        <div class='mb-3'><label class='block text-sm font-medium text-gray-700 mb-1'>Kategori</label>
            <select name='kategori' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>
                <option value='IKI' " . ($row && $row['kategori'] == 'IKI' ? 'selected' : '') . ">IKI (Indikator Kinerja Individu)</option>
                <option value='ABSENSI' " . ($row && $row['kategori'] == 'ABSENSI' ? 'selected' : '') . ">Absensi</option>
            </select></div>
        <div class='mb-3'><label class='block text-sm font-medium text-gray-700 mb-1'>Nama / Rentang</label>
            <input type='text' name='nama' value='" . ($row ? htmlspecialchars($row['nama']) : '') . "' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500' required></div>
        <div class='mb-3'><label class='block text-sm font-medium text-gray-700 mb-1'>Nilai</label>
            <input type='number' name='nilai' value='" . ($row ? $row['nilai'] : '') . "' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500' required></div>
        <div class='flex gap-2 justify-end mt-6'><button type='button' onclick='closeModal()' class='px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50'>Batal</button>
            <button type='submit' class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700'><i class='fas fa-save mr-1'></i> Simpan</button></div></form></div>";
    echo json_encode(['ok' => true, 'html' => $html]);
}

function penilaianSaveJSON($db)
{
    $id = (int) ($_POST['id'] ?? 0);
    try {
        if ($id > 0)
            $db->prepare("UPDATE penilaian_atasan SET kategori=?, nama=?, nilai=? WHERE id=?")->execute([$_POST['kategori'], $_POST['nama'], (int) $_POST['nilai'], $id]);
        else
            $db->prepare("INSERT INTO penilaian_atasan (kategori,nama,nilai) VALUES (?,?,?)")->execute([$_POST['kategori'], $_POST['nama'], (int) $_POST['nilai']]);
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

// ============================================================
// PEGAWAI
// ============================================================
function pegawaiListJSON($db)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $length = max(5, (int) ($_GET['length'] ?? 50));
    $search = $_GET['search'] ?? '';
    $offset = ($page - 1) * $length;
    $where = '';
    $params = [];
    if ($search) {
        $where = "WHERE p.nama LIKE ?";
        $params[] = "%$search%";
    }
    $total = $db->prepare("SELECT COUNT(*) as c FROM pegawai p $where")->fetch()['c'] ?? 0;
    $sql = "SELECT p.*, t.nama as ruang, t.index_resiko, t.index_emergensi,
        j.nama as posisi, k.nama as pangkat, s.nama as status, d.nama as pendidikan,
        rs.nilai as resiko_nilai, em.nilai as emergensi_nilai
        FROM pegawai p LEFT JOIN tempat_tugas t ON p.ruang_kerja_id=t.id
        LEFT JOIN jabatan_ruangan j ON p.posisi_ruangan_id=j.id
        LEFT JOIN kepangkatan k ON p.pangkat_id=k.id
        LEFT JOIN status_kepegawaian s ON p.status_kepegawaian_id=s.id
        LEFT JOIN pendidikan d ON p.pendidikan_id=d.id
        LEFT JOIN resiko rs ON t.index_resiko=rs.nama
        LEFT JOIN emergensi em ON t.index_emergensi=em.nama
        $where ORDER BY p.nama LIMIT ? OFFSET ?";
    $params[] = $length;
    $params[] = $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    $rows = '';
    foreach ($data as $i => $r) {
        $num = $offset + $i + 1;
        $rs = $r['index_resiko'] ? ($r['index_resiko'] . ' / ' . $r['resiko_nilai']) : '-';
        $re = $r['index_emergensi'] ? ($r['index_emergensi'] . ' / ' . $r['emergensi_nilai']) : '-';
        $rows .= "<tr class='hover:bg-gray-50 transition-colors'>
            <td class='px-4 py-2.5 text-gray-500 text-sm'>$num</td>
            <td class='px-4 py-2.5 text-sm font-medium text-gray-800'>" . htmlspecialchars($r['nama']) . "</td>
            <td class='px-4 py-2.5 text-sm text-gray-600'>" . htmlspecialchars($r['ruang'] ?? '-') . "</td>
            <td class='px-4 py-2.5 text-sm text-gray-600'>" . htmlspecialchars($r['posisi'] ?? '-') . "</td>
            <td class='px-4 py-2.5 text-sm text-gray-600'>" . htmlspecialchars($r['pangkat'] ?? '-') . "</td>
            <td class='px-4 py-2.5 text-sm text-gray-600'>" . htmlspecialchars($r['status'] ?? '-') . "</td>
            <td class='px-4 py-2.5 text-sm text-gray-600'>" . htmlspecialchars($r['pendidikan'] ?? '-') . "</td>
            <td class='px-4 py-2.5 text-xs text-gray-600'>$rs</td>
            <td class='px-4 py-2.5 text-xs text-gray-600'>$re</td>
            <td class='px-4 py-2.5'>
                <button onclick=\"editRecord('pegawai',{$r['id']})\" class='text-white p-2 hover:bg-amber-800 mr-2 bg-amber-600 rounded' title='Edit'>Ubah</button>
                <button onclick=\"deleteRecord('pegawai',{$r['id']})\" class='text-white p-2 hover:bg-red-800 mr-2 bg-red-600 rounded' title='Hapus'>Hapus</button>
            </td></tr>";
    }
    if (!$data)
        $rows = '<tr><td colspan="10" class="text-center text-gray-400 py-8">Tidak ada data</td></tr>';
    echo json_encode(['ok' => true, 'html' => $rows, 'pagination' => paginate($total, $page, $length, 'pegawai'), 'total' => $total]);
}

function pegawaiGetJSON($db, $id)
{
    $refs = [];
    $refs['tempat'] = $db->query("SELECT id, nama FROM tempat_tugas ORDER BY nama")->fetchAll();
    $refs['jabatan'] = $db->query("SELECT id, nama FROM jabatan_ruangan ORDER BY nama")->fetchAll();
    $refs['pangkat'] = $db->query("SELECT id, nama FROM kepangkatan ORDER BY id")->fetchAll();
    $refs['status'] = $db->query("SELECT id, nama FROM status_kepegawaian ORDER BY id")->fetchAll();
    $refs['pendidikan'] = $db->query("SELECT id, nama FROM pendidikan ORDER BY id")->fetchAll();
    $refs['sertifikat'] = $db->query("SELECT id, nama FROM sertifikasi ORDER BY id")->fetchAll();
    $refs['beban'] = $db->query("SELECT id, nama FROM beban_kerja ORDER BY nama")->fetchAll();
    $refs['iki'] = $db->query("SELECT id, nama, nilai FROM penilaian_atasan WHERE kategori='IKI' ORDER BY nilai DESC")->fetchAll();
    $refs['absensi'] = $db->query("SELECT id, nama, nilai FROM penilaian_atasan WHERE kategori='ABSENSI' ORDER BY nilai DESC")->fetchAll();

    $refs['resiko_map'] = [];
    foreach ($db->query("SELECT id, nama, nilai FROM resiko")->fetchAll() as $r) {
        $refs['resiko_map'][$r['nama']] = $r['nilai'];
    }
    $refs['emergensi_map'] = [];
    foreach ($db->query("SELECT id, nama, nilai FROM emergensi")->fetchAll() as $r) {
        $refs['emergensi_map'][$r['nama']] = $r['nilai'];
    }

    $row = null;
    $rowResiko = '';
    $rowEmergensi = '';
    if ($id > 0) {
        $s = $db->prepare("SELECT p.*, t.index_resiko, t.index_emergensi FROM pegawai p LEFT JOIN tempat_tugas t ON p.ruang_kerja_id=t.id WHERE p.id=?");
        $s->execute([$id]);
        $row = $s->fetch();
        if ($row && $row['index_resiko']) {
            $rn = $refs['resiko_map'][$row['index_resiko']] ?? '';
            $rowResiko = $row['index_resiko'] . ' / ' . $rn;
        }
        if ($row && $row['index_emergensi']) {
            $en = $refs['emergensi_map'][$row['index_emergensi']] ?? '';
            $rowEmergensi = $row['index_emergensi'] . ' / ' . $en;
        }
    }
    $title = $id > 0 ? 'Edit Pegawai' : 'Tambah Pegawai';

    function opt($list, $sel, $v = 'id', $l = 'nama')
    {
        $h = '<option value="">-- Pilih --</option>';
        foreach ($list as $r) {
            $s = $sel == $r[$v] ? 'selected' : '';
            $h .= "<option value=\"{$r[$v]}\" $s>" . htmlspecialchars($r[$l]) . "</option>";
        }
        return $h;
    }

    $ttData = [];
    foreach ($refs['tempat'] as $tt) {
        $s = $db->prepare("SELECT index_resiko, index_emergensi FROM tempat_tugas WHERE id=?");
        $s->execute([$tt['id']]);
        $t = $s->fetch();
        $ttData[$tt['id']] = [
            'resiko' => $t['index_resiko'] ?? '',
            'emergensi' => $t['index_emergensi'] ?? ''
        ];
    }
    $ttJson = json_encode($ttData);
    $rmJson = json_encode($refs['resiko_map']);
    $emJson = json_encode($refs['emergensi_map']);

    $html = "<div class='p-6'><div class='flex justify-between items-center mb-4'><h3 class='text-lg font-bold text-gray-800'>$title</h3>
        <button onclick='closeModal()' class='text-gray-400 hover:text-gray-600'><i class='fas fa-times text-xl'></i></button></div>
        <form onsubmit='return submitModalForm(event)' action='api/simulasi.php?action=save&type=pegawai' method='post'>
        <input type='hidden' name='id' value='$id'>
        <div id='pegawaiTtData' data-tt='" . htmlspecialchars($ttJson) . "' data-rm='" . htmlspecialchars($rmJson) . "' data-em='" . htmlspecialchars($emJson) . "'></div>
        <div class='grid grid-cols-1 sm:grid-cols-2 gap-3'>
            <div class='col-span-2'><label class='block text-sm font-medium text-gray-700 mb-1'>Nama</label>
                <input type='text' name='nama' value='" . ($row ? htmlspecialchars($row['nama']) : '') . "' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500' required></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Ruangan Kerja</label><select name='ruang_kerja_id' onchange='updateResikoEmergensi(this)' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['tempat'], $row['ruang_kerja_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Resiko</label><input type='text' id='resikoDisplay' readonly value='" . htmlspecialchars($rowResiko) . "' class='w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-sm text-gray-600'></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Emergensi</label><input type='text' id='emergensiDisplay' readonly value='" . htmlspecialchars($rowEmergensi) . "' class='w-full px-3 py-2 border border-gray-200 rounded-lg bg-gray-50 text-sm text-gray-600'></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Posisi Ruangan</label><select name='posisi_ruangan_id' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['jabatan'], $row['posisi_ruangan_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Pangkat</label><select name='pangkat_id' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['pangkat'], $row['pangkat_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Status Kepegawaian</label><select name='status_kepegawaian_id' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['status'], $row['status_kepegawaian_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Pendidikan</label><select name='pendidikan_id' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['pendidikan'], $row['pendidikan_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Sertifikat</label><select name='sertifikat_id' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['sertifikat'], $row['sertifikat_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Beban Kerja</label><select name='beban_kerja_id' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['beban'], $row['beban_kerja_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>IKI</label><select name='iki_id' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['iki'], $row['iki_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Absensi</label><select name='absensi_id' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'>" . opt($refs['absensi'], $row['absensi_id'] ?? '') . "</select></div>
            <div><label class='block text-sm font-medium text-gray-700 mb-1'>Index Koreksi</label>
                <input type='number' name='index_koreksi' step='any' value='" . ($row ? htmlspecialchars($row['index_koreksi']) : '1') . "' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'></div>
        </div>
        <div class='flex gap-2 justify-end mt-6'><button type='button' onclick='closeModal()' class='px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50'>Batal</button>
            <button type='submit' class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700'><i class='fas fa-save mr-1'></i> Simpan</button></div></form></div>";
    echo json_encode(['ok' => true, 'html' => $html]);
}

function pegawaiSaveJSON($db)
{
    $id = (int) ($_POST['id'] ?? 0);
    $fields = ['nama', 'ruang_kerja_id', 'posisi_ruangan_id', 'pangkat_id', 'status_kepegawaian_id', 'pendidikan_id', 'sertifikat_id', 'beban_kerja_id', 'iki_id', 'absensi_id', 'index_koreksi'];
    $vals = [];
    foreach ($fields as $f) {
        $v = $_POST[$f] ?? '';
        if ($f !== 'nama') {
            if ($f === 'index_koreksi')
                $v = $v !== '' ? (float) $v : null;
            else
                $v = $v ? (int) $v : null;
        }
        $vals[] = $v;
    }
    try {
        if ($id > 0) {
            $set = implode(',', array_map(fn($f) => "$f=?", $fields));
            $vals[] = $id;
            $db->prepare("UPDATE pegawai SET $set WHERE id=?")->execute($vals);
        } else {
            $phs = implode(',', array_fill(0, count($fields), '?'));
            $db->prepare("INSERT INTO pegawai (" . implode(',', $fields) . ") VALUES ($phs)")->execute($vals);
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

function evaluasiRumus($ekspresi, $vars)
{
    $allowed = array_keys($vars);
    $result = $ekspresi;
    foreach ($vars as $k => $v) {
        $result = str_replace($k, '(' . (float) $v . ')', $result);
    }
    if (preg_match('/[^0-9+\-*()\/\s.]/', $result)) {
        return 0;
    }
    $php = '$__r = ' . $result . ';';
    try {
        eval($php);
        return $__r ?? 0;
    } catch (\Throwable $e) {
        return 0;
    }
}

// ============================================================
// SIMULASI
// ============================================================
function simulasiListJSON($db)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $length = max(5, (int) ($_GET['length'] ?? 50));
    $search = $_GET['search'] ?? '';
    $offset = ($page - 1) * $length;

    $allCols = ['v_kr', 'v_p', 'v_rs', 'v_re', 'v_sk', 'v_jr', 'v_sf', 'v_bk', 'v_ik', 'v_ab'];
    $colDefs = [
        'v_kr' => ['Koreksi', ''],
        'v_p' => ['Pend', 'x3'],
        'v_rs' => ['Resiko', 'x5'],
        'v_re' => ['Emergensi', 'x5'],
        'v_sk' => ['Kepeg', 'x2'],
        'v_jr' => ['Jabatan', 'x2'],
        'v_sf' => ['Sertifikat', 'x1'],
        'v_bk' => ['B.Kerja', 'x2'],
        'v_ik' => ['IKI', ''],
        'v_ab' => ['Penilaian', ''],
    ];
    $cols = isset($_GET['cols']) ? array_intersect(explode(',', $_GET['cols']), $allCols) : [];
    $hideAksi = isset($_GET['hide_aksi']);

    $indices = [];
    foreach ($db->query("SELECT nama, rating FROM set_index")->fetchAll() as $r) {
        $indices[strtolower(trim($r['nama']))] = (float) $r['rating'];
    }
    $i_p = $indices['pendidikan'] ?? 3;
    $i_bk = $indices['beban kerja'] ?? 2;
    $i_rk = $indices['resiko kerja'] ?? 5;
    $i_em = $indices['emergensi'] ?? 5;
    $i_kp = $indices['kepegawaian'] ?? 2;
    $i_jr = $indices['jabatan ruangan'] ?? 2;
    $i_sf = $indices['keahlian/sertifikat'] ?? 1;
    $i_ik = $indices['indeks kinerja individu'] ?? 0;

    $where = '';
    $params = [];
    if ($search) {
        $where = "WHERE p.nama LIKE ?";
        $params[] = "%$search%";
    }

    $total = $db->prepare("SELECT COUNT(*) as c FROM pegawai p $where")->fetch()['c'] ?? 0;

    $sql = "SELECT p.id, p.nama, p.index_koreksi, tt.nama as tempat, pnd.nilai as pnd_n, pnd.nama as pnd_nama,
        rs.nilai as rs_n, em.nilai as re_n, sk.nilai as sk_n, jr.nilai as jr_n,
        sf.nilai as sf_n, bk.nilai as bk_n, iki.nilai as iki_n, abs.nilai as abs_n
        FROM pegawai p
        LEFT JOIN tempat_tugas tt ON p.ruang_kerja_id=tt.id
        LEFT JOIN resiko rs ON tt.index_resiko=rs.nama
        LEFT JOIN emergensi em ON tt.index_emergensi=em.nama
        LEFT JOIN status_kepegawaian sk ON p.status_kepegawaian_id=sk.id
        LEFT JOIN jabatan_ruangan jr ON p.posisi_ruangan_id=jr.id
        LEFT JOIN pendidikan pnd ON p.pendidikan_id=pnd.id
        LEFT JOIN sertifikasi sf ON p.sertifikat_id=sf.id
        LEFT JOIN beban_kerja bk ON p.beban_kerja_id=bk.id
        LEFT JOIN penilaian_atasan iki ON p.iki_id=iki.id
        LEFT JOIN penilaian_atasan abs ON p.absensi_id=abs.id
        $where ORDER BY p.nama LIMIT ? OFFSET ?";
    $params[] = $length;
    $params[] = $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    $rumusRow = $db->query("SELECT ekspresi FROM rumus WHERE nama='JUMLAH INDEX' LIMIT 1")->fetch();
    $rumus = $rumusRow ? $rumusRow['ekspresi'] : '(pend+resiko+emergensi+kepeg+jabatan+sertifikat+bkerja)*(iki+penilaian)';

    $results = [];
    foreach ($data as $r) {
        $v_kr = (float) ($r['index_koreksi'] ?? 1);
        $v_p = ($r['pnd_n'] ?? 0) * $i_p;
        $v_rs = ($r['rs_n'] ?? 0) * $i_rk;
        $v_re = ($r['re_n'] ?? 0) * $i_em;
        $v_sk = ($r['sk_n'] ?? 0) * $i_kp;
        $v_jr = ($r['jr_n'] ?? 0) * $i_jr;
        $v_sf = ($r['sf_n'] ?? 0) * $i_sf;
        $v_bk = ($r['bk_n'] ?? 0) * $i_bk;
        $v_ik = ($r['iki_n'] ?? 0);
        $v_ab = $r['abs_n'] ?? 0;
        $vars = compact('v_p', 'v_rs', 'v_re', 'v_sk', 'v_jr', 'v_sf', 'v_bk', 'v_ik', 'v_ab', 'v_kr');
        $rumusVars = [
            'pend' => $v_p, 'resiko' => $v_rs, 'emergensi' => $v_re,
            'kepeg' => $v_sk, 'jabatan' => $v_jr, 'sertifikat' => $v_sf,
            'bkerja' => $v_bk, 'iki' => $v_ik, 'penilaian' => $v_ab,
            'koreksi' => $v_kr,
        ];
        $total = evaluasiRumus($rumus, $rumusVars);
        $v_ia = $total * $v_kr;
        $results[] = compact('v_p', 'v_rs', 'v_re', 'v_sk', 'v_jr', 'v_sf', 'v_bk', 'v_ik', 'v_ab', 'v_kr', 'total', 'v_ia') + ['id' => $r['id'], 'nama' => $r['nama'], 'tempat' => $r['tempat'] ?? '-', 'pnd_nama' => $r['pnd_nama'] ?? '-'];
    }
    usort($results, fn($a, $b) => $b['v_ia'] <=> $a['v_ia']);

    // Build thead
    $thead = '<tr>
        <th class="px-3 py-3">#</th>
        <th class="px-3 py-3">Nama</th>
        <th class="px-3 py-3">Ruangan</th>
        <th class="px-3 py-3">Pendidikan</th>';
    foreach ($allCols as $ck) {
        if (in_array($ck, $cols)) {
            $l = $colDefs[$ck][0];
            $m = $colDefs[$ck][1];
            $ml = $m ? " <small>x$m</small>" : '';
            $thead .= "<th class='px-3 py-3 text-right' data-col='$ck'>$l$ml</th>";
        }
    }
    $aksiCol = $hideAksi ? '' : '<th class="px-3 py-3 w-16">Aksi</th>';
    $thead .= '<th class="px-3 py-3 text-right font-bold">Total Index</th>';
    $thead .= '<th class="px-3 py-3 text-right font-bold text-primary-700">Index Akhir</th>' . $aksiCol . '</tr>';

    $colspan = 5 + count($cols) + 2 + ($hideAksi ? 0 : 1);

    // Build rows
    if (!$results) {
        $rows = '<tr><td colspan="' . $colspan . '" class="text-center text-gray-400 py-8">Tidak ada data</td></tr>';
        echo json_encode(['ok' => true, 'html' => $rows, 'thead' => $thead, 'pagination' => '', 'total' => 0]);
        return;
    }
    $rows = '';
    foreach ($results as $i => $r) {
        $num = $offset + $i + 1;
        $rows .= "<tr class='hover:bg-gray-50 transition-colors'>
            <td class='px-3 py-2.5 text-gray-500 text-xs'>$num</td>
            <td class='px-3 py-2.5 text-sm font-medium text-gray-800'>" . htmlspecialchars($r['nama']) . "</td>
            <td class='px-3 py-2.5 text-xs text-gray-600'>" . htmlspecialchars($r['tempat']) . "</td>
            <td class='px-3 py-2.5 text-xs text-gray-500'>" . htmlspecialchars($r['pnd_nama']) . "</td>";
        foreach ($allCols as $ck) {
            if (in_array($ck, $cols)) {
                $rows .= "<td class='px-3 py-2.5 text-sm text-right font-mono' data-col='$ck'>" . number_format($r[$ck], 0) . "</td>";
            }
        }
        $rows .= "<td class='px-3 py-2.5 text-sm text-right font-mono font-bold'>" . number_format($r['total'], 0) . "</td>";
        $rows .= "<td class='px-3 py-2.5 text-sm text-right font-mono font-bold text-primary-700'>" . number_format($r['v_ia'], 0) . "</td>";
        if (!$hideAksi)
            $rows .= "<td class='px-3 py-2.5'><button onclick=\"editRecord('pegawai',{$r['id']})\" class='text-white p-1 bg-amber-600 hover:bg-amber-800 rounded text-xs' title='Ubah'>Ubah</button></td>";
        $rows .= "</tr>";
    }
    echo json_encode(['ok' => true, 'html' => $rows, 'thead' => $thead, 'pagination' => paginate($total, $page, $length, 'simulasi'), 'total' => $total]);
}

function setIndexGetJSON($db)
{
    $rows = $db->query("SELECT id, nama, rating FROM set_index ORDER BY id")->fetchAll();
    $fields = '';
    foreach ($rows as $r) {
        $label = htmlspecialchars(ucfirst($r['nama']));
        $id = $r['id'];
        $val = htmlspecialchars((string) $r['rating']);
        $fields .= "<div><label class='block text-sm font-medium text-gray-700 mb-1'>$label</label>
            <input type='number' step='any' name='idx_$id' value='$val' class='w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500'></div>";
    }
    $html = "<div class='p-6'><div class='flex justify-between items-center mb-4'><h3 class='text-lg font-bold text-gray-800'>Atur Index</h3>
        <button onclick='closeModal()' class='text-gray-400 hover:text-gray-600'><i class='fas fa-times text-xl'></i></button></div>
        <form onsubmit='return submitModalForm(event)' action='api/simulasi.php?action=save&type=set_index' method='post'>
        <input type='hidden' name='id' value='1'>
        <div class='grid grid-cols-1 sm:grid-cols-2 gap-3'>$fields</div>
        <div class='flex gap-2 justify-end mt-6'><button type='button' onclick='closeModal()' class='px-4 py-2 text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50'>Batal</button>
            <button type='submit' class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700'><i class='fas fa-save mr-1'></i> Simpan</button></div></form></div>";
    echo json_encode(['ok' => true, 'html' => $html]);
}

function setIndexSaveJSON($db)
{
    try {
        foreach ($_POST as $k => $v) {
            if (str_starts_with($k, 'idx_')) {
                $id = (int) substr($k, 4);
                $db->prepare("UPDATE set_index SET rating=? WHERE id=?")->execute([(float) $v, $id]);
            }
        }
        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

function rumusGetJSON($db)
{
    $row = $db->query("SELECT * FROM rumus WHERE nama='JUMLAH INDEX' LIMIT 1")->fetch();
    $id = $row['id'] ?? 0;
    $ekspresi = $row ? $row['ekspresi'] : '(pend+resiko+emergensi+kepeg+jabatan+sertifikat+bkerja)*(iki+penilaian)';

    $vars = [
        'pend' => 'Pendidikan', 'resiko' => 'Resiko', 'emergensi' => 'Emergensi',
        'kepeg' => 'Kepegawaian', 'jabatan' => 'Jabatan', 'sertifikat' => 'Sertifikat',
        'bkerja' => 'B.Kerja', 'iki' => 'IKI', 'penilaian' => 'Penilaian',
        'koreksi' => 'Koreksi',
    ];
    $btnHtml = '';
    $idx = 1;
    foreach ($vars as $k => $v) {
        $cls = $idx <= 7 ? 'bg-primary-50 text-primary-700 border-primary-200 hover:bg-primary-100' : 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100';
        $btnHtml .= "<button type='button' onclick='modalAddVar(\"$k\")' class='px-3 py-1.5 $cls border rounded-md text-xs font-semibold transition-colors'>$v</button>";
        $idx++;
    }

    $ops = [['+','+'],['-','-'],['&times;','*'],['/','/'],['%','%'],['(', '('],[')', ')']];
    $opHtml = '';
    foreach ($ops as $o) {
        $opHtml .= "<button type='button' onclick='modalAddOp(\"{$o[1]}\")' class='w-9 h-9 bg-gray-100 text-gray-700 border border-gray-300 rounded-md text-sm font-bold hover:bg-gray-200 transition-colors'>{$o[0]}</button>";
    }

    $html = "<div class='p-6'><div class='flex justify-between items-center mb-4'><h3 class='text-lg font-bold text-gray-800'>Rumus Perhitungan</h3>
        <button onclick='closeModal()' class='text-gray-400 hover:text-gray-600'><i class='fas fa-times text-xl'></i></button></div>
        <form onsubmit='return submitModalForm(event)' action='api/simulasi.php?action=save&type=rumus' method='post'>
        <input type='hidden' name='id' value='$id'>
        <input type='hidden' name='ekspresi' id='modalFormulaInput' value='" . htmlspecialchars($ekspresi) . "'>
        <div class='mb-3'>
            <div id='modalFormulaDisplay' class='w-full min-h-[40px] px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 font-mono text-sm leading-relaxed break-all'></div>
        </div>
        <div class='mb-3'>
            <div class='text-xs font-medium text-gray-500 mb-1.5'>INDEX</div>
            <div class='flex flex-wrap gap-1.5'>$btnHtml</div>
        </div>
        <div class='mb-3'>
            <div class='text-xs font-medium text-gray-500 mb-1.5'>OPERATOR</div>
            <div class='flex flex-wrap gap-1.5'>$opHtml</div>
        </div>
        <div class='flex gap-2 justify-between items-center'>
            <div class='flex gap-2'>
                <button type='button' onclick='modalBackspace()' class='px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50 inline-flex items-center gap-1'><i class='fas fa-delete-left'></i> Hapus</button>
                <button type='button' onclick='modalClearFormula()' class='px-3 py-1.5 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50 inline-flex items-center gap-1'><i class='fas fa-eraser'></i> Bersihkan</button>
            </div>
            <button type='submit' class='px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700'><i class='fas fa-save mr-1'></i> Simpan</button>
        </div>
        </form></div>";
    echo json_encode(['ok' => true, 'html' => $html]);
}

function resikoBadge($v)
{
    return match ($v) { 'I' => 'bg-green-100 text-green-700', 'II' => 'bg-blue-100 text-blue-700', 'III' => 'bg-amber-100 text-amber-700', 'IV' => 'bg-red-100 text-red-700', default => 'bg-gray-100 text-gray-700'};
}
