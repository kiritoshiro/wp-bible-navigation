#!/usr/bin/env python3
"""Create the exact WordPress plugin ZIP used by GitHub releases.

Usage: build-release.py vX.Y.Z [--any-version]
--any-version skips the tag/header match (CI trial build only).

The plugin files are copied to a temporary staging directory, where
scripts/minify-package-assets.mjs minifies assets/*.js and assets/*.css
(run `npm ci --ignore-scripts` first). The repository keeps the readable
sources. The build fails if Node or the minifier fails.
"""
from hashlib import sha256
from pathlib import Path
from shutil import copy2, which
from subprocess import run
from sys import argv
from tempfile import TemporaryDirectory
from zipfile import ZIP_DEFLATED, ZipFile
import re

SLUG = "wp-bible-navigation"
ROOT = Path(__file__).resolve().parents[1]
tag = argv[1] if len(argv) >= 2 else ""
if not re.fullmatch(r"v[0-9]+\.[0-9]+\.[0-9]+", tag):
    raise SystemExit("Expected a vX.Y.Z tag")
version = tag[1:]
main = (ROOT / f"{SLUG}.php").read_text(encoding="utf-8")
if "--any-version" not in argv[2:]:
    if f" * Version: {version}\n" not in main or f"define( 'BNAV_VERSION', '{version}' );" not in main:
        raise SystemExit("Tag does not match the plugin header and BNAV_VERSION")

files = [ROOT / name for name in (f"{SLUG}.php", "LICENSE", "README.md")]
files += sorted((ROOT / "includes").glob("*.php"))
files += sorted((ROOT / "assets").glob("*.*"))
files += sorted((ROOT / "languages").glob("*.php"))
if not all(path.is_file() for path in files) or len(files) < 12:
    raise SystemExit("A required plugin file is missing")

node = which("node")
if node is None:
    raise SystemExit("Node.js is required to minify the packaged assets")

out = ROOT / "dist"
out.mkdir(exist_ok=True)
archive = out / f"{SLUG}-{version}.zip"
checksum = out / f"{archive.name}.sha256"
# A failed build must not leave an older ZIP behind under the same name.
archive.unlink(missing_ok=True)
checksum.unlink(missing_ok=True)
with TemporaryDirectory() as tmp:
    stage = Path(tmp) / SLUG
    names = [path.relative_to(ROOT).as_posix() for path in files]
    for name in names:
        (stage / name).parent.mkdir(parents=True, exist_ok=True)
        copy2(ROOT / name, stage / name)
    minify = run([node, str(ROOT / "scripts" / "minify-package-assets.mjs"), str(stage)], cwd=ROOT)
    if minify.returncode != 0:
        raise SystemExit(f"Minifying the packaged assets failed (exit {minify.returncode})")
    with ZipFile(archive, "w", ZIP_DEFLATED, compresslevel=9) as package:
        for name in names:
            package.write(stage / name, f"{SLUG}/{name}")
digest = sha256(archive.read_bytes()).hexdigest()
checksum.write_bytes(f"{digest}  {archive.name}\n".encode())
with ZipFile(archive) as package:
    if package.testzip() is not None:
        raise SystemExit("Corrupt ZIP")
    packed = package.namelist()
if packed != [f"{SLUG}/{name}" for name in names]:
    raise SystemExit("The ZIP does not hold exactly the plugin files")
print(f"Created {archive.name}: {len(packed)} files, {archive.stat().st_size} bytes, sha256 {digest}")
