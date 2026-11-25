# Units45 2010 extraction (21 Nov 2026)

`units45-2010.gsa` = the same 18 globals as `Backup\units45.gsa` (Jan 2006), read directly from
`F:\גיבוי רמי 10.11.2026\rami-a\DataBases\Units45\CACHE.DAT` (database of 1 Sep 2010, Caché 5.0).
Same file format as the 2006 export, so `tools/export_seed.py` can read it.

- 286,985 nodes (2006: 240,120). 1,094 questions (2006: 641).
- New exams: questionnaires D, E (4 units) and F, G (5 units), 2006–2008: 40 exams, 211 questions,
  354 images (all present in `Graphics\2006`, `2007`, `2008`).
- Also new: mock exams (מתכונות) M007, M105, M107, M108, MA005, MA008, MA2008, Mtemp, Mtemp2 and 2 old bagrut questions (1968, 1970).
- Where a node exists in both files, 190,485 values are identical; the rest are edits made in the old system after 2006.

## How CACHE.DAT is read (`cdat.py`)
- 8 KB blocks, block N at offset (N-1)*8192. Header 28 bytes: used bytes (u32), type (u8: 9 directory, 66/70/6 pointer, 8 data, 24 big string), collation, right link at offset 8.
- Global directory = block 3. Data blocks are found by scanning type-8 blocks and following right links.
- Node: u32 header (bits 0-12 length incl. 0-3 pad bytes in the low 2 bits, 13-21 shared key prefix, 22-29 new key bytes), key bytes, pad, value.
- Key: subscripts separated by 0x00. Strings: each byte = char - 0x1C (strings that start with a digit get a 0x1F marker).
  Numbers: 0x10 = 0, 0x12 = 1, otherwise exponent byte 0x12+n followed by base-100 digit pairs (+0x1C).
- Value: inline ($LIST or raw string); 0x00+int32 = integer; 0x06 + last-block length + block list = big string (type-24 blocks).

Rebuild: `python3 write_gsa.py` (paths inside cdat.py / write_gsa.py).
