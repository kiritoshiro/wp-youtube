#!/usr/bin/env python3
"""Create the exact WordPress plugin ZIP used by GitHub releases."""
from hashlib import sha256
from pathlib import Path
from sys import argv
from zipfile import ZIP_DEFLATED, ZipFile
import re

ROOT = Path(__file__).resolve().parents[1]
tag = argv[1] if len(argv) == 2 else ""
if not re.fullmatch(r"v[0-9]+\.[0-9]+\.[0-9]+", tag):
    raise SystemExit("Expected a vX.Y.Z tag")
version = tag[1:]
if f" * Version: {version}\n" not in (ROOT / "wp-youtube.php").read_text():
    raise SystemExit("Tag does not match plugin header")

files = [ROOT / name for name in ("wp-youtube.php", "LICENSE", "README.md")]
files += sorted((ROOT / "includes").glob("*.php"))
files += sorted((ROOT / "assets").glob("*"))
if not all(path.is_file() for path in files):
    raise SystemExit("A required plugin file is missing")

out = ROOT / "dist"
out.mkdir(exist_ok=True)
archive = out / f"wp-youtube-{version}.zip"
with ZipFile(archive, "w", ZIP_DEFLATED, compresslevel=9) as package:
    for path in files:
        package.write(path, "wp-youtube/" + path.relative_to(ROOT).as_posix())
digest = sha256(archive.read_bytes()).hexdigest()
(out / f"wp-youtube-{version}.zip.sha256").write_text(f"{digest}  {archive.name}\n")
with ZipFile(archive) as package:
    if package.testzip() is not None:
        raise SystemExit("Corrupt ZIP")
print(f"Created {archive.name} ({digest})")
