"""Reader for Caché %GO 'Format=5' global export files (.gsa) and $LIST values."""
import struct, re, sys, collections


def read_records(path):
    d = open(path, 'rb').read()
    p = 0
    # header: two length-prefixed strings
    hdr = []
    for _ in range(2):
        n = struct.unpack_from('<H', d, p)[0]; p += 2
        hdr.append(d[p:p + n]); p += n
    out = []
    while p + 2 <= len(d):
        n = struct.unpack_from('<H', d, p)[0]; p += 2
        if n == 0:
            break
        ref = d[p:p + n]; p += n
        n = struct.unpack_from('<H', d, p)[0]; p += 2
        if n == 0xFFFF:  # long value (guess: 4-byte length follows)
            n = struct.unpack_from('<I', d, p)[0]; p += 4
        val = d[p:p + n]; p += n
        out.append((ref.decode('cp1255', 'replace'), val))
    return hdr, out, p, len(d)


def _dec(t, data):
    if t == 1:
        return data.decode('cp1255', 'replace')
    if t == 2:
        return data.decode('utf-16le', 'replace')
    if t == 4:
        return int.from_bytes(data, 'little') if data else 0
    if t == 5:
        return -(int.from_bytes(bytes(255 - x for x in data), 'little') + 1) if data else -1
    if t == 6:
        exp = data[0] if data else 0
        m = int.from_bytes(data[1:], 'little') if len(data) > 1 else 0
        return m * 10 ** (exp - 256 if exp > 127 else exp)
    if t == 7:
        exp = data[0]
        m = int.from_bytes(bytes(255 - x for x in data[1:]), 'little') + 1
        return -m * 10 ** (exp - 256 if exp > 127 else exp)
    if t == 8:
        return struct.unpack('<d', data)[0]
    raise ValueError(t)


def parse_list(b):
    """Decode a Caché $LIST. Returns list or None if b is not a valid list."""
    items, p = [], 0
    try:
        while p < len(b):
            ln = b[p]; hl = 1
            if ln == 0:
                ln = struct.unpack_from('<H', b, p + 1)[0]; hl = 3
                if ln == 0:
                    ln = struct.unpack_from('<I', b, p + 3)[0]; hl = 7
                ln += hl          # long form: length excludes the header
            elif ln == 1:
                items.append(None); p += 1; continue
            if ln < hl + 1 or p + ln > len(b):
                return None
            t = b[p + hl]
            items.append(_dec(t, b[p + hl + 1:p + ln]))
            p += ln
    except Exception:
        return None
    return items


def decode_val(v):
    l = parse_list(v)
    if l is not None and len(v) > 0:
        return l
    return v.decode('cp1255', 'replace')


if __name__ == '__main__':
    hdr, recs, p, n = read_records(sys.argv[1])
    print(hdr, len(recs), p, n)
    c = collections.Counter(re.match(r'\^[^(]*', r).group(0) for r, _ in recs)
    for k, v in c.most_common():
        print(v, k)
