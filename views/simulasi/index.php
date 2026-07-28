<?php
$pageTitle = 'Simulasi Remunerasi - RSUD MERAUKE';
$rootPath = '../../';
$extraHead = '
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          primary: {
            50: "#eff6ff", 100: "#dbeafe", 200: "#bfdbfe", 300: "#93c5fd",
            400: "#065f46", 500: "#065f46", 600: "#065f46", 700: "#065f46",
            800: "#065f46", 900: "#065f46"
          }
        }
      }
    }
  }
</script>
<style>
  [x-cloak] { display: none !important }
  .fade-enter { animation: fadeIn .2s ease }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px) }
    to { opacity: 1; transform: translateY(0) }
  }
  @media print {
    body * { visibility: hidden; }
    #printArea, #printArea * { visibility: visible; }
    #printArea { position: fixed; inset: 0; padding: 20px; }
    #printArea table { width: 100%; border-collapse: collapse; font-size: 10px; }
    #printArea th, #printArea td { border: 1.5px solid #000; padding: 6px 8px; text-align: center; }
    #printArea th { background: #f0f0f0; font-weight: bold; }
    #printArea .print-title { text-align: center; font-size: 14px; font-weight: bold; margin-bottom: 4px; }
    #printArea .print-subtitle { text-align: center; font-size: 10px; margin-bottom: 16px; }
  }
</style>';
require_once '../layouts/header.php';
?>

<div id="simulasi-app" class="simulasi-app">
  <nav class="top-0 z-50 bg-white border-b border-gray-200 shadow-sm flex justify-center rounded-lg mb-4">
    <div class="w-full px-4">
      <div class="flex items-center justify-between h-14">
        <div class="flex items-center gap-1">
          <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" @click.outside="open = false"
              class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-colors">
              <i class="fas fa-database"></i><span class="hidden sm:inline">Master Data</span>
              <i class="fas fa-chevron-down text-xs" :class="{'rotate-180':open}"></i>
            </button>
            <div x-show="open" x-cloak
              class="absolute left-0 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50 max-h-96 overflow-y-auto">
              <?php
              $simDbPath = __DIR__ . '/../../database/simulasi.sqlite';
              if (file_exists($simDbPath)) {
                $menudb = new PDO("sqlite:$simDbPath");
                $menudb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $menudb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $menu = [
                  'pendidikan' => 'Pendidikan',
                  'set_index' => 'Set Index',
                  'tempat_tugas' => 'Tempat Tugas',
                  'resiko' => 'Resiko',
                  'emergensi' => 'Emergensi',
                  'beban_kerja' => 'Beban Kerja',
                  'sertifikasi' => 'Sertifikasi',
                  'pajak' => 'Pajak',
                  'jabatan_ruangan' => 'Jabatan Ruangan',
                  'kepangkatan' => 'Kepangkatan',
                  'status_kepegawaian' => 'Status Kepegawaian',
                  'ruangan_manajemen' => 'Ruangan Manajemen',
                  'jabatan_manajemen' => 'Jabatan Manajemen',
                  'pengurangan_manajemen' => 'Pengurangan Manajemen',
                  'penilaian_atasan' => 'Penilaian Atasan'
                ];
                foreach ($menu as $k => $v) {
                  $cnt = $menudb->query("SELECT COUNT(*) as c FROM $k")->fetch()['c'] ?? 0;
                  echo "<a href=\"#\" onclick=\"openMasterModal('$k');return false\" class=\"flex items-center gap-3 px-4 py-2 text-sm text-gray-600 hover:bg-primary-50 hover:text-primary-700 transition-colors\">
                    <span class=\"flex-1\">$v</span><span class=\"text-xs bg-gray-100 text-gray-500 px-1.5 py-0.5 rounded-full\">$cnt</span>
                  </a>\n";
                }
              }
              ?>
            </div>
          </div>

          <a href="#" onclick="openBekai();return false"
            class="flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-colors">
            <i class="fas fa-calculator"></i><span class="hidden sm:inline">BEKAI B NON MEDIS</span>
          </a>
          <a href="#" data-page="simulasi"
            class="nav-link flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-primary-50 hover:text-primary-700 transition-colors">
            <i class="fas fa-chart-bar"></i><span class="hidden sm:inline">Simulasi</span>
          </a>
        </div>
      </div>
    </div>
  </nav>

  <main id="simulasiContent" class="fade-enter"></main>
</div>

<div id="simulasiModalOverlay" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4"
  onclick="if(event.target===this)closeSimulasiModal()">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" id="simulasiModalBody">
  </div>
</div>

<div id="simulasiLoading" class="fixed inset-0 bg-white/70 z-40 hidden items-center justify-center">
  <div class="flex flex-col items-center gap-3">
    <div class="w-10 h-10 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
    <span class="text-sm text-gray-500">Loading...</span>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
<script>
  const SIM_MAIN = document.getElementById('simulasiContent');
  const SIM_MODAL = document.getElementById('simulasiModalOverlay');
  const SIM_MBODY = document.getElementById('simulasiModalBody');
  const SIM_LOADING = document.getElementById('simulasiLoading');
  const SIM_API = (window.BASE_URL || '') + '/api/simulasi.php';

  function simShowLoading() { SIM_LOADING.classList.remove('hidden') }
  function simHideLoading() { SIM_LOADING.classList.add('hidden') }

  function openSimulasiModal(html) {
    SIM_MBODY.innerHTML = html;
    SIM_MODAL.classList.remove('hidden');
    SIM_MODAL.classList.add('flex');
    const tt = document.querySelector('#pegawaiTtData + .grid select[name="ruang_kerja_id"]');
    if (tt) updateResikoEmergensi(tt);
    if (document.getElementById('modalFormulaInput')) initModalFormula();
  }
  function closeSimulasiModal() {
    SIM_MODAL.classList.add('hidden');
    SIM_MODAL.classList.remove('flex');
    SIM_MBODY.innerHTML = '';
  }
  window.closeModal = closeSimulasiModal;
  window.addRecord = simAddRecord;
  window.editRecord = simEditRecord;
  window.deleteRecord = simDeleteRecord;
  window.loadTable = simLoadTable;

  let simCurrentPage = '';

  async function simLoadPage(page) {
    if (page === simCurrentPage) return;
    simCurrentPage = page;
    simShowLoading();
    try {
      const r = await fetch(SIM_API + '?action=page&name=' + page);
      const html = await r.text();
      SIM_MAIN.innerHTML = html;
      document.querySelectorAll('#simulasi-app .nav-link').forEach(el => {
        el.classList.toggle('bg-primary-50', el.dataset.page === page);
        el.classList.toggle('text-primary-700', el.dataset.page === page);
      });
      document.title = 'Simulasi Remun - ' + (document.querySelector('#simulasi-app h2,#simulasi-app h3')?.textContent?.trim() || page);
      simInitTableHandlers();
    } catch (e) {
      SIM_MAIN.innerHTML = '<div class="text-red-500 p-4">Gagal memuat halaman</div>';
    }
    simHideLoading();
  }

  function simInitTableHandlers() {
    document.querySelectorAll('#simulasi-app .nav-link[data-page]').forEach(el => {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        simLoadPage(this.dataset.page);
      });
    });
    document.querySelectorAll('#simulasi-app [data-ajax-table]').forEach(tbl => simLoadTable(tbl.dataset.ajaxTable));
    document.querySelectorAll('#simulasi-app [data-modal-form]').forEach(f => {
      f.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = this.querySelector('[type="submit"]');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        const fd = new FormData(this);
        const actionUrl = (window.BASE_URL || '') + '/' + this.getAttribute('action');
        const r = await fetch(actionUrl, { method: 'POST', body: fd });
        const json = await r.json();
        btn.disabled = false;
        btn.textContent = 'Simpan';
        if (json.ok) {
          closeSimulasiModal();
          simLoadTable(this.dataset.refresh || '');
        } else alert(json.error || 'Gagal');
      });
    });
    document.querySelectorAll('#simulasi-app [data-delete-url]').forEach(el => {
      el.addEventListener('click', async function () {
        if (!confirm('Hapus data ini?')) return;
        const r = await fetch(SIM_API + '?' + this.dataset.deleteUrl);
        const json = await r.json();
        if (json.ok) simLoadTable(this.dataset.refresh || '');
        else alert(json.error || 'Gagal');
      });
    });
    document.querySelectorAll('#simulasi-app [data-page-link]').forEach(el => {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        simLoadPage(this.dataset.pageLink);
      });
    });
  }

  async function simLoadTable(type, page = 1, search = '') {
    const tbl = document.querySelector(`#simulasi-app [data-ajax-table="${type}"]`);
    if (!tbl) return;
    const tbody = tbl.querySelector('tbody') || tbl;
    const length = tbl.dataset.length || 25;
    simShowLoading();
    try {
      const r = await fetch(SIM_API + `?action=list&type=${type}&page=${page}&length=${length}&search=${encodeURIComponent(search)}`);
      const json = await r.json();
      if (json.html) { tbody.innerHTML = json.html; }
      const pag = tbl.closest('.bg-white')?.querySelector('.pagination');
      if (pag) pag.innerHTML = json.pagination || '';
    } catch (e) { tbody.innerHTML = '<tr><td colspan="10" class="text-red-500 p-4 text-center">Error loading data</td></tr>'; }
    simHideLoading();
  }

  async function simEditRecord(type, id) {
    simShowLoading();
    const r = await fetch(SIM_API + `?action=get&type=${type}&id=${id}`);
    const json = await r.json();
    simHideLoading();
    if (json.ok) openSimulasiModal(json.html);
    else alert(json.error || 'Gagal');
  }

  async function simAddRecord(type) {
    simShowLoading();
    const r = await fetch(SIM_API + `?action=get&type=${type}&id=0`);
    const json = await r.json();
    simHideLoading();
    if (json.ok) openSimulasiModal(json.html);
    else alert(json.error || 'Gagal');
  }

  async function simDeleteRecord(type, id) {
    if (!confirm('Hapus data ini?')) return;
    const r = await fetch(SIM_API + `?action=delete&type=${type}&id=${id}`);
    const json = await r.json();
    if (json.ok) simLoadTable(type);
    else alert(json.error || 'Gagal');
  }

  function updateResikoEmergensi(sel) {
    const container = document.getElementById('pegawaiTtData');
    if (!container) return;
    let ttData, rmData, emData;
    try {
      ttData = JSON.parse(container.dataset.tt);
      rmData = JSON.parse(container.dataset.rm);
      emData = JSON.parse(container.dataset.em);
    } catch (e) { return; }
    const id = sel.value;
    const ri = document.getElementById('resikoDisplay');
    const ei = document.getElementById('emergensiDisplay');
    if (id && ttData[id]) {
      const d = ttData[id];
      ri.value = (d.resiko || '-') + ' / ' + (rmData[d.resiko] || '');
      ei.value = (d.emergensi || '-') + ' / ' + (emData[d.emergensi] || '');
    } else {
      ri.value = '';
      ei.value = '';
    }
  }

  function hitungNilai() {
    const total = parseFloat(document.querySelector('[name="total_manajemen"]')?.value) || 0;
    const keys = ['direktur', 'pelayanan', 'program_keuangan', 'penunjang_sarpras', 'tata_usaha'];
    let sumNilai = 0,
      sumPersen = 0;
    keys.forEach(function (k) {
      const inp = document.querySelector('[name="' + k + '"]');
      if (!inp) return;
      const p = parseFloat(inp.value) || 0;
      sumPersen += p;
      const n = total * p / 100;
      sumNilai += n;
      const el = document.getElementById('nilai_' + k);
      if (el) el.textContent = n.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    });
    const tp = document.getElementById('totalPersen');
    if (tp) tp.textContent = sumPersen.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
    const tn = document.getElementById('totalNilai');
    if (tn) tn.textContent = sumNilai.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function openBekai() {
    simShowLoading();
    fetch(SIM_API + '?action=bekai_get').then(r => r.json()).then(j => {
      if (j.ok) {
        openSimulasiModal(j.html);
        setTimeout(hitungNilai, 50);
      } else alert(j.error || 'Gagal');
      simHideLoading();
    }).catch(() => simHideLoading());
  }

  function bekaiSave(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    const btn = e.target.querySelector('[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    fetch(SIM_API + '?action=bekai_save', { method: 'POST', body: fd })
      .then(r => r.json()).then(j => {
        if (j.ok) closeSimulasiModal();
        else alert(j.error || 'Gagal');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-1"></i> Simpan';
      }).catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-save mr-1"></i> Simpan';
      });
  }

  let simEditModeId = 0;

  function openMasterModal(type) {
    simShowLoading();
    fetch(SIM_API + '?action=master_modal&type=' + type)
      .then(r => r.text()).then(html => {
        openSimulasiModal(html);
        simEditModeId = 0;
        simHideLoading();
      }).catch(() => simHideLoading());
  }

  function modalAddSubmit(e, type) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    const fd = new FormData(form);
    fd.set('type', type);
    if (simEditModeId) fd.set('id', simEditModeId);
    fetch(SIM_API + '?action=save', { method: 'POST', body: fd })
      .then(r => r.json()).then(json => {
        if (json.ok) {
          form.reset();
          simEditModeId = 0;
          btn.innerHTML = '<i class="fas fa-plus"></i> Tambah';
          refreshMasterTable(type);
        } else { alert(json.error || 'Gagal'); }
        btn.disabled = false;
      }).catch(() => { btn.disabled = false; });
  }

  function modalEditRow(type, id) {
    const row = document.getElementById('mrow_' + id);
    if (!row) return;
    const form = document.querySelector('#pegawaiTtData + .grid form, #modalMasterForm') || document.querySelector('#simulasiModalBody form[data-table]');
    if (!form) return;
    const inputs = form.querySelectorAll('input');
    const cells = row.querySelectorAll('td:not(:last-child)');
    cells.forEach((td, i) => {
      if (inputs[i]) inputs[i].value = td.textContent.trim();
    });
    simEditModeId = id;
    const btn = form.querySelector('[type="submit"]');
    btn.innerHTML = '<i class="fas fa-save"></i> Simpan';
    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function modalDeleteRow(type, id) {
    if (!confirm('Hapus data ini?')) return;
    fetch(SIM_API + '?action=delete&type=' + type + '&id=' + id)
      .then(r => r.json()).then(json => {
        if (json.ok) {
          const row = document.getElementById('mrow_' + id);
          if (row) row.remove();
          const tbody = document.getElementById('modalMasterTbody');
          if (tbody && !tbody.querySelector('tr:not(.empty-msg)')) {
            tbody.innerHTML = '<tr><td colspan="99" class="text-center text-gray-400 py-6 text-sm">Belum ada data</td></tr>';
          }
        } else { alert(json.error || 'Gagal'); }
      });
  }

  function refreshMasterTable(type) {
    fetch(SIM_API + '?action=master_modal&type=' + type)
      .then(r => r.text()).then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newTbody = doc.getElementById('modalMasterTbody');
        const oldTbody = document.getElementById('modalMasterTbody');
        if (newTbody && oldTbody) oldTbody.innerHTML = newTbody.innerHTML;
      });
  }

  function initModalFormula() {
    const mfi = document.getElementById('modalFormulaInput');
    const mfd = document.getElementById('modalFormulaDisplay');
    if (!mfi || !mfd) return;
    let mtokens = [];

    function parseTokens(str) {
      const r = [];
      let b = '';
      for (const c of str) {
        if ('+-*/()%'.includes(c)) {
          if (b) {
            r.push(b);
            b = '';
          } r.push(c);
        } else { b += c; }
      }
      if (b) r.push(b);
      return r;
    }
    mtokens = parseTokens(mfi.value);
    window.modalFormulaTokens = mtokens;
    window.modalFormulaInput = mfi;
    window.modalFormulaDisplay = mfd;

    function render() {
      const h = mtokens.map(t => {
        if ('+-*/()%'.includes(t)) {
          if (t === '*') return '<span class="text-orange-600 font-bold mx-0.5">&times;</span>';
          if (t === '/') return '<span class="text-orange-600 font-bold mx-0.5">&divide;</span>';
          if (t === '%') return '<span class="text-orange-600 font-bold mx-0.5">%</span>';
          return '<span class="text-orange-600 font-bold mx-0.5">' + t + '</span>';
        }
        return '<span class="text-primary-700 font-semibold mx-0.5">' + t + '</span>';
      }).join('');
      mfd.innerHTML = h || '<span class="text-gray-300 italic">Tekan tombol di bawah...</span>';
      mfi.value = mtokens.join('');
    }
    window.modalFormulaRender = render;
    window.modalAddVar = function (v) {
      mtokens.push(v);
      render();
    };
    window.modalAddOp = function (o) {
      mtokens.push(o);
      render();
    };
    window.modalBackspace = function () {
      mtokens.pop();
      render();
    };
    window.modalClearFormula = function () {
      mtokens = [];
      render();
    };
    render();
  }

  const simulaciCols = new Set();

  function toggleColsDropdown() {
    const m = document.getElementById('colsMenu');
    if (m) m.classList.toggle('hidden');
  }

  function toggleColumn(key) {
    if (simulaciCols.has(key)) simulaciCols.delete(key);
    else simulaciCols.add(key);
    simLoadTable('simulasi');
  }

  async function submitModalForm(e) {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('[type="submit"]');
    const origText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    const fd = new FormData(form);
    try {
      const actionUrl = (window.BASE_URL || '') + '/' + form.getAttribute('action');
      const r = await fetch(actionUrl, { method: 'POST', body: fd });
      const json = await r.json();
      if (json.ok) {
        closeSimulasiModal();
        const t = form.dataset.refresh || (simCurrentPage === 'simulasi' ? 'simulasi' : form.querySelector('[name="type"]')?.value);
        if (t) simLoadTable(t);
      } else alert(json.error || 'Gagal');
    } catch (e) { alert('Gagal'); }
    btn.disabled = false;
    btn.innerHTML = origText;
  }

  let simSearchTimer;
  document.addEventListener('input', function (e) {
    if (e.target.matches('#simulasi-app [data-search]')) {
      clearTimeout(simSearchTimer);
      const type = e.target.dataset.search;
      simSearchTimer = setTimeout(() => simLoadTable(type, 1, e.target.value), 300);
    }
  });

  document.addEventListener('click', function (e) {
    const dd = document.getElementById('colsDropdown');
    const menu = document.getElementById('colsMenu');
    if (dd && menu && !dd.contains(e.target)) menu.classList.add('hidden');
  });

  function printSimulasi() {
    const allCols = ['v_kr', 'v_p', 'v_rs', 'v_re', 'v_sk', 'v_jr', 'v_sf', 'v_bk', 'v_ik', 'v_ab'];
    const q = document.querySelector('#simulasi-app [data-search="simulasi"]')?.value || '';
    fetch(SIM_API + '?action=list&type=simulasi&page=1&length=99999&search=' + encodeURIComponent(q) + '&cols=' + allCols.join(',') + '&hide_aksi=1')
      .then(r => r.json()).then(json => {
        const w = window.open('', '_blank');
        w.document.write('<html><head><title>SIMULASI REMUNERASI RSUD MERAUKE</title>');
        w.document.write('<style>@page{size:landscape;margin:15mm} table{width:100%;border-collapse:collapse;font-size:9px} th,td{border:1.5px solid #000;padding:4px 6px;text-align:center} th{background:#f0f0f0;font-weight:bold} .title{text-align:center;font-size:14px;font-weight:bold;margin-bottom:4px} .sub{text-align:center;font-size:10px;margin-bottom:16px}th:nth-child(2), td:nth-child(2) { text-align: left; }</style>');
        w.document.write('</head><body>');
        w.document.write('<div class="title">SIMULASI REMUNERASI RSUD MERAUKE</div>');
        w.document.write('<div class="sub">' + new Date().toLocaleDateString('id-ID') + '</div>');
        w.document.write('<table><thead>' + json.thead + '</thead><tbody>' + json.html + '</tbody></table>');
        w.document.write('</body></html>');
        w.document.close();
        w.print();
      });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSimulasiModal();
  });

  simLoadPage('simulasi');
</script>

<?php require_once '../layouts/footer.php'; ?>