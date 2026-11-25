-- Site users and their activity.
-- These tables are NOT in the DROP list of schema.sql: rebuilding the content (install.php?force=1) keeps the users,
-- their teacher links and their visit history. inc/auth.php also creates them when they are missing.
-- question_visits has no foreign key to questions, because the content tables are dropped and rebuilt on reinstall
-- (question ids stay the same).

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(40)  NOT NULL UNIQUE,      -- login name
  email         VARCHAR(190) NULL UNIQUE,          -- optional; can also be used to log in
  full_name     VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,             -- password_hash() (bcrypt / argon2); the password itself is never stored
  role          ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
  lang          CHAR(2)      NOT NULL DEFAULT 'he', -- interface language of this user: he / en / fr (set by the administrator)
  course        VARCHAR(30)  NULL,                 -- the user's course = curricula.code (e.g. 4 or 5 study units); NULL = not set.
                                                   -- No foreign key: the content tables are dropped and rebuilt on reinstall (codes stay the same).
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,   -- 0 = blocked: cannot log in
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Which students belong to which teacher (a student may have several teachers)
CREATE TABLE IF NOT EXISTS teacher_students (
  teacher_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (teacher_id, student_id),
  KEY ix_ts_student (student_id),
  CONSTRAINT fk_ts_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_ts_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every time a logged-in student opens a question page: one row per visit
CREATE TABLE IF NOT EXISTS question_visits (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  visited_at  DATETIME NOT NULL,
  KEY ix_qv_user (user_id, question_id),
  KEY ix_qv_question (question_id),
  CONSTRAINT fk_qv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
