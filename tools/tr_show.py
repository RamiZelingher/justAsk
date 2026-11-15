# Show untranslated runs.  usage: TODO=todo.json python3 tr_show.py FROM TO
import json,sys
t=json.load(open(__import__('os').environ.get('TODO','todo.json'))); a,b=int(sys.argv[1]),int(sys.argv[2])
for i in range(a,min(b,len(t))): print(i, t[i])
