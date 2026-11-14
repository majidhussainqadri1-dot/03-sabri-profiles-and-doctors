#!/usr/bin/env python3
"""File03 V12.3 immutable-source/installed-WordPress manifest parity.

Source: approved, extracted release plugin root.
Installed: read-only JSON emitted by f03-v123-readonly-freeze.php.
No network, writes, secrets, database access, or user records. Fail closed.
"""
import argparse
import hashlib
import json
import sys
from pathlib import Path

ROOT_FILES = {"sabri-profiles-doctors.php", "uninstall.php", "readme.txt"}
RUNTIME_DIRS = ("includes", "assets")


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        while True:
            block = stream.read(1048576)
            if not block:
                break
            digest.update(block)
    return digest.hexdigest()


def approved_files(root: Path) -> dict[str, str]:
    files = {}
    for relative in sorted(ROOT_FILES):
        target = root / relative
        if target.is_symlink() or not target.is_file():
            files[relative] = "MISSING_OR_INVALID"
        else:
            files[relative] = sha256_file(target)
    for directory in RUNTIME_DIRS:
        folder = root / directory
        if not folder.is_dir() or folder.is_symlink():
            raise ValueError(f"Missing or unsafe source directory: {directory}")
        for path in sorted(folder.rglob("*")):
            if path.is_symlink():
                raise ValueError("Symlink in candidate runtime tree: " + str(path.relative_to(root)))
            if not path.is_file():
                continue
            rel = path.relative_to(root).as_posix()
            files[rel] = sha256_file(path)
    return dict(sorted(files.items()))


def manifest_hash(files: dict[str, str]) -> str:
    lines = [f"{path}\t{checksum}" for path, checksum in sorted(files.items())]
    return hashlib.sha256(("\n".join(lines) + "\n").encode("utf-8")).hexdigest()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--approved-root", required=True, type=Path)
    parser.add_argument("--installed-json", required=True, type=Path)
    opts = parser.parse_args()
    source = approved_files(opts.approved_root.resolve())
    installed = json.loads(opts.installed_json.read_text(encoding="utf-8"))
    if installed.get("source") != "installed-wordpress-runtime":
        raise ValueError("Evidence is not an installed WordPress runtime snapshot")
    payload = installed.get("file03")
    if not isinstance(payload, dict):
        raise ValueError("Missing File03 installed evidence")
    indexed = payload.get("runtime_files_sha256")
    if not isinstance(indexed, dict) or not indexed:
        raise ValueError("Missing installed runtime files")
    if any(not isinstance(k, str) or not isinstance(v, str) for k, v in indexed.items()):
        raise ValueError("Malformed installed manifest")
    installed_digest = manifest_hash(indexed)
    reported_digest = payload.get("installed_manifest_sha256")
    if installed_digest != reported_digest:
        print("FAIL: installed evidence manifest is internally inconsistent", file=sys.stderr)
        return 2
    missing = sorted(set(source) - set(indexed))
    unexpected = sorted(set(indexed) - set(source))
    mismatched = sorted(k for k in source.keys() & indexed.keys() if source[k] != indexed[k])
    if missing or unexpected or mismatched:
        print("FAIL File03 approved source differs from installed runtime:", file=sys.stderr)
        for label, values in (("missing on installation", missing),
                              ("unexpected installed runtime", unexpected),
                              ("byte mismatch", mismatched)):
            if values:
                print(f"  {label}: {', '.join(values[:20])}", file=sys.stderr)
        return 1
    if manifest_hash(source) != installed_digest:
        print("FAIL: manifest ordering or hash mismatch", file=sys.stderr)
        return 2
    print(f"PASS File03 immutable manifest parity: {len(source)} runtime files SHA256-equal")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (OSError, ValueError, json.JSONDecodeError) as exc:
        print("FAIL manifest parity evidence: " + str(exc), file=sys.stderr)
        raise SystemExit(2)
