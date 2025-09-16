-- edu_schema.sql
-- Create database and tables for Edugram

CREATE DATABASE IF NOT EXISTS edu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edu;

-- users: generic user table for students, teachers, admins
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  role ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
  password VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- students table stores student-specific info
CREATE TABLE IF NOT EXISTS students (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  class VARCHAR(50),
  roll_no VARCHAR(50),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- teachers table
CREATE TABLE IF NOT EXISTS teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(100),
  department VARCHAR(100),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- games master table
CREATE TABLE IF NOT EXISTS games (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Four game score tables (game1..game4)
-- Each records student_id, score, subject, played_at
CREATE TABLE IF NOT EXISTS game1_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  score INT NOT NULL DEFAULT 0,
  subject VARCHAR(100),
  played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS game2_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  score INT NOT NULL DEFAULT 0,
  subject VARCHAR(100),
  played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS game3_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  score INT NOT NULL DEFAULT 0,
  subject VARCHAR(100),
  played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS game4_scores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  score INT NOT NULL DEFAULT 0,
  subject VARCHAR(100),
  played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);

-- users sample data
-- For demo the seeded users use 'password' as their password. Replace with hashed passwords in production.
INSERT INTO users (name,email,role,password) VALUES
('Alice Student','alice@example.com','student','password'),
('Bob Student','bob@example.com','student','password'),
('Carol Teacher','carol@example.com','teacher','password'),
('Dave Admin','dave@example.com','admin','password');

-- students sample data (link to users)
INSERT INTO students (user_id,class,roll_no) VALUES
(1,'10A','S001'),
(2,'10A','S002');

-- teachers sample data
INSERT INTO teachers (user_id,subject,department) VALUES
(3,'Mathematics','Math Dept');

-- games
INSERT INTO games (name,description) VALUES
('QuizRace','Timed quiz game'),
('WordMatch','Vocabulary matching'),
('MathBlitz','Rapid math challenges'),
('GeoQuest','Geography puzzles');

-- sample scores
INSERT INTO game1_scores (student_id,score,subject) VALUES
(1,85,'Mathematics'),
(2,72,'Mathematics');

INSERT INTO game2_scores (student_id,score,subject) VALUES
(1,90,'English'),
(2,60,'English');

INSERT INTO game3_scores (student_id,score,subject) VALUES
(1,78,'Mathematics'),
(2,88,'Mathematics');

INSERT INTO game4_scores (student_id,score,subject) VALUES
(1,95,'Geography'),
(2,65,'Geography');

-- small view to count tests per student (optional)
DROP VIEW IF EXISTS student_test_counts;
CREATE VIEW student_test_counts AS
SELECT s.id AS student_id, u.name AS student_name, COUNT(*) AS tests_attended
FROM students s
JOIN users u ON u.id = s.user_id
LEFT JOIN (
  SELECT student_id FROM game1_scores
  UNION ALL
  SELECT student_id FROM game2_scores
  UNION ALL
  SELECT student_id FROM game3_scores
  UNION ALL
  SELECT student_id FROM game4_scores
) AS all_scores ON all_scores.student_id = s.id
GROUP BY s.id, u.name;
