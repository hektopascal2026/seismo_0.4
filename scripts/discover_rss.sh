#!/usr/bin/env bash
# Discover RSS/Atom feeds: seed as feed, HTML link[rel=alternate], common /feed paths.
# Usage: ./discover_rss.sh urls.txt

set -eu
# Do not use pipefail: greps often return 1 when a pattern is absent.

UA='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Seismo-RSS-Probe/1'

BODY="${TMPDIR:-/tmp}/drss_body_$$"
HTML="${TMPDIR:-/tmp}/drss_html_$$"
CANDS="${TMPDIR:-/tmp}/drss_cands_$$"
cleanup() { rm -f "$BODY" "$HTML" "$CANDS" 2>/dev/null || true; }
trap cleanup EXIT

[[ -f "${1:-}" ]] || { echo "Usage: $0 urls.txt" >&2; exit 1; }

origin_from_url() {
  python3 -c "import sys,urllib.parse; u=sys.argv[1].strip();
u=u if u.startswith(('http://','https://')) else 'https://'+u; p=urllib.parse.urlparse(u); print(p.scheme+'://'+p.netloc)" "$1" 2>/dev/null \
    || { local u="$1"; u="${u#http://}"; u="${u#https://}"; echo "https://${u%%/*}"; }
}

count_items() {
  local f="$1" n
  n=$(grep -c '<item' "$f" 2>/dev/null || true)
  [[ "${n:-0}" -gt 0 ]] 2>/dev/null && { echo "$n"; return; }
  n=$(grep -c '<entry' "$f" 2>/dev/null || true)
  echo "${n:-0}"
}

is_feed_file() {
  grep -qE '<rss[[:space:]]|<feed[[:space:]]|xmlns="http://www.w3.org/2005/Atom"' "$1" 2>/dev/null
}

kind_of() {
  grep -qE 'xmlns="http://www.w3.org/2005/Atom"|<feed[[:space:]]' "$1" 2>/dev/null && echo atom || echo rss
}

probe_url() {
  local url="$1" code
  code=$(curl -sS -L -A "$UA" --connect-timeout 12 -m 35 -o "$BODY" -w '%{http_code}' "$url" 2>/dev/null || echo 000)
  [[ "$code" == "200" ]] && [[ -s "$BODY" ]] || return 1
  is_feed_file "$BODY" || return 1
  printf '%s\t%s\t%s\t%s\n' "$url" "$code" "$(count_items "$BODY")" "$(kind_of "$BODY")"
}

extract_alternate_hrefs() {
  : > "$CANDS"
  # Avoid macOS tr "Illegal byte sequence" on odd HTML bytes
  LC_ALL=C tr '\n' ' ' < "$HTML" | sed 's/</\n</g' | grep -i '<link ' | while IFS= read -r line; do
    echo "$line" | grep -qiE 'application/rss\+xml|application/atom\+xml' || continue
    echo "$line" | grep -oiE 'href="[^"]+"' | sed 's/^href="//;s/"$//' >> "$CANDS" || true
    echo "$line" | grep -oiE "href='[^']+'" | sed "s/^href='//;s/'$//" >> "$CANDS" || true
  done
}

common_paths() {
  local o="$1"
  printf '%s\n' \
    "${o}/feed/" "${o}/feed" "${o}/rss.xml" "${o}/rss/" "${o}/rss" \
    "${o}/atom.xml" "${o}/feeds/all.atom.xml" "${o}/?feed=rss2" \
    "${o}/index.xml" "${o}/news/rss" \
    "${o}/de/feed/" "${o}/en/feed/" "${o}/fr/feed/" \
    "${o}/en/rss/news" "${o}/de/rss/news" "${o}/rss/news" \
    "${o}/en/news/rss" "${o}/news/rss.xml" \
    "${o}/api/rss" \
    "${o}/public/de/rss/news" "${o}/public/en/rss/news" \
    "${o}/commission/presscorner/api/rss"
}

echo -e "seed_url\tfeed_url\thttp_code\titems\tkind\tdiscovery"

while IFS= read -r line || [[ -n "$line" ]]; do
  line="${line#"${line%%[![:space:]]*}"}"
  line="${line%"${line##*[![:space:]]}"}"
  [[ -z "$line" || "$line" =~ ^# ]] && continue
  seed="$line"
  [[ "$seed" =~ ^https?:// ]] || seed="https://${seed}"

  hits=0
  origin=$(origin_from_url "$seed")

  if rest=$(probe_url "$seed"); then
    IFS=$'\t' read -r fu fc fn fk <<< "$rest"
    printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$seed" "$fu" "$fc" "$fn" "$fk" "seed_is_feed"
    hits=$((hits + 1))
  fi

  curl -sS -L -A "$UA" --connect-timeout 12 -m 35 -o "$HTML" "$seed" 2>/dev/null || true
  if [[ -s "$HTML" ]]; then
    extract_alternate_hrefs
    if [[ -s "$CANDS" ]]; then
      while IFS= read -r uu; do
        [[ -z "${uu:-}" ]] && continue
        [[ "$uu" =~ ^// ]] && uu="https:${uu}"
        [[ "$uu" =~ ^/ ]] && uu="${origin}${uu}"
        if rest=$(probe_url "$uu"); then
          IFS=$'\t' read -r fu fc fn fk <<< "$rest"
          printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$seed" "$fu" "$fc" "$fn" "$fk" "link_rel_alternate"
          hits=$((hits + 1))
        fi
      done < <(sort -u "$CANDS")
    fi
  fi

  while IFS= read -r purl; do
    [[ -z "$purl" ]] && continue
    if rest=$(probe_url "$purl"); then
      IFS=$'\t' read -r fu fc fn fk <<< "$rest"
      printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$seed" "$fu" "$fc" "$fn" "$fk" "common_path"
      hits=$((hits + 1))
    fi
  done < <(common_paths "$origin")

  [[ "$hits" -eq 0 ]] && printf '%s\t\t\t\t\tno_feed_found\n' "$seed"

  sleep 0.35
done < "$1"
