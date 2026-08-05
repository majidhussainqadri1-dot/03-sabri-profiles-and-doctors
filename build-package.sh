#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VERSION="$(sed -n "s/^ \* Version: \(.*\)$/\1/p" "$ROOT/sabri-profiles-doctors.php" | head -1)"
FOLDER="03-sabri-profiles-and-doctors"
OUT_DIR="${1:-$ROOT/build}"
STAGE="$OUT_DIR/$FOLDER"
ZIP="$OUT_DIR/$FOLDER-$VERSION.zip"
rm -rf "$OUT_DIR"
mkdir -p "$STAGE/includes" "$STAGE/assets/css" "$STAGE/assets/js"
cp "$ROOT/sabri-profiles-doctors.php" "$ROOT/uninstall.php" "$ROOT/readme.txt" "$STAGE/"
cp "$ROOT/includes/"*.php "$STAGE/includes/"
cp "$ROOT/assets/css/"*.css "$STAGE/assets/css/"
cp "$ROOT/assets/js/"*.js "$STAGE/assets/js/"
python3 - "$STAGE" "$OUT_DIR/SBOM.json" <<'PY'
import hashlib, json, pathlib, sys
root=pathlib.Path(sys.argv[1])
items=[]
for p in sorted(x for x in root.rglob('*') if x.is_file()):
    data=p.read_bytes()
    items.append({'path':p.relative_to(root).as_posix(),'bytes':len(data),'sha256':hashlib.sha256(data).hexdigest()})
pathlib.Path(sys.argv[2]).write_text(json.dumps({'format':'File03-SBOM-v1','files':items},indent=2)+"\n",encoding='utf-8')
PY
(
  cd "$OUT_DIR"
  zip -X -q -r "$(basename "$ZIP")" "$FOLDER"
)
unzip -t "$ZIP"
sha256sum "$ZIP" > "$ZIP.sha256"
echo "$ZIP"
