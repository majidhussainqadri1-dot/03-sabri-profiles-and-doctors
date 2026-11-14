#!/usr/bin/env python3
"""V12.3 File03 acceptance inventory integrity: 72 original tests, no inflation.

This script validates checklist structure only. A checkbox or source CI result
is NEVER evidence that a Staging case passed. Human/owner receipts, installed
checksum, database parity and Founder approval remain separate release gates.
"""
from pathlib import Path
import re
import sys

root = Path(__file__).resolve().parents[1]
content = (root / "STAGING-ACCEPTANCE.md").read_text(encoding="utf-8")
original = content.split("## A. Install / upgrade / schema / routes", 1)
if len(original) != 2:
    raise SystemExit("FAIL no original Staging group A")
baseline = original[1].split("## V12.3 D14–D21 supplemental safety gate", 1)
if len(baseline) != 2:
    raise SystemExit("FAIL missing separate V12.3 supplementary annex")
baseline_text, annex = baseline

expected = {
    "A": 7, "A2": 3, "B": 5, "C": 6,
    "D": 7, "E": 8, "F": 6, "G": 5,
    "H": 4, "I": 7, "J": 5, "K": 9,
}
counts = {"A": 0}
active = "A"
items = []
for line in baseline_text.splitlines():
    heading = re.match(r"^## (A2|[B-K])\.", line)
    if heading:
        active = heading.group(1)
        counts.setdefault(active, 0)
        continue
    match = re.match(r"^- \[([ xX])\] (.+)$", line)
    if match:
        counts[active] = counts.get(active, 0) + 1
        test_id = f"{active}-{counts[active]:02d}"
        items.append((test_id, match.group(1), match.group(2)))

if counts != expected or len(items) != 72:
    print("FAIL baseline Staging matrix changed unexpectedly:", counts, file=sys.stderr)
    raise SystemExit(1)
if len({x[0] for x in items}) != 72:
    raise SystemExit("FAIL duplicated original Staging acceptance identifier")

# Supplemental code-defect acceptance is never counted in the original 72.
for defect in ("D14", "D15", "D16", "D17", "D18", "D19", "D20", "D21"):
    if not re.search(r"^- \[ \] \*\*" + defect + r"\b", annex, re.MULTILINE):
        raise SystemExit("FAIL missing supplemental test for " + defect)

accepted = sum(mark.lower() == "x" for _, mark, _ in items)
print("PASS File03 V12.3 checklist integrity: 72 unique original cases, "
      f"{accepted} checkmarks (NOT Stage receipts); "
      "D14–D21 supplemental checks recorded outside the 72.")
