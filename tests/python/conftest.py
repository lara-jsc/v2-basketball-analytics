"""
Ensure the project root is on sys.path so analytics.* imports resolve
when pytest is run from any working directory.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent.parent))
