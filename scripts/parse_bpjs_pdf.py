#!/usr/bin/env python3
import json, re, sys, os
from concurrent.futures import ThreadPoolExecutor, as_completed
from pdf2image import convert_from_path, pdfinfo_from_path
import pytesseract

def parse_currency(s):
    s = s.strip().replace(',', '').replace('.', '')
    try: return int(s)
    except: return 0

def clean_sep(s):
    s = s.strip().replace('|', '')
    s = s.lstrip("'\"`‘’“”—–-~|")
    s = re.sub(r'[^0-9A-Z]', '', s.upper())
    return s

def parse_page_text(text):
    rows = []
    for line in text.split('\n'):
        line = line.strip()
        if not line:
            continue
        parts = re.split(r'\s+', line)
        if len(parts) < 5:
            continue
        if not parts[0].replace('|', '').isdigit():
            continue
        for i, p in enumerate(parts):
            if re.match(r'^\d{4}-\d{2}-\d{2}$', p):
                raw_sep = ' '.join(parts[1:i])
                sep_no = clean_sep(raw_sep)
                nums = parts[i+1:]
                riil = parse_currency(nums[0]) if len(nums) > 0 else 0
                diajukan = parse_currency(nums[1]) if len(nums) > 1 else 0
                disetujui = parse_currency(nums[2]) if len(nums) > 2 else diajukan
                if sep_no and len(sep_no) > 10:
                    rows.append({
                        'no_sep': sep_no,
                        'tgl_verifikasi': p,
                        'riil_rs': riil,
                        'diajukan': diajukan,
                        'disetujui': disetujui
                    })
                break
    return rows

def ocr_image(img):
    text = pytesseract.image_to_string(img, lang='eng')
    return parse_page_text(text)

def parse_pdf(pdf_path, dpi=150, workers=4, batch_size=15):
    if not os.path.exists(pdf_path):
        return {'error': 'File not found'}
    info = pdfinfo_from_path(pdf_path)
    total_pages = info['Pages']
    all_rows = []
    seen_seps = set()
    for start in range(1, total_pages + 1, batch_size):
        end = min(start + batch_size - 1, total_pages)
        images = convert_from_path(pdf_path, dpi=dpi, first_page=start, last_page=end)
        with ThreadPoolExecutor(max_workers=workers) as executor:
            futures = [executor.submit(ocr_image, img) for img in images]
            for f in as_completed(futures):
                page_rows = f.result()
                for r in page_rows:
                    if r['no_sep'] not in seen_seps:
                        seen_seps.add(r['no_sep'])
                        all_rows.append(r)
    all_rows.sort(key=lambda x: x['no_sep'])
    return {'rows': all_rows, 'total': len(all_rows)}

if __name__ == '__main__':
    if len(sys.argv) < 2:
        print(json.dumps({'error': 'Usage: parse_bpjs_pdf.py <pdf_path>'}))
        sys.exit(1)
    result = parse_pdf(sys.argv[1])
    print(json.dumps(result))
