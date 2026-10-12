#!/usr/bin/env python3
"""Create the exact WordPress plugin ZIP used by GitHub releases.

The plugin files are copied to a staging directory, their scripts and styles
are minified there (scripts/minify-package-assets.mjs, needs Node and
`npm ci`), and the ZIP is built from that copy. The repository keeps the
readable sources. Any minifier failure stops the build.
"""
from hashlib import sha256
from pathlib import Path
from shutil import copy2, which
from subprocess import run
from sys import argv
from tempfile import TemporaryDirectory
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
files += sorted((ROOT / "languages").glob("*.php"))
if not all(path.is_file() for path in files):
    raise SystemExit("A required plugin file is missing")

node = which("node")
if node is None:
    raise SystemExit("Node.js is required to minify the package (run npm ci first)")

out = ROOT / "dist"
out.mkdir(exist_ok=True)
archive = out / f"wp-youtube-{version}.zip"
with TemporaryDirectory() as temp:
    stage = Path(temp) / "wp-youtube"
    staged = []
    for path in files:
        target = stage / path.relative_to(ROOT)
        target.parent.mkdir(parents=True, exist_ok=True)
        copy2(path, target)
        staged.append(target)
    result = run([node, str(ROOT / "scripts" / "minify-package-assets.mjs"), str(stage)], cwd=ROOT)
    if result.returncode != 0:
        raise SystemExit(f"Minifying the package failed (exit {result.returncode})")
    with ZipFile(archive, "w", ZIP_DEFLATED, compresslevel=9) as package:
        for path in staged:
            package.write(path, "wp-youtube/" + path.relative_to(stage).as_posix())
digest = sha256(archive.read_bytes()).hexdigest()
(out / f"wp-youtube-{version}.zip.sha256").write_text(f"{digest}  {archive.name}\n")
with ZipFile(archive) as package:
    if package.testzip() is not None:
        raise SystemExit("Corrupt ZIP")
    names = package.namelist()
    if any(not name.startswith("wp-youtube/") or name.startswith(("wp-youtube/node_modules/", "wp-youtube/scripts/")) or name.endswith(("package.json", "package-lock.json")) for name in names):
        raise SystemExit("Unexpected file in the package")
print(f"Created {archive.name} ({digest})")
