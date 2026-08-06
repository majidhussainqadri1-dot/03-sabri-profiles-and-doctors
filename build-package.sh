#!/usr/bin/env bash
set -euo pipefail
OUT="${1:-dist}"
ROOT="$(cd "$(dirname "$0")" && pwd)"
VERSION="$(sed -n 's/^ \* Version: \(.*\)$/\1/p' "$ROOT/sabri-profiles-doctors.php" | head -1)"
TOP='03-sabri-profiles-and-doctors'
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/$TOP/includes" "$WORK/$TOP/assets/css" "$WORK/$TOP/assets/js"
cp "$ROOT/sabri-profiles-doctors.php" "$ROOT/uninstall.php" "$ROOT/readme.txt" "$WORK/$TOP/"
cp "$ROOT"/includes/*.php "$WORK/$TOP/includes/"
cp "$ROOT"/assets/css/* "$WORK/$TOP/assets/css/"
cp "$ROOT"/assets/js/* "$WORK/$TOP/assets/js/"
find "$WORK/$TOP" -type f -exec touch -t 202608060000 {} +
ZIP="$OUT/$TOP-$VERSION.zip"
(
  cd "$WORK"
  find "$TOP" -type f -print | LC_ALL=C sort | zip -X -q "$ZIP.tmp" -@
)
mv "$ZIP.tmp" "$ZIP"
(
  cd "$OUT"
  sha256sum "$(basename "$ZIP")" > "$(basename "$ZIP").sha256"
)
python3 - "$WORK/$TOP" "$OUT/SBOM.json" "$VERSION" <<'PY'
import hashlib,json,pathlib,sys
root=pathlib.Path(sys.argv[1]); out=pathlib.Path(sys.argv[2]); version=sys.argv[3]
files=[]
for p in sorted(x for x in root.rglob('*') if x.is_file()):
    b=p.read_bytes(); files.append({'path':p.relative_to(root).as_posix(),'size':len(b),'sha256':hashlib.sha256(b).hexdigest()})
out.write_text(json.dumps({'name':'03-sabri-profiles-and-doctors','version':version,'format':'SPDX-lite','files':files},indent=2,ensure_ascii=False)+'\n',encoding='utf-8')
PY
printf '%s\n' "$ZIP"
