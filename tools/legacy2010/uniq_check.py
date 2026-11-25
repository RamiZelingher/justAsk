import re,collections
ROW=re.compile(r"^INSERT INTO (\w+) \(([^)]*)\) VALUES \((.*?)\);$", re.M|re.S)
exec(open('build_import.py').read().split('def load(p):')[0].split('ROW=')[0])
src=open('build_import.py').read()
exec(src[src.index('def split_vals'):src.index('def load(p):')])
PK={'exams':['id'],'questions':['id'],'question_parts':['id'],'hints':['id'],'solution_steps':['id'],'theory_items':['id'],
    'media':['filename'],'translations':['entity','entity_id','field','lang'],'question_theory':['question_id','theory_id'],
    'question_subjects':['question_id','subject_id'],'subject_theory':['subject_id','theory_id'],'subject_names':['subject_id','lang']}
UNIQ={'exams':[['legacy_id']],'questions':[['legacy_id']],'theory_items':[['legacy_id']]}
def keys(path, skip_deleted=None):
    k=collections.defaultdict(collections.Counter)
    for m in ROW.finditer(open(path,encoding='utf-8').read()):
        t=m.group(1); cols=[c.strip() for c in m.group(2).split(',')]; vals=[v[1] for v in split_vals(m.group(3))]
        d=dict(zip(cols,vals))
        if skip_deleted and t=='subject_theory' and d['subject_id'] in skip_deleted: continue
        for kk in ([PK[t]] if t in PK else [])+UNIQ.get(t,[]):
            k[(t,tuple(kk))][tuple(d[c] for c in kk)]+=1
    return k
imp=open('import_2010.sql',encoding='utf-8').read()
deleted=set(re.search(r'DELETE FROM subject_theory WHERE subject_id IN \(([^)]*)\)',imp).group(1).replace(' ','').split(','))
O=keys('db/seed.sql',deleted); N=keys('import_2010.sql')
bad=0
for tk,c in N.items():
    for key,n in c.items():
        if n>1 or O[tk].get(key): bad+=1; print('CONFLICT',tk,key, n, O[tk].get(key)) if bad<10 else None
print('conflicts',bad)
# media in schema unique?
