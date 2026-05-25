-- ============================================================
-- File    : attendance.sql
-- Role    : Full database schema and seed data for the system
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS attendance_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE attendance_system;

-- --------------------------------------------------------
-- departments
-- --------------------------------------------------------
CREATE TABLE departments (
  id         int           NOT NULL AUTO_INCREMENT,
  name       varchar(100)  NOT NULL,
  code       varchar(20)   NOT NULL,
  created_at datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dept_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- users
-- --------------------------------------------------------
CREATE TABLE users (
  id         int           NOT NULL AUTO_INCREMENT,
  name       varchar(100)  NOT NULL,
  email      varchar(100)  NOT NULL,
  phone      varchar(20)   DEFAULT NULL,
  password   varchar(255)  NOT NULL,
  role       enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  dept_id    int           DEFAULT NULL,
  avatar     varchar(255)  DEFAULT NULL,
  is_active  tinyint       NOT NULL DEFAULT 1,
  created_at datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  KEY fk_user_dept (dept_id),
  CONSTRAINT fk_user_dept FOREIGN KEY (dept_id) REFERENCES departments(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- courses
-- --------------------------------------------------------
CREATE TABLE courses (
  id         int           NOT NULL AUTO_INCREMENT,
  code       varchar(20)   NOT NULL,
  name       varchar(100)  NOT NULL,
  dept_id    int           NOT NULL,
  credits    int           NOT NULL DEFAULT 3,
  created_at datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_course_code (code),
  KEY fk_course_dept (dept_id),
  CONSTRAINT fk_course_dept FOREIGN KEY (dept_id) REFERENCES departments(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- classes
-- --------------------------------------------------------
CREATE TABLE classes (
  id            int           NOT NULL AUTO_INCREMENT,
  course_id     int           NOT NULL,
  teacher_id    int           NOT NULL,
  section       varchar(10)   NOT NULL,
  academic_year varchar(9)    NOT NULL,
  semester      tinyint       NOT NULL,
  schedule      varchar(100)  DEFAULT NULL,
  created_at    datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_class_course  (course_id),
  KEY fk_class_teacher (teacher_id),
  CONSTRAINT fk_class_course  FOREIGN KEY (course_id)  REFERENCES courses(id),
  CONSTRAINT fk_class_teacher FOREIGN KEY (teacher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- enrollments
-- --------------------------------------------------------
CREATE TABLE enrollments (
  id         int      NOT NULL AUTO_INCREMENT,
  class_id   int      NOT NULL,
  student_id int      NOT NULL,
  enrolled_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enrollment (class_id, student_id),
  KEY fk_enroll_student (student_id),
  CONSTRAINT fk_enroll_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE,
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- sessions
-- --------------------------------------------------------
CREATE TABLE sessions (
  id           int          NOT NULL AUTO_INCREMENT,
  class_id     int          NOT NULL,
  teacher_id   int          NOT NULL,
  session_date date         NOT NULL,
  topic        varchar(150) DEFAULT NULL,
  created_at   datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY fk_session_class   (class_id),
  KEY fk_session_teacher (teacher_id),
  CONSTRAINT fk_session_class   FOREIGN KEY (class_id)   REFERENCES classes(id),
  CONSTRAINT fk_session_teacher FOREIGN KEY (teacher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- attendance
-- --------------------------------------------------------
CREATE TABLE attendance (
  id         int      NOT NULL AUTO_INCREMENT,
  session_id int      NOT NULL,
  student_id int      NOT NULL,
  status     enum('present','absent','late','excused') NOT NULL DEFAULT 'absent',
  marked_at  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attendance (session_id, student_id),
  KEY fk_att_student (student_id),
  CONSTRAINT fk_att_session FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- settings
-- --------------------------------------------------------
CREATE TABLE settings (
  setting_key   varchar(60) NOT NULL,
  setting_value text        DEFAULT NULL,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- --------------------------------------------------------
-- Seed data
-- --------------------------------------------------------

-- Admin: password = Admin@123
INSERT INTO users (name, email, password, role) VALUES
('System Admin', 'admin@campus.edu',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('school_name',   'Campus Attendance System'),
('school_logo',   ''),
('min_attendance','75'),
('semester',      '1'),
('academic_year', '2025-2026');

-- Departments
INSERT INTO departments (name, code) VALUES
('Computer Science',    'CS'),
('Mathematics',         'MATH'),
('Business Administration', 'BUS');

-- Teachers (password: Teacher@123)
INSERT INTO users (name, email, phone, password, role, dept_id) VALUES
('Dr. Alice Johnson',  'alice@campus.edu',  '555-1001',
 '$2y$10$TKh8H1.PfYkafgb/1HHh2uFJKCaELpXhyK5wBjkw0FbBkON.5K6mO', 'teacher', 1),
('Prof. Bob Williams', 'bob@campus.edu',    '555-1002',
 '$2y$10$TKh8H1.PfYkafgb/1HHh2uFJKCaELpXhyK5wBjkw0FbBkON.5K6mO', 'teacher', 2),
('Dr. Carol Martinez', 'carol@campus.edu',  '555-1003',
 '$2y$10$TKh8H1.PfYkafgb/1HHh2uFJKCaELpXhyK5wBjkw0FbBkON.5K6mO', 'teacher', 3);

-- Students (password: Student@123)
INSERT INTO users (name, email, phone, password, role, dept_id) VALUES
('Emma Davis',    'emma@campus.edu',   '555-2001',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1),
('James Wilson',  'james@campus.edu',  '555-2002',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 1),
('Sophia Brown',  'sophia@campus.edu', '555-2003',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 2),
('Liam Garcia',   'liam@campus.edu',   '555-2004',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 2),
('Olivia Lee',    'olivia@campus.edu', '555-2005',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 3);

-- Courses
INSERT INTO courses (code, name, dept_id, credits) VALUES
('CS101',   'Introduction to Programming',   1, 3),
('CS202',   'Data Structures & Algorithms',  1, 3),
('MATH101', 'Calculus I',                    2, 4),
('BUS101',  'Principles of Management',      3, 3);

-- Classes
INSERT INTO classes (course_id, teacher_id, section, academic_year, semester, schedule) VALUES
(1, 2, 'A', '2025-2026', 1, 'Mon/Wed 9:00-10:30'),
(2, 2, 'A', '2025-2026', 1, 'Tue/Thu 11:00-12:30'),
(3, 3, 'A', '2025-2026', 1, 'Mon/Wed/Fri 8:00-9:00'),
(4, 4, 'A', '2025-2026', 1, 'Tue/Thu 14:00-15:30');

-- Enrollments
INSERT INTO enrollments (class_id, student_id) VALUES
(1, 5),(1, 6),(1, 7),
(2, 5),(2, 6),
(3, 7),(3, 8),
(4, 8),(4, 9);

-- Sessions (last 7 days)
INSERT INTO sessions (class_id, teacher_id, session_date, topic) VALUES
(1, 2, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Variables and Data Types'),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 4 DAY), 'Control Flow'),
(1, 2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Functions'),
(2, 2, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Arrays Introduction'),
(3, 3, DATE_SUB(CURDATE(), INTERVAL 6 DAY), 'Limits'),
(4, 4, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Intro to Management');

-- Attendance records
INSERT INTO attendance (session_id, student_id, status) VALUES
(1,5,'present'),(1,6,'present'),(1,7,'absent'),
(2,5,'present'),(2,6,'late'),  (2,7,'present'),
(3,5,'absent'), (3,6,'present'),(3,7,'present'),
(4,5,'present'),(4,6,'present'),
(5,7,'present'),(5,8,'absent'),
(6,8,'present'),(6,9,'present');