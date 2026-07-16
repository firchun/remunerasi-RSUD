import os
import re

files = [
    '/Applications/XAMPP/xamppfiles/htdocs/remon/api/export_hitung_jasa_ralan_umum.php',
    '/Applications/XAMPP/xamppfiles/htdocs/remon/api/export_hitung_jasa_dokter_ralan_umum.php',
    '/Applications/XAMPP/xamppfiles/htdocs/remon/api/export_hitung_jasa_ranap_umum.php',
    '/Applications/XAMPP/xamppfiles/htdocs/remon/api/export_hitung_jasa_dokter_ranap_umum.php'
]

for f in files:
    with open(f, 'r') as file:
        content = file.read()
    
    # Remove `$rekap[...] += ;` lines
    content = re.sub(r'\$rekap\[.*?\]\[\d+\]\s*\+=\s*;\n?', '', content)
    
    # In some places, `$sisa_bpjs` is left
    content = re.sub(r'\$rekap\[.*?\]\[\d+\]\s*\+=\s*\$sisa_bpjs;\n?', '', content)
    
    # Also if there are any remaining `$sisa_bpjs = - ;` 
    content = re.sub(r'\$sisa_bpjs\s*=\s*-\s*;\n?', '', content)

    with open(f, 'w') as file:
        file.write(content)
