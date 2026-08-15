#!/usr/bin/env python3
"""Generate l10n/pt_BR.json — thin wrapper around the atomic rebuild.

Prefer: python3 scripts/rebuild-pt-BR-l10n-atomic.py
"""

from __future__ import annotations

import runpy
from pathlib import Path

if __name__ == "__main__":
	runpy.run_path(str(Path(__file__).with_name("rebuild-pt-BR-l10n-atomic.py")), run_name="__main__")
