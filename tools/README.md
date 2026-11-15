# כלי ההמרה של JustAsk

הכלים שבנו את מסד הנתונים של האתר מגיבוי המערכת הישנה (Units45 / InterSystems Caché), תרגמו את התוכן לאנגלית, והפיקו קובצי PDF ושיעורי וידאו.
התיקייה חסומה לגישה מהדפדפן (`.htaccess`), ו**אין להעלות אותה לשרת החיצוני**.

הכלים כתובים ב־Python 3, ורצים ב־Linux / WSL / Ubuntu.

## בניית מסד הנתונים

```bash
# 1. ייצוא: גיבוי Caché  ->  db/seed.sql (MySQL)
python3 tools/export_seed.py <units45.gsa> db/_graphics_list.txt
#    גרסת SQLite לבדיקות:
python3 tools/export_seed.py <units45.gsa> db/_graphics_list.txt --dialect sqlite
python3 tools/make_sqlite_test_db.py test.db
```
אחר כך מתקינים: `install.php?force=1`.

| קובץ | תפקיד |
|---|---|
| `export_seed.py` | ממיר את הגיבוי (`units45.gsa`) ל־`db/seed.sql`: נושאים, שאלות, רמזים, שלבי פתרון, רקע תאורטי, תרגומים, הגדרות |
| `gsa.py` | קורא את קובץ הגיבוי של Caché |
| `subject_names.py` | שמות הנושאים בעברית, אנגלית וצרפתית |
| `question_files.json` | קובצי PDF ווידאו לכל שאלה (בתיקייה `files/`), למשל `{"200": {"pdf": "q200.pdf", "video": "q200.mp4"}}` |
| `site_settings.json` | ערכי טבלת `settings`: גרסה (`2026-10`) וכתובת המשוב |
| `make_sqlite_test_db.py` | בונה מסד SQLite לבדיקות |

## תרגום התוכן

התרגום נשמר בזיכרון תרגום לפי משפטים: `translations/en.json` (כ־17,000 משפטים). הייצוא מחליף כל משפט בעברית בתרגום שלו; פריט שאין לו תרגום מלא מוצג בעברית.

```bash
python3 tools/content_i18n.py todo test.db en todo.json   # רשימת המשפטים שעוד לא תורגמו
TODO=todo.json python3 tools/tr_show.py 0 50               # הצגת משפטים 0–49
TODO=todo.json python3 tools/tr_add.py 0 batch.json        # הוספת תרגומים (רשימת מחרוזות באותו סדר)
```
הסימנים `⟦1⟧`, `⟦2⟧`… מסמנים נוסחאות ותגיות, וחייבים להופיע בתרגום בדיוק כמו במקור.

## קובצי PDF ושיעורי וידאו (`media/`)

| קובץ | תפקיד |
|---|---|
| `question_pdf.py` | דף הדפסה לשאלה (שאלה, רמזים, פתרון מלא): `python3 question_pdf.py 200 he` ואז הדפסה ל־PDF ב־Chrome |
| `lesson_video_he.py` | שיעור וידאו לשאלה 200 בעברית (כתוביות, בלי קול) |
| `lesson_video_en.py` | שיעור וידאו לשאלה 200 באנגלית, עם קריינות (flite) |

הסרטונים נבנים עם Chrome (Playwright) ו־ffmpeg. התוכן של שיעור הווידאו כתוב בתוך הקבצים, כך שלשאלה אחרת מעתיקים ומשנים את הסצנות.
