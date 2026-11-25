import struct, sys
from cdat import *
from gsa_records import records
orig=records('/mnt/user-data/uploads/rami-a/Backup/units45.gsa')
roots={k for k,v in orig if b'(' not in k}
out=bytearray()
def rec(a,b): out.extend(struct.pack('<H',len(a))+a+struct.pack('<H',len(b))+b)
hdr1=b'Export of 18 globals from namespace UNITS45~Format=5.V~'
hdr2=b'01 Sep 2010 (CACHE.DAT)   Cache'
out.extend(struct.pack('<H',len(hdr1))+hdr1+struct.pack('<H',len(hdr2))+hdr2)
n=0
for g in GLOBALS:
    for k,v in walk_global(g):
        kt=key_text(k)
        if b'(' not in kt and kt not in roots: continue
        rec(kt,value(v)); n+=1
out.extend(b'\x00'*6)
open('units45-2010.gsa','wb').write(out)
print(n,len(out),sorted(roots))
