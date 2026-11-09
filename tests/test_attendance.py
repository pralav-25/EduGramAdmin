"""Exercise the actual portable view SQL without needing a MySQL service."""

import re
import sqlite3
import unittest
from pathlib import Path


class AttendanceViewTests(unittest.TestCase):
    def setUp(self):
        self.db = sqlite3.connect(":memory:")
        self.addCleanup(self.db.close)
        self.db.executescript("""
            CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT);
            CREATE TABLE students (id INTEGER PRIMARY KEY, user_id INTEGER);
            INSERT INTO users VALUES (1, 'Alice'), (2, 'No attempts yet');
            INSERT INTO students VALUES (1, 1), (2, 2);
        """)
        for number in range(1, 5):
            self.db.execute(f"CREATE TABLE game{number}_scores (student_id INTEGER, score INTEGER)")
        schema = (Path(__file__).resolve().parents[1] / "sql" / "edu_schema.sql").read_text()
        view = re.search(r"CREATE VIEW student_test_counts AS\b.*?;", schema, re.S)
        self.assertIsNotNone(view, "The application's attendance view must be present")
        self.db.executescript(view.group())

    def counts(self):
        return dict(self.db.execute("SELECT student_id, tests_attended FROM student_test_counts"))

    def test_students_without_scores_have_zero_attendance(self):
        self.assertEqual(self.counts(), {1: 0, 2: 0})

    def test_counts_attempts_across_all_games_and_keeps_unplayed_students(self):
        for number in range(1, 5):
            self.db.execute(f"INSERT INTO game{number}_scores VALUES (1, 80)")
        self.db.execute("INSERT INTO game1_scores VALUES (1, 90)")
        self.assertEqual(self.counts(), {1: 5, 2: 0})

    def test_view_reflects_first_score_and_deleted_scores(self):
        self.db.execute("INSERT INTO game3_scores VALUES (2, 75)")
        self.assertEqual(self.counts(), {1: 0, 2: 1})
        self.db.execute("DELETE FROM game3_scores WHERE student_id = 2")
        self.assertEqual(self.counts(), {1: 0, 2: 0})


if __name__ == "__main__":
    unittest.main()
