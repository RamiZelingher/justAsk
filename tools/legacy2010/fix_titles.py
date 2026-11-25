import re
s=open('import_2010.sql',encoding='utf-8').read()
L={'D':"ד'",'E':"ה'",'F':"ו'",'G':"ז'"}
for m in re.finditer(r"INSERT INTO exams \(.*?\) VALUES \(\d+, '([DEFG])(\d{4})(SA|SB|SC|WA)', '((?:[^']|'')*)'",s):
    q,y,ss,old=m.groups()
    season='חורף' if ss=='WA' else 'קיץ'
    t="שאלון %s %s %s"%(L[q],season,y)+{'SB':" - מועד ב'",'SC':' - מועד מיוחד'}.get(ss,'')
    new=t.replace("'","''")
    if new!=old:
        n0=s.count("'"+old+"'")+s.count("'בגרות "+old+", שאלה")
        s=s.replace("'"+old+"'","'"+new+"'").replace("'בגרות "+old+", שאלה","'בגרות "+new+", שאלה")
        print(q+y+ss, old,'->',new, n0)
open('import_2010.sql','w',encoding='utf-8').write(s)
