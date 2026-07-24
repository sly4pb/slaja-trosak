#!/usr/bin/env python3
"""
AIK banka PDF parser.

Cita mesecni izvod (PDF) i vraca strukturirane transakcije kao JSON na stdout.

Usage:
    python3 aik_parser.py /path/to/statement.pdf

Output (stdout):
    {"success": true, "transactions": [...]}
    ili
    {"success": false, "error": "poruka greske"}
"""

import sys
import json
import re

try:
    import pdfplumber
except ImportError:
    print(json.dumps({
        "success": False,
        "error": "pdfplumber nije instaliran u Python okruzenju"
    }))
    sys.exit(1)

# X-koordinate kolona u PDF-u (u "points"), sa tolerancijom
COL_CREDIT_MIN = 300  # Potrazuje (uplata) - leva kolona iznosa
COL_CREDIT_MAX = 400
COL_DEBIT_MIN = 400   # Duguje (isplata) - desna kolona iznosa
COL_DEBIT_MAX = 500

ROW_TOLERANCE = 2  # piksela, za grupisanje reci u isti red


def group_words_into_rows(words):
    """Grupise reci u redove na osnovu Y (top) koordinate."""
    rows = {}
    for w in words:
        top = round(w['top'])
        found_key = None
        for existing_top in rows:
            if abs(existing_top - top) <= ROW_TOLERANCE:
                found_key = existing_top
                break
        key = found_key if found_key is not None else top
        rows.setdefault(key, []).append(w)
    return rows


def find_transaction_start_rows(rows, sorted_tops):
    """Pronalazi redove koji oznacavaju pocetak nove transakcije (npr 'N.' na levoj margini)."""
    starts = []
    for top in sorted_tops:
        row = sorted(rows[top], key=lambda w: w['x0'])
        first = row[0]
        if re.match(r'^\d{1,3}\.$', first['text']) and first['x0'] < 30:
            starts.append(top)
    return starts


def extract_date(main_row_text):
    """Trazi datum formata dd.mm.yy i vraca ga kao Y-m-d (pretpostavlja 2000+ godinu)."""
    match = re.search(r'(\d{2})\.(\d{2})\.(\d{2})', main_row_text)
    if not match:
        return None
    day, month, year = match.groups()
    return f"20{year}-{month}-{day}"


def extract_type(main_row_text):
    if 'Kartica' in main_row_text:
        return 'Kartično plaćanje'
    if 'Nalog' in main_row_text:
        return 'Transfer'
    return 'Ostalo'


def extract_amount(main_row):
    """
    Vraca (amount, currency). Duguje (desno) = rashod (negativno).
    Potrazuje (levo) = prihod (pozitivno).
    """
    for w in main_row:
        if not re.match(r'^[\d,]+\.\d{2}$', w['text']):
            continue
        value = float(w['text'].replace(',', ''))
        x = w['x0']
        if COL_DEBIT_MIN <= x < COL_DEBIT_MAX:
            return -value
        if COL_CREDIT_MIN <= x < COL_CREDIT_MAX:
            return value
    return None


def extract_description(rows, block_tops):
    """Prvi opisni red posle glavnog reda, preskace 'Poziv', 'Datum', 'Iznos' redove."""
    for top in block_tops[1:]:
        row = sorted(rows[top], key=lambda w: w['x0'])
        text = ' '.join(w['text'] for w in row).strip()
        if text.startswith('Poziv') or text.startswith('Datum') or text.startswith('Iznos'):
            continue
        if text:
            return text
    return None


def parse_aik_pdf(filepath):
    transactions = []

    with pdfplumber.open(filepath) as pdf:
        for page in pdf.pages:
            words = page.extract_words(use_text_flow=False, keep_blank_chars=False)
            if not words:
                continue

            rows = group_words_into_rows(words)
            sorted_tops = sorted(rows.keys())
            tx_starts = find_transaction_start_rows(rows, sorted_tops)

            for i, start in enumerate(tx_starts):
                end = tx_starts[i + 1] if i + 1 < len(tx_starts) else max(sorted_tops) + 1
                block_tops = [t for t in sorted_tops if start <= t < end]

                main_row = sorted(rows[block_tops[0]], key=lambda w: w['x0'])
                main_text = ' '.join(w['text'] for w in main_row)

                date = extract_date(main_text)
                if date is None:
                    continue

                amount = extract_amount(main_row)
                if amount is None:
                    continue

                tx_type = extract_type(main_text)
                description = extract_description(rows, block_tops)

                transactions.append({
                    'date': date,
                    'type': tx_type,
                    'description': description,
                    'amount': amount,
                    'currency': 'RSD',
                    'raw': main_text,
                })

    return transactions


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "Nije prosledjena putanja do PDF fajla"}))
        sys.exit(1)

    filepath = sys.argv[1]

    try:
        transactions = parse_aik_pdf(filepath)
        print(json.dumps({"success": True, "transactions": transactions}, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == '__main__':
    main()
