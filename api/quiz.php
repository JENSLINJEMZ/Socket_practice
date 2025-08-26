<?php
require_once '../config/db_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Check authentication for all endpoints
checkAuth();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'daily':
                    getDailyQuiz();
                    break;
                case 'history':
                    getQuizHistory();
                    break;
                case 'stats':
                    getUserStats();
                    break;
                case 'leaderboard':
                    getLeaderboard();
                    break;
                case 'achievements':
                    getUserAchievements();
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        case 'POST':
            switch ($action) {
                case 'submit':
                    submitAnswer();
                    break;
                case 'hint':
                    useHint();
                    break;
                case 'create':
                    checkAdmin(); // Only admins can create quizzes
                    createQuiz();
                    break;
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Invalid request method');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function getDailyQuiz() {
    global $pdo, $user_id;
    
    try {
        // Get today's quiz
        $stmt = $pdo->prepare("
            SELECT q.*, 
                   (SELECT COUNT(*) FROM user_quiz_attempts WHERE question_id = q.id) as participants,
                   (SELECT selected_answer FROM user_quiz_attempts WHERE user_id = ? AND question_id = q.id) as user_answer,
                   (SELECT is_correct FROM user_quiz_attempts WHERE user_id = ? AND question_id = q.id) as user_correct,
                   (SELECT hint_used FROM user_quiz_attempts WHERE user_id = ? AND question_id = q.id) as hint_used
            FROM quiz_questions q
            WHERE q.date = CURDATE()
            LIMIT 1
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $quiz = $stmt->fetch();
        
        if (!$quiz) {
            echo json_encode(['success' => false, 'error' => 'No quiz available for today']);
            return;
        }
        
        // Get options
        $stmt = $pdo->prepare("SELECT option_letter, option_text FROM quiz_options WHERE question_id = ? ORDER BY option_letter");
        $stmt->execute([$quiz['id']]);
        $options = $stmt->fetchAll();
        
        // Get topics
        $stmt = $pdo->prepare("SELECT topic FROM quiz_topics WHERE question_id = ?");
        $stmt->execute([$quiz['id']]);
        $topics = array_column($stmt->fetchAll(), 'topic');
        
        // Calculate time until next quiz
        $now = new DateTime();
        $tomorrow = new DateTime('tomorrow');
        $diff = $tomorrow->diff($now);
        
        // Prepare response
        $response = [
            'success' => true,
            'quiz' => [
                'id' => $quiz['id'],
                'date' => $quiz['date'],
                'category' => $quiz['category'],
                'question' => $quiz['question'],
                'code_snippet' => $quiz['code_snippet'],
                'options' => $options,
                'points' => $quiz['points'],
                'difficulty' => $quiz['difficulty'],
                'participants' => $quiz['participants'],
                'topics' => $topics,
                'completed' => !is_null($quiz['user_answer']),
                'time_remaining' => [
                    'hours' => $diff->h,
                    'minutes' => $diff->i,
                    'seconds' => $diff->s
                ]
            ]
        ];
        
        // Include answer info if already completed
        if ($response['quiz']['completed']) {
            $response['quiz']['user_answer'] = $quiz['user_answer'];
            $response['quiz']['correct_answer'] = $quiz['correct_answer'];
            $response['quiz']['explanation'] = $quiz['explanation'];
            $response['quiz']['is_correct'] = (bool)$quiz['user_correct'];
            $response['quiz']['hint_used'] = (bool)$quiz['hint_used'];
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

function submitAnswer() {
    global $pdo, $user_id;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $question_id = $data['question_id'] ?? 0;
    $selected_answer = $data['selected_answer'] ?? '';
    $time_taken = $data['time_taken'] ?? 0; // Time taken in seconds
    
    if (!$question_id || !$selected_answer) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if already answered
        $stmt = $pdo->prepare("SELECT id FROM user_quiz_attempts WHERE user_id = ? AND question_id = ?");
        $stmt->execute([$user_id, $question_id]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Quiz already completed']);
            return;
        }
        
        // Get correct answer and points
        $stmt = $pdo->prepare("SELECT correct_answer, points, explanation FROM quiz_questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $quiz = $stmt->fetch();
        
        if (!$quiz) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Quiz not found']);
            return;
        }
        
        $is_correct = ($selected_answer === $quiz['correct_answer']);
        $points_earned = $is_correct ? $quiz['points'] : 0;
        
        // Check if hint was used
        $hint_used = $_SESSION['hint_used_' . $question_id] ?? false;
        if ($hint_used && $is_correct) {
            $points_earned = max(0, $points_earned - 10);
        }
        
        // Bonus for quick answer (under 30 seconds)
        $speed_bonus = false;
        if ($is_correct && $time_taken < 30) {
            $points_earned += 10;
            $speed_bonus = true;
        }
        
        // Insert attempt
        $stmt = $pdo->prepare("
            INSERT INTO user_quiz_attempts 
            (user_id, question_id, selected_answer, is_correct, points_earned, hint_used) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $question_id, $selected_answer, $is_correct, $points_earned, $hint_used]);
        
        // Update user stats
        updateUserStats($user_id, $is_correct, $points_earned);
        
        // Check for new achievements
        $new_achievements = checkAchievements($user_id, $speed_bonus);
        
        $pdo->commit();
        
        // Get topics for response
        $stmt = $pdo->prepare("SELECT topic FROM quiz_topics WHERE question_id = ?");
        $stmt->execute([$question_id]);
        $topics = array_column($stmt->fetchAll(), 'topic');
        
        // Clean up hint session
        unset($_SESSION['hint_used_' . $question_id]);
        
        echo json_encode([
            'success' => true,
            'is_correct' => $is_correct,
            'correct_answer' => $quiz['correct_answer'],
            'explanation' => $quiz['explanation'],
            'points_earned' => $points_earned,
            'topics' => $topics,
            'new_achievements' => $new_achievements,
            'speed_bonus' => $speed_bonus
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

function updateUserStats($user_id, $is_correct, $points_earned) {
    global $pdo;
    
    // Check if user has entry in leaderboard
    $stmt = $pdo->prepare("SELECT * FROM quiz_leaderboard WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    if (!$stats) {
        // Create new entry
        $stmt = $pdo->prepare("
            INSERT INTO quiz_leaderboard 
            (user_id, total_points, total_correct, total_attempted, current_streak, longest_streak, last_attempt_date) 
            VALUES (?, ?, ?, 1, 1, 1, CURDATE())
        ");
        $stmt->execute([$user_id, $points_earned, $is_correct ? 1 : 0]);
    } else {
        // Update existing stats
        $last_date = new DateTime($stats['last_attempt_date']);
        $today = new DateTime();
        $diff = $today->diff($last_date)->days;
        
        // Calculate streak
        $new_streak = 1;
        if ($diff == 1) {
            $new_streak = $stats['current_streak'] + 1;
        } elseif ($diff == 0) {
            $new_streak = $stats['current_streak'];
        }
        
        $longest_streak = max($stats['longest_streak'], $new_streak);
        
        $stmt = $pdo->prepare("
            UPDATE quiz_leaderboard 
            SET total_points = total_points + ?,
                total_correct = total_correct + ?,
                total_attempted = total_attempted + 1,
                current_streak = ?,
                longest_streak = ?,
                last_attempt_date = CURDATE()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $points_earned,
            $is_correct ? 1 : 0,
            $new_streak,
            $longest_streak,
            $user_id
        ]);
    }
    
    // Update rankings
    updateRankings();
}

function updateRankings() {
    global $pdo;
    
    // Update rank positions based on total points
    $stmt = $pdo->prepare("
        SET @rank = 0;
        UPDATE quiz_leaderboard 
        SET rank_position = (@rank := @rank + 1)
        ORDER BY total_points DESC, total_correct DESC, total_attempted ASC
    ");
    $pdo->exec("SET @rank = 0");
    $pdo->exec("UPDATE quiz_leaderboard SET rank_position = (@rank := @rank + 1) ORDER BY total_points DESC, total_correct DESC, total_attempted ASC");
}

function checkAchievements($user_id, $speed_bonus = false) {
    global $pdo;
    
    $new_achievements = [];
    
    // Get current user stats
    $stmt = $pdo->prepare("SELECT * FROM quiz_leaderboard WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    if (!$stats) return $new_achievements;
    
    // Speed Demon achievement
    if ($speed_bonus) {
        $stmt = $pdo->prepare("SELECT id FROM quiz_achievements WHERE name = 'Speed Demon'");
        $stmt->execute();
        $achievement = $stmt->fetch();
        
        if ($achievement) {
            $stmt = $pdo->prepare("SELECT id FROM user_quiz_achievements WHERE user_id = ? AND achievement_id = ?");
            $stmt->execute([$user_id, $achievement['id']]);
            
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO user_quiz_achievements (user_id, achievement_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $achievement['id']]);
                
                $new_achievements[] = [
                    'name' => 'Speed Demon',
                    'description' => 'Answered correctly in under 30 seconds!',
                    'icon' => '⚡'
                ];
            }
        }
    }
    
    // Check other achievements
    $stmt = $pdo->prepare("
        SELECT * FROM quiz_achievements 
        WHERE id NOT IN (
            SELECT achievement_id FROM user_quiz_achievements WHERE user_id = ?
        )
    ");
    $stmt->execute([$user_id]);
    $achievements = $stmt->fetchAll();
    
    foreach ($achievements as $achievement) {
        $earned = false;
        
        if ($achievement['points_required'] && $stats['total_points'] >= $achievement['points_required']) {
            $earned = true;
        }
        if ($achievement['correct_required'] && $stats['total_correct'] >= $achievement['correct_required']) {
            $earned = true;
        }
        if ($achievement['streak_required'] && $stats['current_streak'] >= $achievement['streak_required']) {
            $earned = true;
        }
        
        if ($earned) {
            $stmt = $pdo->prepare("INSERT INTO user_quiz_achievements (user_id, achievement_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $achievement['id']]);
            
            $new_achievements[] = [
                'name' => $achievement['name'],
                'description' => $achievement['description'],
                'icon' => $achievement['icon']
            ];
        }
    }
    
    return $new_achievements;
}

function getUserStats() {
    global $pdo, $user_id;
    
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, u.username, u.avatar,
                   (SELECT COUNT(*) FROM user_quiz_achievements WHERE user_id = l.user_id) as achievements_count
            FROM quiz_leaderboard l
            JOIN users u ON l.user_id = u.id
            WHERE l.user_id = ?
        ");
        $stmt->execute([$user_id]);
        $stats = $stmt->fetch();
        
        if (!$stats) {
            $stats = [
                'total_points' => 0,
                'total_correct' => 0,
                'total_attempted' => 0,
                'current_streak' => 0,
                'longest_streak' => 0,
                'rank_position' => null,
                'achievements_count' => 0,
                'accuracy' => 0
            ];
        } else {
            $stats['accuracy'] = $stats['total_attempted'] > 0 
                ? round(($stats['total_correct'] / $stats['total_attempted']) * 100) 
                : 0;
        }
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

function getQuizHistory() {
    global $pdo, $user_id;
    
    $filter = $_GET['filter'] ?? 'all';
    $limit = intval($_GET['limit'] ?? 10);
    $offset = intval($_GET['offset'] ?? 0);
    
    try {
        $query = "
            SELECT q.*, 
                   a.selected_answer as user_answer,
                   a.is_correct,
                   a.points_earned,
                   a.attempt_date
            FROM quiz_questions q
            LEFT JOIN user_quiz_attempts a ON q.id = a.question_id AND a.user_id = ?
            WHERE q.date < CURDATE()
        ";
        
        $params = [$user_id];
        
        switch ($filter) {
            case 'completed':
                $query .= " AND a.id IS NOT NULL";
                break;
            case 'missed':
                $query .= " AND a.id IS NULL";
                break;
            case 'perfect':
                $query .= " AND a.is_correct = 1";
                break;
        }
        
        $query .= " ORDER BY q.date DESC LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $history = $stmt->fetchAll();
        
        foreach ($history as &$quiz) {
            // Get options
            $stmt = $pdo->prepare("SELECT option_letter, option_text FROM quiz_options WHERE question_id = ? ORDER BY option_letter");
            $stmt->execute([$quiz['id']]);
            $quiz['options'] = $stmt->fetchAll();
            
            // Get topics
            $stmt = $pdo->prepare("SELECT topic FROM quiz_topics WHERE question_id = ?");
            $stmt->execute([$quiz['id']]);
            $quiz['topics'] = array_column($stmt->fetchAll(), 'topic');
            
            $quiz['completed'] = !is_null($quiz['user_answer']);
            $quiz['missed'] = is_null($quiz['user_answer']);
        }
        
        echo json_encode(['success' => true, 'history' => $history]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

function getLeaderboard() {
    global $pdo, $user_id;
    
    $limit = intval($_GET['limit'] ?? 10);
    $type = $_GET['type'] ?? 'all'; // all, daily, weekly, monthly
    
    try {
        $query = "
            SELECT l.*, u.username, u.avatar,
                   (SELECT COUNT(*) FROM user_quiz_achievements WHERE user_id = l.user_id) as achievements_count,
                   CASE WHEN l.user_id = ? THEN 1 ELSE 0 END as is_current_user
            FROM quiz_leaderboard l
            JOIN users u ON l.user_id = u.id
        ";
        
        $params = [$user_id];
        
        switch ($type) {
            case 'daily':
                $query .= " WHERE l.last_attempt_date = CURDATE()";
                break;
            case 'weekly':
                $query .= " WHERE l.last_attempt_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'monthly':
                $query .= " WHERE l.last_attempt_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                break;
        }
        
        $query .= " ORDER BY l.total_points DESC, l.total_correct DESC LIMIT ?";
        
        $stmt = $pdo->prepare($query);
        foreach ($params as $i => $param) {
            $stmt->bindValue($i + 1, $param, is_int($param) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $leaderboard = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'leaderboard' => $leaderboard]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

function getUserAchievements() {
    global $pdo, $user_id;
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.*, ua.earned_date
            FROM quiz_achievements a
            LEFT JOIN user_quiz_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
            ORDER BY ua.earned_date DESC NULLS LAST
        ");
        $stmt->execute([$user_id]);
        $achievements = $stmt->fetchAll();
        
        $earned = [];
        $available = [];
        
        foreach ($achievements as $achievement) {
            if ($achievement['earned_date']) {
                $earned[] = $achievement;
            } else {
                $available[] = $achievement;
            }
        }
        
        echo json_encode([
            'success' => true,
            'earned' => $earned,
            'available' => $available
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

function useHint() {
    global $pdo, $user_id;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $question_id = $data['question_id'] ?? 0;
    
    if (!$question_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid input']);
        return;
    }
    
    try {
        // Check if already answered
        $stmt = $pdo->prepare("SELECT id FROM user_quiz_attempts WHERE user_id = ? AND question_id = ?");
        $stmt->execute([$user_id, $question_id]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Quiz already completed']);
            return;
        }
        
        // Check if hint already used
        if (isset($_SESSION['hint_used_' . $question_id]) && $_SESSION['hint_used_' . $question_id]) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Hint already used']);
            return;
        }
        
        // Get hint
        $stmt = $pdo->prepare("SELECT hint FROM quiz_questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $quiz = $stmt->fetch();
        
        if (!$quiz || !$quiz['hint']) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No hint available']);
            return;
        }
        
        // Mark hint as used
        $_SESSION['hint_used_' . $question_id] = true;
        
        echo json_encode([
            'success' => true,
            'hint' => $quiz['hint'],
            'points_deducted' => 10
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}

function createQuiz() {
    global $pdo, $user_id;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['date', 'category', 'question', 'options', 'correct_answer', 'explanation'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Validate date format
    if (!validateDate($data['date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid date format. Use YYYY-MM-DD']);
        return;
    }
    
    // Check if date is in the future or today
    $quizDate = new DateTime($data['date']);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($quizDate < $today) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Cannot create quiz for past dates']);
        return;
    }
    
    // Validate options
    if (!is_array($data['options']) || count($data['options']) !== 4) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Must provide exactly 4 options']);
        return;
    }
    
    // Validate correct answer
    if (!in_array($data['correct_answer'], ['A', 'B', 'C', 'D'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Correct answer must be A, B, C, or D']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if quiz already exists for this date
        $stmt = $pdo->prepare("SELECT id FROM quiz_questions WHERE date = ?");
        $stmt->execute([$data['date']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Quiz already exists for this date']);
            return;
        }
        
        // Insert quiz question
        $stmt = $pdo->prepare("
            INSERT INTO quiz_questions 
            (date, category, question, code_snippet, correct_answer, explanation, hint, points, difficulty, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['date'],
            sanitizeInput($data['category']),
            sanitizeInput($data['question']),
            $data['code_snippet'] ?? null,
            $data['correct_answer'],
            sanitizeInput($data['explanation']),
            sanitizeInput($data['hint'] ?? ''),
            intval($data['points'] ?? 50),
            $data['difficulty'] ?? 'medium',
            $user_id
        ]);
        
        $questionId = $pdo->lastInsertId();
        
        // Insert options
        $letters = ['A', 'B', 'C', 'D'];
        foreach ($data['options'] as $i => $option) {
            $stmt = $pdo->prepare("
                INSERT INTO quiz_options (question_id, option_letter, option_text) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$questionId, $letters[$i], sanitizeInput($option)]);
        }
        
        // Insert topics if provided
        if (!empty($data['topics']) && is_array($data['topics'])) {
            foreach ($data['topics'] as $topic) {
                $stmt = $pdo->prepare("
                    INSERT INTO quiz_topics (question_id, topic) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$questionId, sanitizeInput($topic)]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Quiz created successfully',
            'quiz_id' => $questionId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
    }
}
?>
