#!/bin/bash
# $1 = first id, $2 = last id
cd /home/claude/pdf/tools/media
for i in $(seq $1 $2); do
  JUSTASK_SQLITE=/home/claude/pdf/test.db JUSTASK_MEDIA=/home/claude/pdf/media python3 question_pdf.py $i he >/dev/null || echo "HTMLFAIL $i"
  google-chrome --headless --no-sandbox --disable-gpu --no-pdf-header-footer --user-data-dir=/tmp/chr$1 --print-to-pdf=/home/claude/pdf/out/q$i.pdf q${i}_he.html >/dev/null 2>&1 || echo "PDFFAIL $i"
done
