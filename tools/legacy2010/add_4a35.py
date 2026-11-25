import re
s=open('import_2010.sql',encoding='utf-8').read()
assert '4A35' not in s
txt=open('db/seed.sql',encoding='utf-8').read()+s
kind={int(m.group(1)):(m.group(2),m.group(3)) for m in re.finditer(r"INSERT INTO theory_items \(id, legacy_id, kind, title, body_html\) VALUES \((\d+), '([^']+)', '(\w+)'",txt)}
tids=[int(x) for x in re.findall(r"INSERT INTO question_theory \(question_id, theory_id\) VALUES \(778, (\d+)\)",s)]
KO={'definition':1,'theorem':2,'method':3,'other':4}
tids.sort(key=lambda t:(KO[kind[t][1]],kind[t][0]))
add=["-- new subtopic 4A35 'הסתברות מותנית' (added in the old system after 2006), used by question Q2746",
"UPDATE subjects SET study_order = study_order + 1 WHERE parent_id = 47 AND study_order >= 6;",
"INSERT INTO subjects (curriculum_id, parent_id, legacy_id, units, slug, study_order) VALUES (1, 47, '4A35', 4, '4-conditional-probability', 6);",
"SET @sid = LAST_INSERT_ID();",
"INSERT INTO subject_names (subject_id, lang, name, is_original, is_reviewed) VALUES (@sid, 'he', 'הסתברות מותנית', 1, 0);",
"INSERT INTO subject_names (subject_id, lang, name, is_original, is_reviewed) VALUES (@sid, 'en', 'Conditional probability', 0, 0);",
"INSERT INTO subject_names (subject_id, lang, name, is_original, is_reviewed) VALUES (@sid, 'fr', 'Probabilités conditionnelles', 0, 0);",
"INSERT INTO question_subjects (question_id, subject_id, position) VALUES (778, @sid, 1);"]
add+=["INSERT INTO subject_theory (subject_id, theory_id, position) VALUES (@sid, %d, %d);"%(t,i) for i,t in enumerate(tids,1)]
s=s.replace("\nCOMMIT;\n","\n"+"\n".join(add)+"\nCOMMIT;\n")
open('import_2010.sql','w',encoding='utf-8').write(s)
