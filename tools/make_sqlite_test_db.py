"""Build a throw-away SQLite copy of the MySQL schema + seed, for quick tests without MySQL.

  python3 tools/export_seed.py <gsa> <images> --dialect sqlite
  python3 tools/make_sqlite_test_db.py test.db
  JUSTASK_DB=sqlite:test.db python3 app.py
"""
import os, re, sqlite3, sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
out = sys.argv[1] if len(sys.argv) > 1 else 'test.db'
s = open(os.path.join(ROOT, 'db', 'schema.sql'), encoding='utf-8').read()
s = re.sub(r'(?m)^(CREATE DATABASE|USE|SET).*$', '', s)
s = re.sub(r'DROP TABLE IF EXISTS[^;]+;', '', s)
s = s.replace(' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', '').replace('ENGINE=InnoDB', '').replace(' UNSIGNED', '').replace(' ON UPDATE CURRENT_TIMESTAMP', '')
s = s.replace('INT AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT')
s = re.sub(r'ENUM\([^)]*\)', 'TEXT', s)
s = re.sub(r'UNIQUE KEY \w+ \(', 'UNIQUE (', s)
s = re.sub(r'MEDIUMTEXT|TINYINT|SMALLINT', 'TEXT', s)
if os.path.exists(out):
    os.remove(out)
c = sqlite3.connect(out)
c.executescript(s)
c.executescript(open(os.path.join(ROOT, 'db', 'seed.sqlite.sql'), encoding='utf-8').read())
c.commit()
print('created', out, {t: c.execute('SELECT COUNT(*) FROM %s' % t).fetchone()[0]
                       for t in ('questions', 'hints', 'solution_steps', 'theory_items', 'media')})
