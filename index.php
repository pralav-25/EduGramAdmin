<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_SERVER['PATH_INFO']) ? trim($_SERVER['PATH_INFO'], '/') : (isset($_GET['route']) ? $_GET['route'] : '');

// secret for JWT - in production place in env var
$JWT_SECRET = getenv('JWT_SECRET') ?: 'change_this_secret';

// Simple router
switch (true) {
    // GET /students - list students with number of tests attended and details
    case $method === 'GET' && ($path === 'students' || $path === 'students/'):
        // params: q (search name/email), page, per_page
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = max(5, min(200, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page-1)*$per;

        if ($q !== '') {
            $like = "%{$q}%";
            $stmt = $pdo->prepare("SELECT s.id, u.name AS student_name, u.email, s.class, s.roll_no, COALESCE(st.tests_attended,0) AS tests_attended FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN student_test_counts st ON st.student_id = s.id WHERE u.name LIKE ? OR u.email LIKE ? ORDER BY tests_attended DESC LIMIT ? OFFSET ?");
            $stmt->execute([$like, $like, $per, $offset]);
            $rows = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT s.id, u.name AS student_name, u.email, s.class, s.roll_no, COALESCE(st.tests_attended,0) AS tests_attended FROM students s JOIN users u ON u.id = s.user_id LEFT JOIN student_test_counts st ON st.student_id = s.id ORDER BY tests_attended DESC LIMIT ? OFFSET ?");
            $stmt->execute([$per, $offset]);
            $rows = $stmt->fetchAll();
        }
        json_response(['page'=>$page,'per_page'=>$per,'data'=>$rows]);
        break;

    // GET /students/{id}
    case $method === 'GET' && preg_match('#^students/(\d+)$#', $path, $m):
        $id = (int)$m[1];
        $stmt = $pdo->prepare("SELECT s.*, u.name, u.email FROM students s JOIN users u ON u.id = s.user_id WHERE s.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) json_response(['error' => 'Not found'], 404);
        json_response($row);
        break;

    // GET /teachers
    case $method === 'GET' && ($path === 'teachers' || $path === 'teachers/'):
        // params: q (search), page, per_page
        $q = isset($_GET['q']) ? trim($_GET['q']) : '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $per = max(5, min(200, (int)($_GET['per_page'] ?? 20)));
        $offset = ($page-1)*$per;
        if ($q !== '') {
            $like = "%{$q}%";
            $stmt = $pdo->prepare("SELECT t.id, u.name, u.email, t.subject, t.department FROM teachers t JOIN users u ON u.id = t.user_id WHERE u.name LIKE ? OR u.email LIKE ? ORDER BY u.name LIMIT ? OFFSET ?");
            $stmt->execute([$like,$like,$per,$offset]);
            $rows = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("SELECT t.id, u.name, u.email, t.subject, t.department FROM teachers t JOIN users u ON u.id = t.user_id ORDER BY u.name LIMIT ? OFFSET ?");
            $stmt->execute([$per,$offset]);
            $rows = $stmt->fetchAll();
        }
        json_response(['page'=>$page,'per_page'=>$per,'data'=>$rows]);
        break;

    // GET /games
    case $method === 'GET' && ($path === 'games' || $path === 'games/'):
        $stmt = $pdo->query("SELECT * FROM games ORDER BY id");
        json_response($stmt->fetchAll());
        break;

    // POST /auth/login -> {email, password}
    case $method === 'POST' && ($path === 'auth/login' || $path === 'auth/login/'):
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['email']) || !isset($input['password'])) json_response(['error'=>'email and password required'],400);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$input['email']]);
        $user = $stmt->fetch();
        if(!$user || $user['password'] !== $input['password']) json_response(['error'=>'invalid credentials'],401);
        $payload = ['sub'=>$user['id'],'name'=>$user['name'],'role'=>$user['role'],'iat'=>time(),'exp'=>time()+60*60*24];
        $token = jwt_encode($payload, $JWT_SECRET);
        json_response(['token'=>$token,'user'=>$payload]);
        break;


    // GET /games/{id}/scores or /games/game1/scores
    case $method === 'GET' && preg_match('#^games/(\w+)/(scores)$#', $path, $m):
        $game = $m[1];
        // Map friendly names to tables
        $tableMap = [
            'game1' => 'game1_scores',
            'game2' => 'game2_scores',
            'game3' => 'game3_scores',
            'game4' => 'game4_scores',
        ];
        if (!isset($tableMap[$game])) json_response(['error' => 'Unknown game'], 404);
        $table = $tableMap[$game];
        $stmt = $pdo->prepare("SELECT gs.*, u.name as student_name, u.email FROM $table gs JOIN students s ON s.id = gs.student_id JOIN users u ON u.id = s.user_id ORDER BY gs.score DESC");
        $stmt->execute();
        json_response($stmt->fetchAll());
        break;

    // GET /users
    case $method === 'GET' && ($path === 'users' || $path === 'users/'):
        $stmt = $pdo->query("SELECT u.*, COALESCE(s.id, NULL) as student_id, COALESCE(t.id, NULL) as teacher_id FROM users u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN teachers t ON t.user_id = u.id ORDER BY u.name");
        json_response($stmt->fetchAll());
        break;

    // GET /leaderboard (top scores across all games)
    case $method === 'GET' && ($path === 'leaderboard' || $path === 'leaderboard/'):
        $query = "SELECT u.name AS student_name, g.game_name, gs.score, gs.played_at FROM (
            SELECT student_id, score, played_at, 'Game 1' as game_name FROM game1_scores
            UNION ALL
            SELECT student_id, score, played_at, 'Game 2' as game_name FROM game2_scores
            UNION ALL
            SELECT student_id, score, played_at, 'Game 3' as game_name FROM game3_scores
            UNION ALL
            SELECT student_id, score, played_at, 'Game 4' as game_name FROM game4_scores
        ) gs
        JOIN students s ON s.id = gs.student_id
        JOIN users u ON u.id = s.user_id
        ORDER BY gs.score DESC LIMIT 100";
        $stmt = $pdo->query($query);
        json_response($stmt->fetchAll());
        break;

    // GET /stats/aggregates -> counts per game and average scores
    case $method === 'GET' && ($path === 'stats/aggregates' || $path === 'stats/aggregates/'):
        $query = "SELECT 'Game 1' as game_name, COUNT(*) as count, AVG(score) as avg_score FROM game1_scores
                  UNION ALL
                  SELECT 'Game 2', COUNT(*), AVG(score) FROM game2_scores
                  UNION ALL
                  SELECT 'Game 3', COUNT(*), AVG(score) FROM game3_scores
                  UNION ALL
                  SELECT 'Game 4', COUNT(*), AVG(score) FROM game4_scores";
        $stmt = $pdo->query($query);
        json_response($stmt->fetchAll());
        break;

    // GET /stream/sse -> Server-Sent Events stream of events table (simple poll-based)
    case $method === 'GET' && ($path === 'stream/sse' || $path === 'stream/sse/'):
        // turn off output buffering
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', 'off');
        header("Content-Type: text/event-stream");
        header("Cache-Control: no-cache");
        header("Connection: keep-alive");

        // last id seen by client (optional)
        $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
        $start = time();
        while (true) {
            if (connection_aborted()) break;
            // fetch new events
            $stmt = $pdo->prepare("SELECT id, type, payload, created_at FROM events WHERE id > ? ORDER BY id ASC LIMIT 50");
            $stmt->execute([$lastId]);
            $rows = $stmt->fetchAll();
            foreach ($rows as $r) {
                $lastId = $r['id'];
                echo "id: {$r['id']}\n";
                echo "event: {$r['type']}\n";
                echo "data: {$r['payload']}\n\n";
                @ob_flush(); flush();
            }
            // heartbeat every 10s
            echo ":\n\n";
            @ob_flush(); flush();
            sleep(2);
            // safety: exit after 10 minutes
            if ((time()-$start) > 60*10) break;
        }
        exit;
        break;

    // POST /scores/game1 (or game2, game3, game4) - protected
    case $method === 'POST' && preg_match('#^scores/(game[1-4])$#', $path, $m):
        $game = $m[1];
        $tableMap = [
            'game1' => 'game1_scores',
            'game2' => 'game2_scores',
            'game3' => 'game3_scores',
            'game4' => 'game4_scores',
        ];
        if (!isset($tableMap[$game])) json_response(['error' => 'Unknown game'], 404);
        $table = $tableMap[$game];

    // require auth
    $token = get_bearer_token();
    $userPayload = $token ? jwt_decode($token, $JWT_SECRET) : null;
    if(!$userPayload) json_response(['error'=>'Authorization required'],401);

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) json_response(['error' => 'Invalid JSON'], 400);

        // expected: student_id, score, subject
        $student_id = isset($input['student_id']) ? (int)$input['student_id'] : null;
        $score = isset($input['score']) ? (int)$input['score'] : null;
        $subject = isset($input['subject']) ? $input['subject'] : null;

        if (!$student_id || $score === null) {
            json_response(['error' => 'student_id and score are required'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO $table (student_id, score, subject) VALUES (?, ?, ?)");
        $stmt->execute([$student_id, $score, $subject]);
        $insertId = $pdo->lastInsertId();

        // Optionally append to an events table for SSE/streaming (not created by default)
        try {
            $pdo->prepare("INSERT INTO events (type, payload) VALUES (?, ?)")->execute(['score', json_encode(['game'=>$game,'student_id'=>$student_id,'score'=>$score,'id'=>$insertId])]);
        } catch (Exception $e) { /* ignore if events table missing */ }

        json_response(['success' => true, 'id' => $insertId], 201);
        break;

    default:
        json_response(['error' => 'Endpoint not found', 'path' => $path, 'method' => $method], 404);
}
