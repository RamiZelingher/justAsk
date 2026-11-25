def parse(v):
    out=[];i=0
    while i<len(v):
        l=v[i]
        if l==0:
            n=v[i+1]|v[i+2]<<8; t=v[i+3]; d=v[i+4:i+3+n]; i+=n+3
        elif l==1: out.append(None); i+=1; continue
        else: t=v[i+1]; d=v[i+2:i+l]; i+=l
        if t==1: out.append(d.decode('cp1255','replace'))
        elif t==4: out.append(int.from_bytes(d,'little',signed=True) if d else 0)
        elif t==5: out.append(-int.from_bytes(d,'little') if d else 0)
        else: out.append(('t%d'%t,d))
    return out
