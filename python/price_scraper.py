#!/usr/bin/env python3
"""
Universal product price scraper.

Extraction strategy (in order of reliability):
  1. JSON-LD structured data (schema.org/Product -> offers.price)
     Most e-commerce sites embed this for Google Shopping / SEO purposes.
     It's already computed (final price after discounts) and language-independent.
  2. Open Graph meta tags (product:price:amount / og:price:amount)
     Common on sites optimized for social media sharing.
  3. Regex fallback on visible page text (Serbian RSD format), with
     heuristics to exclude savings/delivery/installment labels.
     Least reliable — used only when structured data is absent.

Usage:
    python3 price_scraper.py "<url>"

Output (stdout):
    {"success": true, "product_name": "...", "price": 1234.56, "currency": "RSD", "source": "json-ld"}
    or
    {"success": false, "error": "..."}
"""

import sys
import json
import re

try:
    import requests
    from bs4 import BeautifulSoup
except ImportError as e:
    print(json.dumps({"success": False, "error": f"Missing Python package: {e}"}))
    sys.exit(1)

HEADERS = {
    'User-Agent': (
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 '
        '(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
    ),
    'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
    'Accept-Language': 'sr-RS,sr;q=0.9,en;q=0.8',
    'Connection': 'keep-alive',
}

REQUEST_TIMEOUT = 20
MIN_REASONABLE_PRICE = 50
MAX_REASONABLE_PRICE = 10_000_000


# ─── Strategy 1: JSON-LD structured data ────────────────────────────────

def find_product_nodes(data) -> list[dict]:
    """Recursively search parsed JSON-LD data for nodes with @type == 'Product'."""
    found = []

    if isinstance(data, dict):
        if data.get('@type') == 'Product':
            found.append(data)
        # Product can be nested inside @graph or other wrapper structures
        for value in data.values():
            found.extend(find_product_nodes(value))
    elif isinstance(data, list):
        for item in data:
            found.extend(find_product_nodes(item))

    return found


def extract_price_from_json_ld(soup: BeautifulSoup) -> dict | None:
    """
    Look for schema.org Product structured data and extract offers.price.
    Handles offers as a single dict or a list of offers (takes the lowest price).
    """
    scripts = soup.find_all('script', type='application/ld+json')

    for script in scripts:
        if not script.string:
            continue

        try:
            data = json.loads(script.string)
        except (json.JSONDecodeError, TypeError):
            continue

        products = find_product_nodes(data)

        for product in products:
            offers = product.get('offers')
            if not offers:
                continue

            offer_list = offers if isinstance(offers, list) else [offers]
            prices = []

            for offer in offer_list:
                if not isinstance(offer, dict):
                    continue
                price_raw = offer.get('price') or offer.get('lowPrice')
                if price_raw is None:
                    continue
                try:
                    prices.append(float(str(price_raw).replace(',', '.')))
                except ValueError:
                    continue

            if prices:
                currency = offer_list[0].get('priceCurrency', 'RSD')
                return {
                    'price':    min(prices),
                    'currency': currency,
                    'name':     product.get('name'),
                }

    return None


# ─── Strategy 2: Open Graph / meta tag price data ───────────────────────

def extract_price_from_meta_tags(soup: BeautifulSoup) -> dict | None:
    """Look for og:price:amount / product:price:amount meta tags."""
    price_meta = (
        soup.find('meta', property='product:price:amount')
        or soup.find('meta', property='og:price:amount')
        or soup.find('meta', attrs={'name': 'price'})
    )

    if not price_meta or not price_meta.get('content'):
        return None

    try:
        price = float(price_meta['content'].replace(',', '.'))
    except ValueError:
        return None

    currency_meta = (
        soup.find('meta', property='product:price:currency')
        or soup.find('meta', property='og:price:currency')
    )
    currency = currency_meta['content'] if currency_meta and currency_meta.get('content') else 'RSD'

    return {'price': price, 'currency': currency, 'name': None}


# ─── Strategy 3: Regex fallback on visible text ─────────────────────────

PRICE_PATTERN = re.compile(
    r'(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*(?:RSD|din\.?|дин)',
    re.IGNORECASE
)
EXCLUDE_BEFORE_WORDS = [
    'isporuk', 'dostav', 'shipping', 'delivery', 'poštarin', 'postarin',
]
EXCLUDE_AFTER_WORDS = [
    'rata', 'rate', 'installment', 'mesečno', 'mesecno', 'monthly',
]
SAVINGS_LABEL_BEFORE_PATTERN = re.compile(
    r'(?:ušteda|usteda|savings|saved|you save)\s*:?\s*'
    r'(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*(?:RSD|din\.?|дин)',
    re.IGNORECASE
)
SAVINGS_LABEL_AFTER_PATTERN = re.compile(
    r'(\d{1,3}(?:\.\d{3})*(?:,\d{2})?)\s*(?:RSD|din\.?|дин)\s*'
    r'(?:ušteda|usteda|savings|saved)\b(?!\s*:)',
    re.IGNORECASE
)
CONTEXT_WINDOW = 30


def extract_price_from_text(soup: BeautifulSoup) -> dict | None:
    """
    Fallback: scan visible page text for RSD-formatted numbers, excluding
    known non-price contexts (savings deltas, delivery costs, installments).
    Least reliable — only used when structured data is unavailable.
    """
    text = soup.get_text(' ', strip=True)

    savings_amounts = set()
    for pattern in (SAVINGS_LABEL_BEFORE_PATTERN, SAVINGS_LABEL_AFTER_PATTERN):
        for m in pattern.finditer(text):
            cleaned = m.group(1).replace('.', '').replace(',', '.')
            try:
                savings_amounts.add(round(float(cleaned), 2))
            except ValueError:
                pass

    prices = []
    for match in PRICE_PATTERN.finditer(text):
        raw = match.group(1)

        before_context = text[max(0, match.start() - CONTEXT_WINDOW):match.start()].lower()
        after_context  = text[match.end():match.end() + CONTEXT_WINDOW].lower()

        if any(word in before_context for word in EXCLUDE_BEFORE_WORDS):
            continue
        if any(word in after_context for word in EXCLUDE_AFTER_WORDS):
            continue

        cleaned = raw.replace('.', '').replace(',', '.')
        try:
            value = float(cleaned)
        except ValueError:
            continue

        if round(value, 2) in savings_amounts:
            continue

        if MIN_REASONABLE_PRICE <= value <= MAX_REASONABLE_PRICE:
            prices.append(value)

    if not prices:
        return None

    return {'price': sorted(set(prices))[0], 'currency': 'RSD', 'name': None}


# ─── Product name extraction (independent of price source) ─────────────

def extract_product_name(soup: BeautifulSoup) -> str | None:
    og_title = soup.find('meta', property='og:title')
    if og_title and og_title.get('content'):
        return og_title['content'].strip()

    if soup.title and soup.title.string:
        return soup.title.string.strip()

    h1 = soup.find('h1')
    if h1:
        return h1.get_text(strip=True)

    return None


# ─── Main orchestration ──────────────────────────────────────────────────

def scrape(url: str) -> dict:
    response = requests.get(url, headers=HEADERS, timeout=REQUEST_TIMEOUT)
    response.raise_for_status()

    soup = BeautifulSoup(response.text, 'html.parser')
    product_name = extract_product_name(soup)

    # Try each strategy in order of reliability
    result = extract_price_from_json_ld(soup)
    source = 'json-ld'

    if result is None:
        result = extract_price_from_meta_tags(soup)
        source = 'meta-tags'

    if result is None:
        result = extract_price_from_text(soup)
        source = 'text-regex'

    if result is None:
        return {
            "success": False,
            "error": "No price found via structured data or page text",
            "product_name": product_name,
        }

    return {
        "success":      True,
        "product_name": result.get('name') or product_name,
        "price":        result['price'],
        "currency":     result.get('currency', 'RSD'),
        "source":       source,
    }


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "No URL provided"}))
        sys.exit(1)

    url = sys.argv[1]

    try:
        result = scrape(url)
        print(json.dumps(result, ensure_ascii=False))
    except requests.exceptions.HTTPError as e:
        print(json.dumps({
            "success": False,
            "error": f"HTTP error: {e.response.status_code}"
        }, ensure_ascii=False))
        sys.exit(1)
    except requests.exceptions.RequestException as e:
        print(json.dumps({"success": False, "error": f"Request failed: {str(e)}"}, ensure_ascii=False))
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}, ensure_ascii=False))
        sys.exit(1)


if __name__ == '__main__':
    main()