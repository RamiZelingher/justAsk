import mmap,struct
F=open('/mnt/user-data/uploads/rami-a/DataBases/Units45/CACHE.DAT','rb')
f=mmap.mmap(F.fileno(),0,access=mmap.ACCESS_READ)
B=8192
def off(n): return (n-1)*B
def hdr(n):
    o=off(n); used,typ,coll=struct.unpack_from('<IBB',f,o); right=struct.unpack_from('<I',f,o+8)[0]
    return used,typ,coll,right
def nodes(n):
    o=off(n); used,typ,coll,right=hdr(n)
    p=o+28; end=o+28+used; key=b''
    while p<end:
        x=struct.unpack_from('<I',f,p)[0]; L=x&0x1fff; pre=(x>>13)&0x1ff; new=(x>>22)&0xff
        if L<4: break
        key=key[:pre]+f[p+4:p+4+new]
        val=f[p+4+new+(L&3):p+(L&~3)]
        yield key,val
        p+=L&~3
def gdir():
    d={}
    for k,v in nodes(3):
        d[k]=struct.unpack_from('<I',v,len(v)-4)[0]
    return d
def leftmost(n):
    while hdr(n)[1] not in (8,):
        k,v=next(nodes(n)); n=struct.unpack_from('<I',v,len(v)-4)[0]
    return n
def chain(n):
    while n:
        yield n; n=hdr(n)[3]
def walk(gname):
    """yield (key,value) of all data nodes of global"""
    top=gdir()[gname]
    for n in chain(leftmost(top)):
        yield from nodes(n)
def data_blocks():
    nb=len(f)//B
    res={}
    for n in range(1,nb+1):
        o=off(n)
        if f[o+4]!=8: continue
        try:
            k,v=next(nodes(n))
        except StopIteration: continue
        res[n]=k
    return res
_DB=None
def chains_for(gname):
    global _DB
    if _DB is None: _DB=data_blocks()
    g=gname+b'\x00'
    mine={n for n,k in _DB.items() if k.startswith(g) or k==gname}
    pointed={hdr(n)[3] for n in mine}
    heads=[n for n in mine if n not in pointed]
    out=[]
    for h in heads:
        c=[];n=h
        while n and n in mine and n not in c: c.append(n); n=hdr(n)[3]
        out.append(c)
    return mine,out

def dec_sub(s):
    if s==b'\x06\xe1': return -100000000000000
    if s==b'\x10': return 0
    if s and 0x11<=s[0]<=0x1b and (len(s)==1 or all(0x1c<=c<0x1c+100 for c in s[1:])):
        e=s[0]; pairs=[c-0x1c for c in s[1:]]
        if not pairs: return 1 if e==0x12 else ('NUM?',s.hex())
        n=e-0x12  # number of integer pairs
        v=0
        for p in pairs: v=v*100+p
        v*=100**(n-len(pairs))
        return v
    if len(s)>1 and s[0]==0x1f: s=s[1:]
    return bytes(c+0x1c for c in s).decode('cp1255','replace')
def dec_key(k):
    parts=k.split(b'\x00')
    return parts[0].decode('latin1'), tuple(dec_sub(p) for p in parts[1:])
def fmt(g,subs):
    return '^'+g+('('+','.join('"%s"'%x if isinstance(x,str) else str(x) for x in subs)+')' if subs else '')
def is_list(v):
    i=0
    try:
        while i<len(v):
            l=v[i]
            if l==0:
                if i+3>len(v): return False
                n=v[i+1]|v[i+2]<<8
                if n<1: return False
                i+=n+3
            elif l==1: i+=1
            else: i+=l
        return i==len(v)
    except: return False
def read_big(ln,blk):
    out=b''
    while blk and len(out)<ln:
        used,typ,coll,right=hdr(blk)
        out+=f[off(blk)+28:off(blk)+28+used]
        blk=right
    return out[:ln]
def is_big(v):
    nb=len(f)//B
    last=struct.unpack_from('<I',v,1)[0]
    if not (0<last<=B-28): return False
    for i in range(5,len(v),4):
        b=struct.unpack_from('<I',v,i)[0]
        if not (1<=b<=nb) or f[off(b)+4]!=24: return False
    return True
def value(v):
    """return python bytes (string) or int"""
    if v and v[0]==6 and len(v)>=9 and (len(v)-5)%4==0 and is_big(v):
        out=b''
        for i in range(5,len(v),4):
            blk=struct.unpack_from('<I',v,i)[0]
            used=hdr(blk)[0]
            out+=f[off(blk)+28:off(blk)+28+used]
        return out
    if len(v)==5 and v[0]==0:
        return str(struct.unpack_from('<i',v,1)[0]).encode()
    return bytes(v)
def walk_global(g):
    mine,chs=chains_for(g)
    for c in chs:
        for b in c:
            for k,v in nodes(b):
                yield k,v
GLOBALS=[b'CacheMsg',b'JustAsk.KeyWordsD',b'JustAsk.ObjectStream',b'JustAsk.RulesD',b'JustAsk.StepStream',b'JustAskooObjectD',b'JustAskooObjectI',b'JustAskooObjectTopicsI',b'JustAskooObjectTypeD',b'JustAskooObjectTypeI',b'JustAskooQuestionD',b'JustAskooQuestionI',b'JustAskooSourceD',b'JustAskooSourceTypeD',b'JustAskooSubjectD',b'JustAskooSubjectI',b'JustAskooTopicD',b'Relationship']
def sub_text(s):
    if s==b'\x06\xe1': return b'-100000000000000'
    if s==b'\x10': return b'0'
    if s and 0x11<=s[0]<=0x1b and (len(s)==1 or all(0x1c<=c<0x1c+100 for c in s[1:])):
        v=dec_sub(s)
        if isinstance(v,int): return str(v).encode()
        raise ValueError(s.hex())
    if len(s)>1 and s[0]==0x1f: s=s[1:]
    raw=bytes(c+0x1c for c in s)
    return b'"'+raw.replace(b'"',b'""')+b'"'
def key_text(k):
    parts=k.split(b'\x00')
    g=b'^'+parts[0]
    if len(parts)==1: return g
    return g+b'('+b','.join(sub_text(p) for p in parts[1:])+b')'
