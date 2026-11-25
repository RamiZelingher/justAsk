import re, struct, sys
sys.path.insert(0,'/home/claude/cdat')
from gsa import records
from lst import parse
old=records('/mnt/user-data/uploads/rami-a/Backup/units45.gsa')
new=records('/home/claude/cdat/units45-2010.gsa')
O=dict(old)
EX=re.compile(rb'^[DEFG]200[678](SA|SB|SC|WA)$')
newq=set()
for k,v in new:
    m=re.match(rb'\^JustAskooQuestionD\("([^"]+)"\)$',k)
    if m and k not in O:
        p=parse(v)
        if EX.match(str(p[7]).encode()): newq.add(m.group(1))
oldobj={m.group(1) for k in O for m in [re.match(rb'\^JustAskooObjectD\("([^"]+)"',k)] if m}
add=[]
for k,v in new:
    m=re.match(rb'\^JustAskooQuestionD\("([^"]+)"',k)
    if m and m.group(1) in newq: add.append((k,v)); continue
    m=re.match(rb'\^JustAskooSourceD\("([^"]+)"\)$',k)
    if m and EX.match(m.group(1)) and k not in O: add.append((k,v)); continue
    m=re.match(rb'\^JustAskooObjectD\("([^"]+)"',k)
    if m and m.group(1) not in oldobj: add.append((k,v)); continue
    m=re.match(rb'\^Relationship\("Question","Subject","([^"]+)"',k)
    if m and m.group(1) in newq: add.append((k,v)); continue
    m=re.match(rb'\^Relationship\("Object","Topic","([^"]+)"',k)
    if m and m.group(1) not in oldobj and k not in O: add.append((k,v)); continue
print('new questions',len(newq),'added nodes',len(add))
# merge keeping old file order (generator uses dict; order irrelevant) -> write old then added
out=bytearray()
g=open('/mnt/user-data/uploads/rami-a/Backup/units45.gsa','rb').read()
i=g.find(b'\x00^')-1
out+=g[:i]
for k,v in old+add:
    out+=struct.pack('<H',len(k))+k+struct.pack('<H',len(v))+v
out+=b'\x00'*6
open('combined.gsa','wb').write(out)
