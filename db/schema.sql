-- justask: math Q&A site - MySQL 8 schema
-- Content imported from the legacy Units45 (InterSystems Caché) database.
-- Rich text is stored as HTML; formulas are embedded MathML; images are files under static/media.

CREATE DATABASE IF NOT EXISTS justask CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE justask;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS question_languages, translations, subject_theory, question_theory, theory_items, solution_steps, hints, question_parts,
                     question_subjects, questions, exams, subject_names, subjects,
                     curriculum_names, curricula, languages, media, settings;
SET FOREIGN_KEY_CHECKS = 1;

-- Languages of the site interface and of the names below
CREATE TABLE languages (
  code       CHAR(2)     NOT NULL PRIMARY KEY,   -- he / en / fr
  name       VARCHAR(40) NOT NULL,               -- the language's own name: עברית, English, Français
  dir        ENUM('rtl','ltr') NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A curriculum = one subject tree on the home page (4 units, 5 units, the French Première S)
CREATE TABLE curricula (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(30) NOT NULL UNIQUE,      -- e.g. bagrut-4
  content_lang CHAR(2)     NOT NULL,             -- language the questions are written in
  units        TINYINT UNSIGNED NULL,            -- 4 / 5 study units (יח"ל); NULL when not a Bagrut track
  sort_order   INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_curriculum_lang FOREIGN KEY (content_lang) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE curriculum_names (
  curriculum_id INT UNSIGNED NOT NULL,
  lang          CHAR(2)      NOT NULL,
  name          VARCHAR(200) NOT NULL,
  description   VARCHAR(500) NULL,
  PRIMARY KEY (curriculum_id, lang),
  CONSTRAINT fk_cn_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE CASCADE,
  CONSTRAINT fk_cn_lang       FOREIGN KEY (lang) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subject tree, e.g. "Differential calculus" > "Investigating rational functions". Names live in subject_names.
CREATE TABLE subjects (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  curriculum_id INT UNSIGNED NOT NULL,
  parent_id     INT UNSIGNED NULL,
  legacy_id     VARCHAR(20)  NULL,               -- ^JustAskooSubjectD id in the legacy database
  units         TINYINT UNSIGNED NULL,           -- 4 or 5 study units (יח"ל)
  slug          VARCHAR(120) NOT NULL UNIQUE,    -- used in URLs
  study_order   INT NOT NULL DEFAULT 0,          -- study order: the subject's place among its siblings (1 = taught first)
  UNIQUE KEY uq_subject_legacy (curriculum_id, legacy_id),
  CONSTRAINT fk_subject_curriculum FOREIGN KEY (curriculum_id) REFERENCES curricula(id) ON DELETE CASCADE,
  CONSTRAINT fk_subject_parent FOREIGN KEY (parent_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The subject's name in each language: one row per subject and language
CREATE TABLE subject_names (
  subject_id  INT UNSIGNED NOT NULL,
  lang        CHAR(2)      NOT NULL,
  name        VARCHAR(255) NOT NULL,
  description VARCHAR(500) NULL,
  is_original TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 = the name as written in the legacy database
  is_reviewed TINYINT(1)   NOT NULL DEFAULT 0,   -- 1 = a person checked this name / translation
  PRIMARY KEY (subject_id, lang),
  CONSTRAINT fk_sn_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_sn_lang    FOREIGN KEY (lang) REFERENCES languages(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Where a question comes from: a bagrut exam, a mock exam, a practice set
CREATE TABLE exams (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legacy_id   VARCHAR(20)  NULL UNIQUE,          -- ^JustAskooSourceD id
  title       VARCHAR(200) NOT NULL,             -- e.g. "4 יחידות יוני 1996"
  source_type VARCHAR(20)  NOT NULL DEFAULT 'bagrut',   -- bagrut / mock / practice
  units       TINYINT UNSIGNED NULL,
  year        SMALLINT UNSIGNED NULL,
  session     VARCHAR(30)  NULL                  -- e.g. "יוני", "ינואר", "קיץ מועד ב"
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE questions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legacy_id   VARCHAR(20)  NULL UNIQUE,          -- e.g. Q1405
  exam_id     INT UNSIGNED NULL,
  question_no TINYINT UNSIGNED NULL,             -- number of the question within the exam
  units       TINYINT UNSIGNED NOT NULL,
  title       VARCHAR(200) NULL,                 -- short label for lists
  status      ENUM('draft','published') NOT NULL DEFAULT 'published',
  pdf_file    VARCHAR(255) NULL,                 -- printable question+solution in files/, e.g. q200.pdf (q200_en.pdf = English copy)
  video_file  VARCHAR(255) NULL,                 -- video lesson in files/, e.g. q200.mp4 (q200_en.mp4 = English copy)
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_question_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE question_subjects (
  question_id INT UNSIGNED NOT NULL,
  subject_id  INT UNSIGNED NOT NULL,
  position    INT NOT NULL DEFAULT 0,            -- order of the question in this subject's list (1 = first)
  PRIMARY KEY (question_id, subject_id),
  CONSTRAINT fk_qs_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  CONSTRAINT fk_qs_subject  FOREIGN KEY (subject_id)  REFERENCES subjects(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A question has one or more parts; each part has its own text, final answer, hints and steps
CREATE TABLE question_parts (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id       INT UNSIGNED NOT NULL,
  part_no           TINYINT UNSIGNED NOT NULL,
  body_html         MEDIUMTEXT NOT NULL,
  final_answer_html MEDIUMTEXT NULL,
  UNIQUE KEY uq_part (question_id, part_no),
  CONSTRAINT fk_part_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guided hints ("הכוונה לפתרון")
CREATE TABLE hints (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  part_id   INT UNSIGNED NOT NULL,
  seq       SMALLINT UNSIGNED NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  UNIQUE KEY uq_hint (part_id, seq),
  CONSTRAINT fk_hint_part FOREIGN KEY (part_id) REFERENCES question_parts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detailed step-by-step solution: explanatory text + the formula for the step
CREATE TABLE solution_steps (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  part_id   INT UNSIGNED NOT NULL,
  seq       SMALLINT UNSIGNED NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  math_html MEDIUMTEXT NULL,                     -- MathML block shown under the text
  UNIQUE KEY uq_step (part_id, seq),
  CONSTRAINT fk_step_part FOREIGN KEY (part_id) REFERENCES question_parts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Theory background: definitions, theorems and methods taught in grades 11-12
CREATE TABLE theory_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legacy_id  VARCHAR(30)  NOT NULL UNIQUE,       -- e.g. S9DDI020; also used in URLs
  kind       ENUM('definition','theorem','method','other') NOT NULL DEFAULT 'other',
  title      VARCHAR(255) NOT NULL,
  body_html  MEDIUMTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Theory background of a subject (definitions, theorems, methods), in display order
CREATE TABLE subject_theory (
  subject_id INT UNSIGNED NOT NULL,
  theory_id  INT UNSIGNED NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,      -- order in the subject's theory list
  PRIMARY KEY (subject_id, theory_id),
  CONSTRAINT fk_st_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
  CONSTRAINT fk_st_theory  FOREIGN KEY (theory_id)  REFERENCES theory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which theory items a question's solution refers to (built from the links in the text)
CREATE TABLE question_theory (
  question_id INT UNSIGNED NOT NULL,
  theory_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (question_id, theory_id),
  CONSTRAINT fk_qt_question FOREIGN KEY (question_id) REFERENCES questions(id)    ON DELETE CASCADE,
  CONSTRAINT fk_qt_theory   FOREIGN KEY (theory_id)   REFERENCES theory_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Image files referenced from content (files live in static/media/<filename>)
CREATE TABLE media (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename  VARCHAR(120) NOT NULL UNIQUE,
  mime_type VARCHAR(50)  NOT NULL,
  bytes     INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Translations of the content (questions, hints, solution steps, theory) into other languages.
-- entity: part (fields body, answer) | hint (body) | step (body, math) | theory (title, body)
CREATE TABLE translations (
  entity      VARCHAR(10)  NOT NULL,
  entity_id   INT UNSIGNED NOT NULL,
  field       VARCHAR(10)  NOT NULL,
  lang        CHAR(2)      NOT NULL,
  html        MEDIUMTEXT   NOT NULL,
  is_reviewed TINYINT(1)   NOT NULL DEFAULT 0,   -- machine translation until a person checks it
  PRIMARY KEY (entity, entity_id, field, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questions that are fully translated into a language (shown on that language's pages)
CREATE TABLE question_languages (
  question_id INT UNSIGNED NOT NULL,
  lang        CHAR(2)      NOT NULL,
  PRIMARY KEY (question_id, lang),
  CONSTRAINT fk_ql_question FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site-wide values shown on every page (version in the footer, address for feedback and corrections)
CREATE TABLE settings (
  name  VARCHAR(50)  NOT NULL PRIMARY KEY,
  value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
