"""Exercise the real PHP/MySQL service against an isolated seeded database."""
import json
import os
import urllib.error
import urllib.request

BASE = os.environ.get("API_BASE", "http://127.0.0.1:8000/api/index.php?route=")


def request(route, payload=None, token=None, raw=None):
    headers = {"Content-Type": "application/json"}
    if token:
        headers["Authorization"] = "Bearer " + token
    body = raw if raw is not None else json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(BASE + route, data=body, headers=headers)
    try:
        response = urllib.request.urlopen(req, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    return response.status, json.loads(response.read())


for route in ["students", "teachers", "students&q=Alice&per_page=5", "teachers&q=Carol", "games", "leaderboard", "stats/aggregates"]:
    status, data = request(route)
    assert status == 200, (route, status, data)
status, leaderboard = request("leaderboard")
assert len(leaderboard) >= 8 and "game_name" in leaderboard[0]
assert request("students&q=not-a-student")[1]["data"] == []
assert request("students&page=2&per_page=5")[1]["data"] == []
assert request("users")[0] == 401
assert request("auth/login", raw=b'{broken')[0] == 400
assert request("auth/login", raw=b'[]')[0] == 400
assert request("auth/login", raw=b'x' * 65537)[0] == 413
assert request("auth/login", {"email": "alice@example.com", "password": "wrong"})[0] == 401
status, login = request("auth/login", {"email": "alice@example.com", "password": "password"})
assert status == 200, login
token = login["token"]
assert request("users", token=token)[0] == 403
assert request("scores/game1", {"student_id": 2, "score": 80}, token)[0] == 403
assert request("scores/game1", {"student_id": 1, "score": 101}, token)[0] == 422
assert request("scores/game1", {"student_id": 1, "score": 80, "subject": "Math"}, token)[0] == 201
print("PHP/MySQL integration checks passed.")

assert request("students")[1]["total"] == 2
assert request("teachers")[1]["total"] == 1
assert request("students&q=Alice")[1]["total"] == 1
assert request("students&q=not-a-student")[1]["total"] == 0
assert request("students&page=999999999999999999999999999")[0] == 200
