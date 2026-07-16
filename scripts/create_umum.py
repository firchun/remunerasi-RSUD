import os
import re

base_dir = '/Applications/XAMPP/xamppfiles/htdocs/remon'

def process_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    original_content = content

    # 1. Replace API calls
    content = re.sub(r'(/api/(?:get_data_|export_|get_rekap_)hitung_jasa_[a-z_]+)\.php', r'\1_umum.php', content)

    # 2. View files modifications (index.php and detail.php)
    if 'views/hitung_jasa_' in filepath and filepath.endswith('.php'):
        # Remove table headers for BPJS, 44%, and percentages
        content = re.sub(r'<th[^>]*>Total BPJS</th>\s*', '', content)
        content = re.sub(r'<th[^>]*>44%</th>\s*', '', content)
        
        # safely remove % and Jml headers
        header_removals = ['%DPJP', 'Jml DPJP', '%Perawat', 'Jml Perawat', '%Farmasi', 'Jml Farmasi', '%Dr Lab', 'Jml Dr Lab', '%Analis Lab', 'Jml Analis Lab', '%Dr Rad', 'Jml Dr Rad', '%Radiografer', 'Jml Radiografer', '%Non Medis', 'Jml Non Medis', '%Jasa Medis', 'Jml Jasa Medis']
        for h in header_removals:
            content = re.sub(r'<th[^>]*>' + re.escape(h) + r'</th>\s*', '', content)
        
        # DataTables column definitions removal
        content = re.sub(r'\{\s*data:\s*\'total_bpjs\'[^}]*\},?\s*', '', content)
        content = re.sub(r'\{\s*data:\s*\'kolom_44\'[^}]*\},?\s*', '', content)
        content = re.sub(r'\{\s*data:\s*\'persen_[a-z_]+\'[^}]*\},?\s*', '', content)
        content = re.sub(r'\{\s*data:\s*\'jumlah_[a-z_]+\'[^}]*\},?\s*', '', content)

        # footerCallback removals
        content = re.sub(r'\$\(api\.column\(\d+\)\.footer\(\)\)\.html\([^\)]*?\(?st2\(\'[a-z_]+\'\)[^\)]*?\);\s*', '', content)
        content = re.sub(r'\$\(api\.column\(\d+\)\.footer\(\)\)\.html\([^\)]*?\(?sumData\(\'(?:total_bpjs|kolom_44|jumlah_[a-z_]+)\'\)[^\)]*?\);\s*', '', content)
        content = re.sub(r'\$\(api\.column\(\d+\)\.footer\(\)\)\.html\([^\)]*?\(?pct\([^\)]+\)[^\)]*?\);\s*', '', content)

        # For empty th elements in tfoot:
        # We will just replace exactly 18 empty ths if it's the main index.
        # It's better to just remove 2 + count(header_removals) matching the data arrays.
        for _ in range(20):
            content = re.sub(r'<th class="text-right px-2"></th>\s*', '', content, count=1)

        # Adjust the export filename
        content = re.sub(r'filename:\s*\'(hitung_jasa_[a-z_]+)\'', r"filename: '\1_umum'", content)

    # 3. API files modifications (get_data_ and export_)
    if 'api/' in filepath and filepath.endswith('_umum.php'):
        # Remove BPJS verification lookups block
        match = re.search(r'\$bpjs_lookup\s*=\s*\[\];(.*?)\$data\s*=\s*\[\];', content, flags=re.DOTALL)
        if match:
            content = content.replace(match.group(0), '$data = [];')
            
        match2 = re.search(r'\$bpjs_this_month\s*=\s*\[\];(.*?)\$sep_gagal_compare\s*=\s*\[\];', content, flags=re.DOTALL)
        if match2:
            content = content.replace(match2.group(0), '$sep_gagal_compare = [];\n')

        match3 = re.search(r'\$sep_gagal_compare\s*=\s*\[\];(.*?)// ─── Rekap Per Poli', content, flags=re.DOTALL)
        if match3:
            content = content.replace(match3.group(0), '// ─── Rekap Per Poli')

        # In get_data or get_rekap: remove bpjs and kolom_44
        content = re.sub(r'\$row\[\'total_bpjs\'\]\s*=\s*[^;]+;\s*', '', content)
        content = re.sub(r'\$row\[\'kolom_44\'\]\s*=\s*[^;]+;\s*', '', content)
        content = re.sub(r'\$tb44\s*=\s*[^;]+;\s*', '', content)
        content = re.sub(r'\$total_bpjs\s*=\s*[^;]+;\s*', '', content)
        content = re.sub(r'\$kolom_44\s*=\s*[^;]+;\s*', '', content)
        content = re.sub(r'\$row\[\'persen_[a-z_]+\'\]\s*=\s*[^;]+;\s*', '', content)
        content = re.sub(r'\$row\[\'jumlah_[a-z_]+\'\]\s*=\s*[^;]+;\s*', '', content)

        # In export files: removing from headers array
        header_removals = ['%DPJP', 'Jml DPJP', '%Perawat', 'Jml Perawat', '%Farmasi', 'Jml Farmasi', '%Dr Lab', 'Jml Dr Lab', '%Analis Lab', 'Jml Analis Lab', '%Dr Rad', 'Jml Dr Rad', '%Radiografer', 'Jml Radiografer', '%Non Medis', 'Jml Non Medis', 'Total BPJS', '44%', '%Jasa Medis', 'Jml Jasa Medis']
        for h in header_removals:
            content = content.replace(f"'{h}',", "")
            content = content.replace(f"'{h}'", "")
            
        # In export files, the $finalRow appending
        content = re.sub(r'\$total_bpjs,?\s*', '', content)
        content = re.sub(r'\$kolom_44,?\s*', '', content)
        content = re.sub(r'\$pct\([^)]+\),?\s*', '', content)
        content = re.sub(r'\$jml_[a-z_]+,?\s*', '', content)

        # Remove sheet generation for BPJS specifics in export files
        content = re.sub(r'// ─── Selisih sheet ───.*?(\/\/ ───|\Z)', r'\1', content, flags=re.DOTALL)
        content = re.sub(r'// ─── Daftar Pasien Gagal Kompare ───.*?(\/\/ ───|\Z)', r'\1', content, flags=re.DOTALL)
        content = re.sub(r'// ─── Daftar SEP BPJS Gagal Kompare ───.*?(\/\/ ───|\Z)', r'\1', content, flags=re.DOTALL)
        content = re.sub(r'// ─── Fetch This Month\'s BPJS for Gagal Compare ───.*?(\/\/ ───|\Z)', r'\1', content, flags=re.DOTALL)
        
        # Cleanup any remaining array appends involving these
        content = re.sub(r'\$rekap\[\$poliKey\]\[[1-9]+\]\s*\+=.*?;', '', content)

    if content != original_content:
        with open(filepath, 'w') as f:
            f.write(content)
        print(f"Modified {filepath}")
    else:
        print(f"No changes for {filepath}")

# Process files
dirs = [
    'views/hitung_jasa_ralan_umum',
    'views/hitung_jasa_dokter_ralan_umum',
    'views/hitung_jasa_ranap_umum',
    'views/hitung_jasa_dokter_ranap_umum'
]

api_files = [
    'api/get_data_hitung_jasa_ralan_umum.php',
    'api/export_hitung_jasa_ralan_umum.php',
    'api/get_data_hitung_jasa_dokter_ralan_umum.php',
    'api/get_rekap_hitung_jasa_dokter_ralan_umum.php',
    'api/export_hitung_jasa_dokter_ralan_umum.php',
    'api/get_data_hitung_jasa_ranap_umum.php',
    'api/export_hitung_jasa_ranap_umum.php',
    'api/get_data_hitung_jasa_dokter_ranap_umum.php',
    'api/get_rekap_hitung_jasa_dokter_ranap_umum.php',
    'api/export_hitung_jasa_dokter_ranap_umum.php'
]

for d in dirs:
    for root, _, files in os.walk(os.path.join(base_dir, d)):
        for file in files:
            if file.endswith('.php'):
                process_file(os.path.join(root, file))

for api_file in api_files:
    process_file(os.path.join(base_dir, api_file))

print("Done.")
