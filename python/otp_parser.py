#!/usr/bin/env python3
"""
OTP banka Srbija PDF parser.

Čita mesečni izvod (PDF) i vraća strukturirane transakcije kao JSON na stdout.

Usage:
    python3 otp_parser.py /path/to/statement.pdf

Output (stdout):
    {"success": true, "transactions": [...]}
    ili
    {"success": false, "error": "poruka greske"}

Format izvoda:
    Svaka transakcija se prostire kroz 3 reda:
      Red 1: <redni_br>  <opis transakcije>  <debit iznos>  <credit iznos>
      Red 2: <datum transakcije>   (npr. 02.06.2026, x0 ~43)
      Red 3: <datum valute>        (npr. 01.06.2026, x0 ~43)

    Debit (isplate) nalazi se u koloni x ~450-510
    Credit (uplate) nalazi se u koloni x ~520-570
    Iznosi su formata "1,234.56" (zarez = separator hiljaditih, tačka = decimalni)
    Vrijednost "0.00" znači da ta kolona nije aktivna za datu transakciju.
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

ROW_TOLERANCE    = 3   # y-tolerancija za grupisanje reči u isti red (pikseli)

# X-koordinate kolona iznosa
COL_DEBIT_MIN  = 445   # Isplate (rashod)
COL_DEBIT_MAX  = 515
COL_CREDIT_MIN = 515   # Uplate (prihod)
COL_CREDIT_MAX = 575

# Redni broj transakcije mora biti na levoj margini
COL_ROWNUM_MAX = 75

# Datum je uvek na levoj margini
COL_DATE_MAX   = 55


def group_words_into_rows(words):
    """Grupise reči u redove na osnovu Y (top) koordinate."""
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


def parse_amount(text):
    """
    Parsira iznos formata "1,234.56" → float.
    Vraća None ako nije validan iznos.
    """
    # OTP format: "1,234.56" – zarez = separator hiljaditih, tačka = decimalni
    clean = re.sub(r'[^0-9.]', '', text.replace(',', ''))
    try:
        return float(clean) if clean else None
    except ValueError:
        return None


def is_amount(text):
    """Provjeri da li string izgleda kao iznos (npr. '1,234.56' ili '0.00')."""
    return bool(re.match(r'^\d{1,3}(,\d{3})*\.\d{2}$', text))


def is_date(text):
    """Provjeri da li string izgleda kao datum dd.mm.yyyy."""
    return bool(re.match(r'^\d{2}\.\d{2}\.\d{4}$', text))


def parse_date(text):
    """Parsira datum 'dd.mm.yyyy' → 'yyyy-mm-dd'."""
    m = re.match(r'^(\d{2})\.(\d{2})\.(\d{4})$', text)
    if not m:
        return None
    day, month, year = m.groups()
    return f"{year}-{month}-{day}"


def find_transaction_rows(rows, sorted_tops):
    """
    Vraća listu top-pozicija redova koji označavaju početak transakcije.
    Transakcija počinje redom koji ima redni broj (samo cifre) na x < COL_ROWNUM_MAX.
    """
    starts = []
    for top in sorted_tops:
        row = sorted(rows[top], key=lambda w: w['x0'])
        if not row:
            continue
        first = row[0]
        if re.match(r'^\d+$', first['text']) and first['x0'] < COL_ROWNUM_MAX:
            starts.append(top)
    return starts


def extract_transaction(rows, block_tops):
    """
    Iz bloka od 3 reda izvlači transakciju.
    Vraća dict sa ključevima: date, type, description, amount, currency, raw
    ili None ako parsiranje ne uspe.
    """
    if not block_tops:
        return None

    # --- Red 1: opis + iznosi ---
    main_row = sorted(rows[block_tops[0]], key=lambda w: w['x0'])
    main_text = ' '.join(w['text'] for w in main_row)

    debit  = None
    credit = None
    desc_words = []

    for w in main_row:
        x = w['x0']
        t = w['text']

        if not is_amount(t):
            # Preskoci redni broj (samo cifre, leva margina)
            if re.match(r'^\d+$', t) and x < COL_ROWNUM_MAX:
                continue
            desc_words.append(t)
        else:
            val = parse_amount(t)
            if val is None:
                continue
            if COL_DEBIT_MIN <= x < COL_DEBIT_MAX:
                debit = val
            elif COL_CREDIT_MIN <= x < COL_CREDIT_MAX:
                credit = val

    description = ' '.join(desc_words).strip()

    # Ako su oba 0.00 (npr. kamata) – preskočimo
    if (debit is None or debit == 0.0) and (credit is None or credit == 0.0):
        return None

    # Izračunaj iznos: debit (isplata) = negativan, credit (uplata) = pozitivan
    if credit and credit > 0.0:
        amount = credit
    elif debit and debit > 0.0:
        amount = -debit
    else:
        return None

    # --- Redovi 2 i 3: datumi ---
    date = None
    for top in block_tops[1:]:
        date_row = sorted(rows[top], key=lambda w: w['x0'])
        for w in date_row:
            if w['x0'] < COL_DATE_MAX and is_date(w['text']):
                date = parse_date(w['text'])
                break
        if date:
            break

    if date is None:
        return None

    # Tip transakcije - heuristika
    tx_type = extract_type(description)

    return {
        'date':        date,
        'type':        tx_type,
        'description': description,
        'amount':      amount,
        'currency':    'RSD',
        'raw':         f"{date}|{amount:.2f}|{description}",
    }


def extract_type(description):
    desc_lower = description.lower()
    # ATM mora biti pre kartice jer opis moze imati i 'MasterCard' i 'ATM'
    if 'atm' in desc_lower:
        return 'Podizanje gotovine'
    if 'mastercard' in desc_lower or 'dina classic' in desc_lower or 'visa' in desc_lower:
        return 'Plaćanje karticom'
    if 'isplata čeka' in desc_lower or 'kliring' in desc_lower:
        return 'Isplata čeka'
    if 'napl.mes.nakn' in desc_lower or 'provizija' in desc_lower or 'naknada' in desc_lower:
        return 'Naknada'
    if 'kamata' in desc_lower:
        return 'Kamata'
    if 'knjiženje' in desc_lower or 'príliv' in desc_lower or 'uplata' in desc_lower:
        return 'Uplata'
    if 'ips' in desc_lower:
        return 'IPS plaćanje'
    return 'Ostalo'


def is_header_row(rows, top):
    """Provjeri da li je red zaglavlje tabele (sadrži ključne reči)."""
    row = sorted(rows[top], key=lambda w: w['x0'])
    text = ' '.join(w['text'] for w in row)
    header_keywords = ['Redni', 'Datum', 'Opis', 'Isplate', 'Uplate', 'Description', 'Debit', 'Credit']
    return any(kw in text for kw in header_keywords)


def parse_otp_pdf(filepath):
    transactions = []

    with pdfplumber.open(filepath) as pdf:
        for page in pdf.pages:
            words = page.extract_words(use_text_flow=False, keep_blank_chars=False)
            if not words:
                continue

            rows = group_words_into_rows(words)
            sorted_tops = sorted(rows.keys())
            tx_starts = find_transaction_rows(rows, sorted_tops)

            for i, start in enumerate(tx_starts):
                # Blok se prostire do sledećeg rednog broja (ili kraja stranice)
                end = tx_starts[i + 1] if i + 1 < len(tx_starts) else max(sorted_tops) + 1
                block_tops = [t for t in sorted_tops if start <= t < end]

                tx = extract_transaction(rows, block_tops)
                if tx:
                    transactions.append(tx)

    return transactions


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "Nije prosledjena putanja do PDF fajla"}))
        sys.exit(1)

    filepath = sys.argv[1]

    try:
        transactions = parse_otp_pdf(filepath)
        print(json.dumps({"success": True, "transactions": transactions}, ensure_ascii=False))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == '__main__':
    main()

