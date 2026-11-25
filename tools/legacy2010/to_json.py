import re,json
s=open('import_2010.sql',encoding='utf-8').read()
parts=re.split(r';\n(?=INSERT INTO |UPDATE |DELETE |SET |START |COMMIT|--|$)',s)
st=[]
for p in parts:
    lines=p.strip().split('\n')
    while lines and lines[0].startswith('--'): lines.pop(0)
    p='\n'.join(lines).strip().rstrip(';')
    if not p or p.startswith('START TRANSACTION') or p=='COMMIT' or p.startswith('SET NAMES'): continue
    st.append(p)
json.dump({'note':'JustAsk: 2006-2008 bagrut exams (D,E,F,G) from the Units45 2010 database; added 21 Nov 2026','check':{'exams':54,'questions':641,'question_parts':641,'hints':2609,'solution_steps':8298,'theory_items':665},'statements':st},open('import_2010.json','w',encoding='utf-8'),ensure_ascii=False)
print(len(st))
