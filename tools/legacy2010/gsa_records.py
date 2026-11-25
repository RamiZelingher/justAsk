import struct
def records(path):
    g=open(path,'rb').read()
    i=g.find(b'\x00^')-1
    out=[]
    while i+2<=len(g):
        kl=struct.unpack_from('<H',g,i)[0]; i+=2
        if kl==0: break
        k=g[i:i+kl]; i+=kl
        vl=struct.unpack_from('<H',g,i)[0]; i+=2
        v=g[i:i+vl]; i+=vl
        out.append((k,v))
    return out
