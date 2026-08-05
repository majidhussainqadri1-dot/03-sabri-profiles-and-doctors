#!/usr/bin/env bash
set -euo pipefail
OUT="${1:-dist}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VERSION="$(sed -n 's/^ \* Version: \(.*\)$/\1/p' "$ROOT/sabri-profiles-doctors.php" | head -1)"
[[ -n "$VERSION" ]] || { echo "Plugin version missing" >&2; exit 1; }
PKG="03-sabri-profiles-and-doctors"
WORK="$OUT/work"
rm -rf "$OUT"
mkdir -p "$WORK/$PKG" "$OUT"
OUT="$(cd "$OUT" && pwd)"
WORK="$OUT/work"
for path in sabri-profiles-doctors.php includes assets uninstall.php readme.txt; do
  cp -a "$ROOT/$path" "$WORK/$PKG/"
done
# Normalize metadata for a byte-reproducible archive.
find "$WORK/$PKG" -type d -exec chmod 0755 {} +
find "$WORK/$PKG" -type f -exec chmod 0644 {} +
find "$WORK/$PKG" -exec touch -h -t 202608060000.00 {} +
(
  cd "$WORK"
  find "$PKG" -type f -print | LC_ALL=C sort | zip -X -q "$OUT/.package.tmp.zip" -@
)
mv "$OUT/.package.tmp.zip" "$OUT/$PKG-$VERSION.zip"
(
  cd "$OUT"
  sha256sum "$PKG-$VERSION.zip" > "$PKG-$VERSION.zip.sha256"
)
python3 - "$ROOT" "$OUT/SBOM.json" "$VERSION" <<'PY'
import hashlib,json,pathlib,sys
root=pathlib.Path(sys.argv[1]); out=pathlib.Path(sys.argv[2]); version=sys.argv[3]
files=[]
for base in ['sabri-profiles-doctors.php','uninstall.php','readme.txt']:
    p=root/base; files.append(p)
for folder in ['includes','assets']:
    files += sorted((root/folder).rglob('*'))
rows=[]
for p in files:
    if not p.is_file(): continue
    rows.append({'path':p.relative_to(root).as_posix(),'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'bytes':p.stat().st_size})
out.write_text(json.dumps({'format':'Sabri-SBOM-1','component':'File 03 — Profiles and Doctors','version':version,'files':rows},indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
PY
rm -rf "$WORK"
printf '%s\n' "$OUT/$PKG-$VERSION.zip"
