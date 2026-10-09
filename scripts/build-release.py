#!/usr/bin/env python3
"""Create the exact WordPress plugin ZIP used by GitHub releases.

Usage: build-release.py vX.Y.Z [--any-version]
--any-version skips the tag/header match (CI trial build only).
"""
from hashlib import sha256
from pathlib import Path
from sys import argv
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

out = ROOT / "dist"
out.mkdir(exist_ok=True)
archive = out / f"{SLUG}-{version}.zip"
with ZipFile(archive, "w", ZIP_DEFLATED, compresslevel=9) as package:
    for path in files:
        package.write(path, f"{SLUG}/" + path.relative_to(ROOT).as_posix())
digest = sha256(archive.read_bytes()).hexdigest()
(out / f"{SLUG}-{version}.zip.sha256").write_text(f"{digest}  {archive.name}\n")
with ZipFile(archive) as package:
    if package.testzip() is not None:
        raise SystemExit("Corrupt ZIP")
    names = package.namelist()
print(f"Created {archive.name}: {len(names)} files, {archive.stat().st_size} bytes, sha256 {digest}")
