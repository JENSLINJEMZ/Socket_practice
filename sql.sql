-- Quiz questions table
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE UNIQUE NOT NULL,
    category VARCHAR(100) NOT NULL,
    question TEXT NOT NULL,
    code_snippet TEXT,
    correct_answer CHAR(1) NOT NULL,
    explanation TEXT NOT NULL,
    hint TEXT,
    points INT DEFAULT 50,
    difficulty ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_date (date),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz options table
CREATE TABLE IF NOT EXISTS quiz_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    option_letter CHAR(1) NOT NULL,
    option_text TEXT NOT NULL,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_question_option (question_id, option_letter)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz topics table
CREATE TABLE IF NOT EXISTS quiz_topics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    topic VARCHAR(50) NOT NULL,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    INDEX idx_topic (topic)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User quiz attempts table
CREATE TABLE IF NOT EXISTS user_quiz_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer CHAR(1) NOT NULL,
    is_correct BOOLEAN NOT NULL,
    points_earned INT DEFAULT 0,
    hint_used BOOLEAN DEFAULT FALSE,
    attempt_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_quiz (user_id, question_id),
    INDEX idx_user_date (user_id, attempt_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz leaderboard table
CREATE TABLE IF NOT EXISTS quiz_leaderboard (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    total_points INT DEFAULT 0,
    total_correct INT DEFAULT 0,
    total_attempted INT DEFAULT 0,
    current_streak INT DEFAULT 0,
    longest_streak INT DEFAULT 0,
    last_attempt_date DATE,
    achievements JSON,
    rank_position INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user (user_id),
    INDEX idx_points (total_points DESC),
    INDEX idx_rank (rank_position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Quiz achievements table
CREATE TABLE IF NOT EXISTS quiz_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(10),
    points_required INT,
    correct_required INT,
    streak_required INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User achievements table
CREATE TABLE IF NOT EXISTS user_quiz_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    earned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES quiz_achievements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_achievement (user_id, achievement_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample achievements
INSERT INTO quiz_achievements (name, description, icon, points_required, correct_required, streak_required) VALUES
('Week Warrior', 'Completed quizzes for 7 days straight!', '🗓️', NULL, NULL, 7),
('Monthly Master', 'Completed quizzes for 30 days straight!', '📅', NULL, NULL, 30),
('Quiz Starter', '10 correct answers!', '🌟', NULL, 10, NULL),
('Quiz Expert', '50 correct answers!', '⭐', NULL, 50, NULL),
('Quiz Master', '100 correct answers!', '🏆', NULL, 100, NULL),
('Point Collector', 'Earned 500 quiz points!', '💎', 500, NULL, NULL),
('Point Master', 'Earned 2000 quiz points!', '💰', 2000, NULL, NULL),
('Perfect Week', '7 correct answers in a row!', '🎯', NULL, NULL, NULL),
('Speed Demon', 'Answered correctly in under 30 seconds!', '⚡', NULL, NULL, NULL);

-- Insert sample quiz for today (optional)
INSERT INTO quiz_questions (date, category, question, correct_answer, explanation, hint, points, difficulty) VALUES
(CURDATE(), 'Network Security', 'Which of the following best describes a SQL injection attack where the attacker uses time delays to infer information about the database?', 'B', 'Time-based SQL injection is a type of blind SQL injection technique where the attacker uses database commands that cause time delays. By measuring the response time, attackers can infer whether their injected conditions are true or false, allowing them to extract data bit by bit.', 'Think about SQL injection techniques that rely on timing rather than direct output.', 50, 'medium');

-- Insert options for the sample quiz
SET @last_quiz_id = LAST_INSERT_ID();
INSERT INTO quiz_options (question_id, option_letter, option_text) VALUES
(@last_quiz_id, 'A', 'Blind SQL injection'),
(@last_quiz_id, 'B', 'Time-based SQL injection'),
(@last_quiz_id, 'C', 'Union-based SQL injection'),
(@last_quiz_id, 'D', 'Error-based SQL injection');

-- Insert topics for the sample quiz
INSERT INTO quiz_topics (question_id, topic) VALUES
(@last_quiz_id, 'SQLInjection'),
(@last_quiz_id, 'WebSecurity'),
(@last_quiz_id, 'DatabaseSecurity'),
(@last_quiz_id, 'OWASP');
