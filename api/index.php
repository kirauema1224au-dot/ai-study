<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
  respond(204, ['ok' => true]);
}

// Start Session
session_start();

// routing
$route = $_GET['r'] ?? $path;
if ($route === '/index.php') {
  $route = '/';
} elseif (str_starts_with($route, '/index.php/')) {
  $route = substr($route, strlen('/index.php'));
  if ($route === '') {
    $route = '/';
  }
}

// DB Connection
$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_NAME') ?: 'appdb';
$user = getenv('DB_USER') ?: 'appuser';
$pass = getenv('DB_PASS') ?: 'apppass';

try {
  $pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  respond(500, ["ok" => false, "error" => "DB connection failed", "detail" => $e->getMessage()]);
}

// Auth Routes
if ($route === '/register' && $method === 'POST') {
  ensureSchema($pdo);
  $raw = file_get_contents('php://input');
  $req = json_decode($raw, true);
  if (!isset($req['username']) || !isset($req['password'])) {
    respond(400, ["ok" => false, "error" => "username and password required"]);
  }

  try {
    $hash = password_hash($req['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:u, :p)");
    $stmt->execute([':u' => $req['username'], ':p' => $hash]);
    respond(200, ["ok" => true, "message" => "User registered"]);
  } catch (PDOException $e) {
    if ($e->errorInfo[1] === 1062) { // Duplicate entry
      respond(409, ["ok" => false, "error" => "Username already taken"]);
    }
    respond(500, ["ok" => false, "error" => "DB Error", "detail" => $e->getMessage()]);
  }
}

if ($route === '/login' && $method === 'POST') {
  ensureSchema($pdo);
  $raw = file_get_contents('php://input');
  $req = json_decode($raw, true);
  if (!isset($req['username']) || !isset($req['password'])) {
    respond(400, ["ok" => false, "error" => "username and password required"]);
  }

  $stmt = $pdo->prepare("SELECT user_id, username, password_hash FROM users WHERE username = :u");
  $stmt->execute([':u' => $req['username']]);
  $user = $stmt->fetch();

  if ($user && password_verify($req['password'], $user['password_hash'])) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    respond(200, ["ok" => true, "message" => "Logged in", "user" => ["id" => $user['user_id'], "name" => $user['username'], "avatar_seed" => $user['avatar_seed'] ?? null]]);
  } else {
    respond(401, ["ok" => false, "error" => "Invalid credentials"]);
  }
}

if ($route === '/logout') {
  session_destroy();
  respond(200, ["ok" => true, "message" => "Logged out"]);
}

if ($route === '/stats' && $method === 'GET') {
  ensureSchema($pdo);
  $uid = requireLogin();

  $stmt = $pdo->prepare("
    SELECT
      q.domain_name,
      q.topic_name,
      COALESCE(latest.is_correct, 0) AS is_correct
    FROM questions q
    LEFT JOIN (
      SELECT
        a.question_id,
        a.is_correct
      FROM answers a
      INNER JOIN (
        SELECT question_id, MAX(answer_id) AS latest_answer_id
        FROM answers
        WHERE user_id = :uid
        GROUP BY question_id
      ) pick ON pick.latest_answer_id = a.answer_id
      WHERE a.user_id = :uid
    ) latest ON latest.question_id = q.question_id
    WHERE q.created_by = :uid
      AND q.is_active = 1
  ");
  $stmt->execute([':uid' => $uid]);
  $rows = $stmt->fetchAll();

  $genreStats = [];
  $topicStats = [];
  $total = 0;
  $correct = 0;

  foreach ($rows as $r) {
    $total++;
    $isCorrect = (int) $r['is_correct'] === 1;
    if ($isCorrect) {
      $correct++;
    }

    $genre = (string) $r['domain_name'];
    if (!isset($genreStats[$genre])) {
      $genreStats[$genre] = ['name' => $genre, 'correct' => 0, 'total' => 0];
    }
    $genreStats[$genre]['total']++;
    if ($isCorrect) {
      $genreStats[$genre]['correct']++;
    }

    $topic = (string) $r['topic_name'];
    if (!isset($topicStats[$topic])) {
      $topicStats[$topic] = ['name' => $topic, 'correct' => 0, 'total' => 0];
    }
    $topicStats[$topic]['total']++;
    if ($isCorrect) {
      $topicStats[$topic]['correct']++;
    }
  }

  $calcAcc = fn (int $c, int $t): ?float => $t > 0 ? round(($c / $t) * 100, 1) : null;

  $genreOut = array_map(function ($g) use ($calcAcc) {
    $g['accuracy'] = $calcAcc($g['correct'], $g['total']);
    return $g;
  }, array_values($genreStats));

  $topicOut = array_map(function ($t) use ($calcAcc) {
    $t['accuracy'] = $calcAcc($t['correct'], $t['total']);
    return $t;
  }, array_values($topicStats));

  respond(200, [
    "ok" => true,
    "overall" => [
      "correct" => $correct,
      "total" => $total,
      "accuracy" => $calcAcc($correct, $total)
    ],
    "genres" => $genreOut,
    "topics" => $topicOut
  ]);
}

if ($route === '/me') {
  ensureSchema($pdo);
  if (isset($_SESSION['user_id'])) {
    $uid = (int) $_SESSION['user_id'];
    // Count created questions
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE created_by = :uid AND is_active = 1");
    $stmt->execute([':uid' => $uid]);
    $count = $stmt->fetchColumn();

    // Get user details
    $stmtUser = $pdo->prepare("SELECT username, avatar_seed FROM users WHERE user_id = :uid");
    $stmtUser->execute([':uid' => $uid]);
    $u = $stmtUser->fetch();

    respond(200, [
      "ok" => true,
      "logged_in" => true,
      "user" => [
        "id" => $uid,
        "name" => $u['username'],
        "avatar_seed" => $u['avatar_seed'],
        "created_questions_count" => (int) $count
      ]
    ]);
  } else {
    respond(200, ["ok" => true, "logged_in" => false]);
  }
}

if ($route === '/profile' && $method === 'POST') {
  ensureSchema($pdo);
  $uid = requireLogin();
  $raw = file_get_contents('php://input');
  $req = json_decode($raw, true);

  if (isset($req['avatar_seed'])) {
    $seed = substr((string)$req['avatar_seed'], 0, 64);
    $stmt = $pdo->prepare("UPDATE users SET avatar_seed = :seed WHERE user_id = :uid");
    $stmt->execute([':seed' => $seed, ':uid' => $uid]);
    respond(200, ["ok" => true, "message" => "Avatar updated"]);
  }
  respond(400, ["ok" => false, "error" => "No valid fields to update"]);
}

// ... (DB Connection Setup maintained here in original file, skipping to next change) ...




if ($route === '/' || $route === '/health') {
  respond(200, [
    "ok" => true,
    "db" => "connected",
    "how_to" => [
      "questions" => "/index.php?r=/questions",
      "questions_alt" => "/index.php/questions",
      "answers" => "/index.php?r=/answers",
      "generate" => "/index.php?r=/generate",
    ]
  ]);
}

if ($route === '/questions' && $method === 'GET') {
  ensureSchema($pdo);
  $where = [];
  $binds = [];
  $joins = [];
  if (isset($_GET['domain']) && $_GET['domain'] !== '') {
    $where[] = 'q.domain_name = :domain';
    $binds[':domain'] = (string) $_GET['domain'];
  }
  if (isset($_GET['topic']) && $_GET['topic'] !== '') {
    $where[] = 'q.topic_name = :topic';
    $binds[':topic'] = (string) $_GET['topic'];
  }
  if (isset($_GET['scope']) && $_GET['scope'] === 'my') {
    if (!isset($_SESSION['user_id'])) {
      respond(401, ["ok" => false, "error" => "Login required for my questions"]);
    }
    $where[] = 'q.created_by = :uid';
    $binds[':uid'] = (int) $_SESSION['user_id'];
  } elseif (isset($_GET['scope']) && $_GET['scope'] === 'review') {
    $uid = requireLogin();
    $recheckDays = max(1, min(30, (int) (getenv('REVIEW_RECHECK_DAYS') ?: 7)));
    $recheckBefore = date('Y-m-d H:i:s', time() - ($recheckDays * 86400));
    $latestSub = "
      SELECT
        a.question_id,
        a.is_correct,
        a.answered_at
      FROM answers a
      INNER JOIN (
        SELECT question_id, MAX(answer_id) AS latest_answer_id
        FROM answers
        WHERE user_id = :uid
        GROUP BY question_id
      ) pick ON pick.latest_answer_id = a.answer_id
      WHERE a.user_id = :uid
    ";
    $answerStatsSub = "
      SELECT
        question_id,
        SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) AS correct_count
      FROM answers
      WHERE user_id = :uid
      GROUP BY question_id
    ";
    $joins[] = "LEFT JOIN ({$latestSub}) latest ON latest.question_id = q.question_id";
    $joins[] = "LEFT JOIN ({$answerStatsSub}) answer_stats ON answer_stats.question_id = q.question_id";
    $where[] = "(
      latest.is_correct = 0
      OR latest.is_correct IS NULL
      OR (
        latest.is_correct = 1
        AND COALESCE(answer_stats.correct_count, 0) = 1
        AND latest.answered_at <= :review_recheck_before
      )
    )";
    $where[] = 'q.created_by = :uid';
    $binds[':uid'] = (int) $uid;
    $binds[':review_recheck_before'] = $recheckBefore;
  } elseif (isset($_GET['scope']) && $_GET['scope'] !== '') {
    respond(400, ["ok" => false, "error" => "Invalid scope"]);
  }
  $whereSql = count($where) ? ('AND ' . implode(' AND ', $where)) : '';
  $orderSql = "ORDER BY q.question_id ASC, c.choice_label ASC";
  if (isset($_GET['scope']) && $_GET['scope'] === 'review') {
    $orderSql = "ORDER BY latest.answered_at ASC, q.question_id ASC, c.choice_label ASC";
  }
  $sql = "
    SELECT
      q.question_id, q.domain_name, q.topic_name, q.title, q.stem, q.correct_label, q.explanation,
      c.choice_id, c.choice_label, c.choice_text,
      stats.correct_count, stats.total_count
    FROM questions q
    LEFT JOIN question_choices c ON c.question_id = q.question_id
    LEFT JOIN (
      SELECT question_id, SUM(is_correct) AS correct_count, COUNT(*) AS total_count
      FROM answers
      GROUP BY question_id
    ) stats ON stats.question_id = q.question_id
    " . implode("\n    ", $joins) . "
    WHERE q.is_active = 1
    {$whereSql}
    {$orderSql}
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($binds);
  $rows = $stmt->fetchAll();

  $out = [];
  foreach ($rows as $r) {
    $qid = (int) $r['question_id'];
    if (!isset($out[$qid])) {
      $out[$qid] = [
        "question_id" => $qid,
        "domain_name" => $r["domain_name"],
        "topic_name" => $r["topic_name"],
        "title" => $r["title"],
        "stem" => $r["stem"],
        "explanation" => $r["explanation"],
        "correct_label" => $r["correct_label"],
        "correct_rate" => (isset($r["total_count"]) && (int) $r["total_count"] > 0)
          ? round(((int) $r["correct_count"] / (int) $r["total_count"]) * 100, 1)
          : null,
        "answers_total" => isset($r["total_count"]) ? (int) $r["total_count"] : 0,
        "choices" => []
      ];
    }
    if ($r["choice_id"] !== null) {
      $out[$qid]["choices"][] = [
        "choice_id" => (int) $r["choice_id"],
        "choice_label" => $r["choice_label"],
        "choice_text" => $r["choice_text"],
      ];
    }
  }

  respond(200, array_values($out));
}

if ($route === '/questions' && $method === 'POST') {
  ensureSchema($pdo);

  $raw = file_get_contents('php://input');
  if ($raw === false) {
    respond(400, ["ok" => false, "error" => "Failed to read request body"]);
  }

  $payload = json_decode($raw, true);
  if (!is_array($payload)) {
    respond(400, ["ok" => false, "error" => "Invalid JSON. Expecting array of questions."]);
  }

  try {
    [$qCount, $cCount] = saveQuestions($pdo, $payload, false);
    respond(200, [
      "ok" => true,
      "questions_saved" => $qCount,
      "choices_saved" => $cCount,
    ]);
  } catch (Throwable $e) {
    respond(500, ["ok" => false, "error" => "Failed to save data", "detail" => $e->getMessage()]);
  }
}

if ($route === '/answers' && $method === 'POST') {
  ensureSchema($pdo);
  $uid = requireLogin();

  $raw = file_get_contents('php://input');
  if ($raw === false) {
    respond(400, ["ok" => false, "error" => "Failed to read request body"]);
  }
  $payload = json_decode($raw, true);
  if (!is_array($payload)) {
    respond(400, ["ok" => false, "error" => "Invalid JSON. Expecting array of answers."]);
  }

  $pdo->beginTransaction();
  try {
    $questionIds = [];
    foreach ($payload as $idx => $a) {
      validateAnswer($a, $idx);
      $questionIds[] = (int) $a['question_id'];
    }
    $questionIds = array_values(array_unique($questionIds));

    if (count($questionIds) === 0) {
      respond(400, ["ok" => false, "error" => "No question_id provided."]);
    }

    $in = implode(',', array_fill(0, count($questionIds), '?'));
    $stmtQ = $pdo->prepare("SELECT question_id, correct_label FROM questions WHERE question_id IN ($in)");
    $stmtQ->execute($questionIds);
    $correctMap = [];
    foreach ($stmtQ->fetchAll() as $row) {
      $correctMap[(int) $row['question_id']] = (string) $row['correct_label'];
    }

    foreach ($questionIds as $qid) {
      if (!isset($correctMap[$qid])) {
        throw new RuntimeException("question_id {$qid} not found");
      }
    }

    $stmtAnswer = $pdo->prepare("
      INSERT INTO answers (user_id, question_id, selected_label, is_correct, elapsed_ms)
      VALUES (:user_id, :question_id, :selected_label, :is_correct, :elapsed_ms)
    ");

    $count = 0;
    foreach ($payload as $idx => $a) {
      $qid = (int) $a['question_id'];
      $selected = (string) $a['selected_label'];
      $isCorrect = isset($correctMap[$qid]) && $selected === (string) $correctMap[$qid] ? 1 : 0;
      $elapsed = array_key_exists('elapsed_ms', $a) ? (is_null($a['elapsed_ms']) ? null : (int) $a['elapsed_ms']) : null;

      $stmtAnswer->execute([
        ':user_id' => $uid,
        ':question_id' => $qid,
        ':selected_label' => $selected,
        ':is_correct' => $isCorrect,
        ':elapsed_ms' => $elapsed,
      ]);
      $count++;
    }

    $pdo->commit();
    respond(200, ["ok" => true, "answers_saved" => $count]);
  } catch (Throwable $e) {
    $pdo->rollBack();
    respond(500, ["ok" => false, "error" => "Failed to save answers", "detail" => $e->getMessage()]);
  }
}

if ($route === '/generate' && $method === 'POST') {
  ensureSchema($pdo);
  $raw = file_get_contents('php://input');
  if ($raw === false) {
    respond(400, ["ok" => false, "error" => "Failed to read request body"]);
  }
  $req = json_decode($raw, true);
  if (!is_array($req)) {
    respond(400, ["ok" => false, "error" => "Invalid JSON. Expecting object."]);
  }

  $genre = trim((string) ($req['genre'] ?? ''));
  $problemType = trim((string) ($req['problem_type'] ?? ''));
  $goal = trim((string) ($req['goal'] ?? ''));
  $background = trim((string) ($req['background'] ?? ''));
  $outputFmt = trim((string) ($req['output_format'] ?? ''));
  $constraints = trim((string) ($req['constraints'] ?? ''));
  $notes = trim((string) ($req['notes'] ?? ''));
  $countReq = isset($req['count']) ? (int) $req['count'] : 5;
  $count = max(1, min($countReq, 50));

  $prompt = buildGeminiPrompt([
    'genre' => $genre,
    'problem_type' => $problemType,
    'goal' => $goal,
    'background' => $background,
    'output_format' => $outputFmt,
    'constraints' => $constraints,
    'notes' => $notes,
    'count' => $count,
  ]);

  $apiKey = getenv('GEMINI_API_KEY') ?: 'AIzaSyCXTPCRqiIcN71fOgI2nEzd6KeWKeOGMIM';
  if ($apiKey === '') {
    respond(500, ["ok" => false, "error" => "GEMINI_API_KEY is not set on server"]);
  }

  try {
    $llmJson = callGemini($prompt, $apiKey);
    $payload = normalizeQuestionsFromGemini($llmJson, $genre, $problemType);
    $payload = validateGeneratedQuestions($payload);

    if ($genre !== '' && $problemType !== '') {
      $stmtDel = $pdo->prepare("DELETE FROM question_choices WHERE question_id IN (SELECT question_id FROM questions WHERE domain_name = :domain AND topic_name = :topic)");
      $stmtDel->execute([':domain' => $genre, ':topic' => $problemType]);
      $stmtDelQ = $pdo->prepare("DELETE FROM questions WHERE domain_name = :domain AND topic_name = :topic");
      $stmtDelQ->execute([':domain' => $genre, ':topic' => $problemType]);
    }

    $createdBy = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    [$qCount, $cCount, $saved] = saveQuestions($pdo, $payload, true, true, $createdBy);
    respond(200, [
      "ok" => true,
      "questions_saved" => $qCount,
      "choices_saved" => $cCount,
      "questions" => $saved,
    ]);
  } catch (Throwable $e) {
    respond(500, ["ok" => false, "error" => "Failed to generate or save", "detail" => $e->getMessage()]);
  }
}

respond(404, ["ok" => false, "error" => "Not Found", "route" => $route, "path" => $path]);

function ensureSchema(PDO $pdo): void
{
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS questions (
      question_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      exam_name VARCHAR(255) NOT NULL DEFAULT 'study',
      domain_name VARCHAR(64) NOT NULL,
      topic_name VARCHAR(64) DEFAULT NULL,
      title VARCHAR(255) DEFAULT NULL,
      stem TEXT NOT NULL,
      explanation MEDIUMTEXT DEFAULT NULL,
      correct_label CHAR(1) NOT NULL DEFAULT 'A',
      difficulty TINYINT UNSIGNED DEFAULT NULL,
      is_active TINYINT(1) NOT NULL DEFAULT 1,
      created_by BIGINT UNSIGNED DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_domain (domain_name),
      KEY idx_topic (topic_name),
      KEY idx_active (is_active),
      KEY idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS question_choices (
      choice_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      question_id BIGINT UNSIGNED NOT NULL,
      choice_label CHAR(1) NOT NULL,
      choice_text TEXT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      CONSTRAINT fk_question_choices_question FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
      INDEX idx_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
  ");

  execIgnore($pdo, "ALTER TABLE questions ADD COLUMN exam_name VARCHAR(255) NOT NULL DEFAULT 'study'");
  execIgnore($pdo, "ALTER TABLE questions ADD COLUMN explanation MEDIUMTEXT DEFAULT NULL");
  execIgnore($pdo, "ALTER TABLE questions ADD COLUMN correct_label CHAR(1) NOT NULL DEFAULT 'A'");
  execIgnore($pdo, "ALTER TABLE questions ADD COLUMN difficulty TINYINT UNSIGNED DEFAULT NULL");
  execIgnore($pdo, "ALTER TABLE questions ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1");
  execIgnore($pdo, "ALTER TABLE questions ADD COLUMN created_by BIGINT UNSIGNED DEFAULT NULL");
  execIgnore($pdo, "CREATE INDEX idx_created_by ON questions(created_by)");
  execIgnore($pdo, "ALTER TABLE questions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

  execIgnore($pdo, "ALTER TABLE question_choices ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
  execIgnore($pdo, "ALTER TABLE question_choices ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
  execIgnore($pdo, "ALTER TABLE question_choices CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      user_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      username VARCHAR(64) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      avatar_seed VARCHAR(64) DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
  ");

  execIgnore($pdo, "ALTER TABLE users ADD COLUMN avatar_seed VARCHAR(64) DEFAULT NULL");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS answers (
      answer_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      question_id BIGINT UNSIGNED NOT NULL,
      selected_label CHAR(1) NOT NULL,
      is_correct TINYINT(1) NOT NULL,
      elapsed_ms INT UNSIGNED DEFAULT NULL,
      answered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      CONSTRAINT fk_answers_question FOREIGN KEY (question_id) REFERENCES questions(question_id) ON DELETE CASCADE,
      KEY idx_user (user_id),
      KEY idx_question (question_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
  ");
}


function execIgnore(PDO $pdo, string $sql): void
{
  try {
    $pdo->exec($sql);
  } catch (PDOException $e) {
    // ignore if column exists, etc.
  }
}

function validateQuestion(mixed $q, int $idx): void
{
  $required = ['domain_name', 'topic_name', 'title', 'stem', 'choices'];
  if (!is_array($q)) {
    respond(400, ["ok" => false, "error" => "Question at index {$idx} must be object"]);
  }
  foreach ($required as $key) {
    if (!array_key_exists($key, $q)) {
      respond(400, ["ok" => false, "error" => "Missing {$key} at question index {$idx}"]);
    }
  }
  if (!is_array($q['choices'])) {
    respond(400, ["ok" => false, "error" => "choices must be array at question index {$idx}"]);
  }
}

function validateChoice(mixed $c, int $qid, int $qIdx, int $cIdx): void
{
  $required = ['choice_label', 'choice_text'];
  if (!is_array($c)) {
    respond(400, ["ok" => false, "error" => "Choice {$cIdx} for question {$qid} must be object"]);
  }
  foreach ($required as $key) {
    if (!array_key_exists($key, $c)) {
      respond(400, ["ok" => false, "error" => "Missing {$key} in choice {$cIdx} for question {$qid} (question index {$qIdx})"]);
    }
  }
}

function validateAnswer(mixed $a, int $idx): void
{
  $required = ['question_id', 'selected_label'];
  if (!is_array($a)) {
    respond(400, ["ok" => false, "error" => "Answer at index {$idx} must be object"]);
  }
  foreach ($required as $key) {
    if (!array_key_exists($key, $a)) {
      respond(400, ["ok" => false, "error" => "Missing {$key} at answer index {$idx}"]);
    }
  }
  if (!is_string($a['selected_label']) || strlen($a['selected_label']) === 0) {
    respond(400, ["ok" => false, "error" => "selected_label must be non-empty string at answer index {$idx}"]);
  }
}

function saveQuestions(PDO $pdo, array $payload, bool $allowAutoId = true, bool $returnSaved = false, ?int $createdBy = null): array
{
  $pdo->beginTransaction();
  try {
    $stmtQuestion = $pdo->prepare("
      INSERT INTO questions (question_id, exam_name, domain_name, topic_name, title, stem, explanation, correct_label, is_active, created_by)
      VALUES (:question_id, :exam_name, :domain_name, :topic_name, :title, :stem, :explanation, :correct_label, 1, :created_by)
      ON DUPLICATE KEY UPDATE exam_name = VALUES(exam_name),
        domain_name = VALUES(domain_name),
        topic_name = VALUES(topic_name),
        title = VALUES(title),
        stem = VALUES(stem),
        explanation = VALUES(explanation),
        correct_label = VALUES(correct_label),
        is_active = VALUES(is_active)
    ");

    $stmtChoice = $pdo->prepare("
      INSERT INTO question_choices (choice_id, question_id, choice_label, choice_text)
      VALUES (:choice_id, :question_id, :choice_label, :choice_text)
      ON DUPLICATE KEY UPDATE question_id = VALUES(question_id),
        choice_label = VALUES(choice_label),
        choice_text = VALUES(choice_text)
    ");

    $questionsCount = 0;
    $choicesCount = 0;
    $saved = [];

    foreach ($payload as $idx => $q) {
      validateQuestion($q, $idx);

      $examName = isset($q['exam_name']) && $q['exam_name'] !== ''
        ? (string) $q['exam_name']
        : 'study';
      $correctLabel = isset($q['correct_label']) && $q['correct_label'] !== ''
        ? (string) $q['correct_label']
        : ((isset($q['choices'][0]['choice_label']) && $q['choices'][0]['choice_label'] !== '') ? (string) $q['choices'][0]['choice_label'] : 'A');

      $qidProvided = array_key_exists('question_id', $q) && $q['question_id'] !== '' ? (int) $q['question_id'] : null;
      $qidBind = $qidProvided;
      if (!$allowAutoId && $qidBind === null) {
        respond(400, ["ok" => false, "error" => "question_id is required at index {$idx}"]);
      }

      $stmtQuestion->execute([
        ':question_id' => $qidBind,
        ':exam_name' => $examName,
        ':domain_name' => (string) $q['domain_name'],
        ':topic_name' => (string) $q['topic_name'],
        ':title' => (string) $q['title'],
        ':stem' => (string) $q['stem'],
        ':explanation' => isset($q['explanation']) ? (string) $q['explanation'] : '',
        ':correct_label' => $correctLabel,
        ':created_by' => $createdBy,
      ]);
      $questionsCount++;

      $questionId = $qidProvided ?: (int) $pdo->lastInsertId();

      foreach ($q['choices'] as $cIdx => $c) {
        validateChoice($c, $questionId, $idx, $cIdx);

        $choiceIdProvided = array_key_exists('choice_id', $c) && $c['choice_id'] !== '' ? (int) $c['choice_id'] : null;

        $stmtChoice->execute([
          ':choice_id' => $choiceIdProvided,
          ':question_id' => $questionId,
          ':choice_label' => (string) $c['choice_label'],
          ':choice_text' => (string) $c['choice_text'],
        ]);
        $choicesCount++;
      }

      if ($returnSaved) {
        $saved[] = [
          'question_id' => $questionId,
          'domain_name' => (string) $q['domain_name'],
          'topic_name' => (string) $q['topic_name'],
          'title' => (string) $q['title'],
          'stem' => (string) $q['stem'],
          'explanation' => isset($q['explanation']) ? (string) $q['explanation'] : '',
          'correct_label' => $correctLabel,
          'choices' => $q['choices'],
        ];
      }
    }

    $pdo->commit();
    return $returnSaved ? [$questionsCount, $choicesCount, $saved] : [$questionsCount, $choicesCount];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    throw $e;
  }
}

function buildGeminiPrompt(array $ctx): string
{
  $genre = $ctx['genre'] ?: 'IT全般';
  $topic = $ctx['problem_type'] ?: '一般';
  $goal = $ctx['goal'] ?: '学習';
  $background = $ctx['background'] ?: '初学者';
  $constraints = $ctx['constraints'] ?: '四択・正答1つ・解説必須';
  $notes = $ctx['notes'] ?: '';
  $count = $ctx['count'] ?? 5;
  return <<<PROMPT
あなたはIT試験向けの問題作成者です。以下の要件に沿って四択問題を{$count}問生成してください。
- ジャンル: {$genre}
- 問題タイプ/トピック: {$topic}
- 目標: {$goal}
- 前提知識: {$background}
- 出力形式: 四択(A-D)、正答1つ、解説必須
- 制約/注意: {$constraints}
- メモ: {$notes}

出力は JSON 配列のみで返してください（Markdownなし、コードフェンスなし）。スキーマ:
[
  {
    "domain_name": "{$genre}",
    "topic_name": "{$topic}",
    "title": "短いタイトル",
    "stem": "問題文",
    "choices": [
      {"choice_label": "A", "choice_text": "..."},
      {"choice_label": "B", "choice_text": "..."},
      {"choice_label": "C", "choice_text": "..."},
      {"choice_label": "D", "choice_text": "..."}
    ],
    "correct_label": "A|B|C|D",
    "explanation": "なぜそれが正解か。誤答の簡潔な指摘も含めると良い"
  }
]
PROMPT;
}

function callGemini(string $prompt, string $apiKey): string
{
  $apiVersion = getenv('GEMINI_API_VERSION') ?: 'v1';
  $model = getenv('GEMINI_MODEL') ?: 'gemini-2.5-flash';
  $timeoutSec = max(10, (int) (getenv('GEMINI_TIMEOUT_SEC') ?: 90));
  $connectTimeoutSec = max(3, (int) (getenv('GEMINI_CONNECT_TIMEOUT_SEC') ?: 10));
  $retryCount = max(0, min(3, (int) (getenv('GEMINI_RETRY_COUNT') ?: 1)));
  if (str_starts_with($model, 'models/')) {
    $model = substr($model, strlen('models/'));
  }
  $url = "https://generativelanguage.googleapis.com/{$apiVersion}/models/{$model}:generateContent?key=" . urlencode($apiKey);
  $payload = json_encode([
    "contents" => [
      ["parts" => [["text" => $prompt]]]
    ],
    "generationConfig" => [
      "temperature" => 0.5,
    ],
  ], JSON_UNESCAPED_UNICODE);

  $attempts = 1 + $retryCount;
  $lastErr = '';
  for ($attempt = 1; $attempt <= $attempts; $attempt++) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_CONNECTTIMEOUT => $connectTimeoutSec,
      CURLOPT_TIMEOUT => $timeoutSec,
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
      $lastErr = curl_error($ch);
      curl_close($ch);
      if ($attempt < $attempts) {
        usleep(500000);
        continue;
      }
      throw new RuntimeException("Gemini request failed: {$lastErr}");
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status < 200 || $status >= 300) {
      $lastErr = "Gemini HTTP {$status}: {$res}";
      if ($attempt < $attempts && ($status === 429 || $status >= 500)) {
        usleep(500000);
        continue;
      }
      throw new RuntimeException($lastErr);
    }

    $data = json_decode($res, true);
    if (!is_array($data)) {
      throw new RuntimeException("Gemini invalid JSON response");
    }
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if (!is_string($text) || $text === '') {
      throw new RuntimeException("Gemini returned empty text");
    }
    return $text;
  }

  throw new RuntimeException("Gemini request failed: {$lastErr}");
}

function normalizeQuestionsFromGemini(string $text, string $genre, string $topic): array
{
  $clean = trim($text);
  if (str_starts_with($clean, '```')) {
    $clean = preg_replace('/^```[a-zA-Z0-9]*\\s*/', '', $clean);
    $clean = preg_replace('/```\\s*$/', '', $clean);
    $clean = trim((string) $clean);
  }
  $json = json_decode($clean, true);
  if (!is_array($json)) {
    throw new RuntimeException("Gemini response is not valid JSON array");
  }
  $labeled = array_map(function ($q) use ($genre, $topic) {
    if (!isset($q['domain_name']) || $q['domain_name'] === '') {
      $q['domain_name'] = $genre ?: '未指定';
    }
    if (!isset($q['topic_name']) || $q['topic_name'] === '') {
      $q['topic_name'] = $topic ?: '未指定';
    }
    if (!isset($q['explanation']) || $q['explanation'] === null) {
      $q['explanation'] = '';
    }
    return $q;
  }, $json);

  // ラベルの偏りを機械的に解消: choicesをシャッフルし A-D を付け直す
  // 正解テキストは維持したまま、正解ラベルを再計算し、解説に新ラベルを明示
  $balanced = [];
  foreach ($labeled as $qIdx => $q) {
    $choices = $q['choices'] ?? [];
    if (!is_array($choices) || count($choices) === 0) {
      $balanced[] = $q;
      continue;
    }

    // もとの正解テキストを取得
    $origCorrectLabel = $q['correct_label'] ?? '';
    $origCorrectText = null;
    foreach ($choices as $c) {
      if (isset($c['choice_label']) && $c['choice_label'] === $origCorrectLabel) {
        $origCorrectText = $c['choice_text'] ?? null;
        break;
      }
    }

    // シャッフルして A-D を振り直し
    shuffle($choices);
    $letters = ['A', 'B', 'C', 'D', 'E', 'F']; // 予備で6択まで
    $newChoices = [];
    $newCorrect = $letters[0];
    foreach ($choices as $idx => $c) {
      $label = $letters[$idx] ?? chr(65 + $idx);
      $newChoices[] = [
        'choice_label' => $label,
        'choice_text' => $c['choice_text'] ?? '',
      ];
      if ($origCorrectText !== null && isset($c['choice_text']) && $c['choice_text'] === $origCorrectText) {
        $newCorrect = $label;
      }
    }

    // もとの正解テキストが見つからない場合はラベルをローテーション
    if ($origCorrectText === null) {
      $newCorrect = $letters[$qIdx % min(count($letters), count($newChoices))];
    }

    $q['choices'] = $newChoices;
    $q['correct_label'] = $newCorrect;
    $q['explanation'] = "【正解: {$newCorrect}】 " . (isset($q['explanation']) && $q['explanation'] !== null ? (string) $q['explanation'] : '');
    $balanced[] = $q;
  }

  // 解説に最終的な正解ラベルを付与
  foreach ($balanced as &$bq) {
    $expl = isset($bq['explanation']) ? (string) $bq['explanation'] : '';
    // 既に付いている先頭の【正解: ...】を除去して二重付与を防ぐ
    $expl = preg_replace('/^【正解:[^】]+】\s*/u', '', $expl);
    $bq['explanation'] = "【正解: {$bq['correct_label']}】 " . $expl;
  }
  unset($bq);

  return $balanced;
}

function validateGeneratedQuestions(array $qs): array
{
  $out = [];
  foreach ($qs as $q) {
    if (!isset($q['stem']) || trim((string) $q['stem']) === '')
      continue;
    if (!isset($q['choices']) || count($q['choices']) < 4)
      continue;
    $labels = [];
    $texts = [];
    $valid = true;
    foreach ($q['choices'] as $c) {
      $lbl = $c['choice_label'] ?? '';
      $txt = trim((string) ($c['choice_text'] ?? ''));
      if ($lbl === '' || $txt === '') {
        $valid = false;
        break;
      }
      if (isset($texts[$txt])) {
        $valid = false;
        break;
      }
      $texts[$txt] = true;
      $labels[] = $lbl;
    }
    if (!$valid)
      continue;
    $q['explanation'] = isset($q['explanation']) ? (string) $q['explanation'] : '';
    if (mb_strlen($q['explanation']) < 10)
      continue;
    $out[] = $q;
  }
  if (count($out) === 0) {
    throw new RuntimeException("Generated questions did not pass quality checks (empty/duplicate/too short).");
  }
  return $out;
}

function requireLogin(): int
{
  if (!isset($_SESSION['user_id'])) {
    respond(401, ["ok" => false, "error" => "Login required"]);
  }
  return (int) $_SESSION['user_id'];
}

function respond(int $status, array $body): void
{
  http_response_code($status);
  echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}
