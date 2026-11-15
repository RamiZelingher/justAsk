# Add translations to the translation memory (translations/en.json).
# usage: TODO=todo.json python3 tr_add.py START file.json
#   todo.json: the list made by `content_i18n.py todo`; file.json: English strings for todo[START:START+n]
#   placeholders such as ⟦1⟧ must appear in the translation exactly as in the Hebrew.
import json,sys,re
sys.path.insert(0,__import__('os').path.dirname(__import__('os').path.abspath(__file__)))
from content_i18n import load_memory, save_memory
t=json.load(open(__import__('os').environ.get('TODO','todo.json'))); start=int(sys.argv[1]); tr=json.load(open(sys.argv[2]))
mem=load_memory('en'); bad=0
for i,e in enumerate(tr):
    k=t[start+i]
    if sorted(re.findall(r'⟦\d+⟧',k))!=sorted(re.findall(r'⟦\d+⟧',e)): print('TOKENS', start+i, k, '|', e); bad+=1
    mem[k]=e
save_memory('en',mem); print('added',len(tr),'memory',len(mem),'token mismatches',bad)
