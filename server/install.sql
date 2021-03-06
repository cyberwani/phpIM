USE phpIM;

CREATE TABLE IF NOT EXISTS Message (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    #user CHAR(1),
    conversation_id CHAR(65),
    username VARCHAR(32),
    message VARCHAR(4096),
    time_stamp DATETIME,
    INDEX(conversation_id),
    INDEX(time_stamp)
);

CREATE TABLE IF NOT EXISTS Conversation (
    id CHAR(65) PRIMARY KEY,
    manager_id INT UNSIGNED,
    username VARCHAR(32),
    last_update_check DATETIME,
    last_id INT UNSIGNED,
    INDEX(manager_id),
    INDEX(username),
    INDEX(last_update_check),
    INDEX(last_id)
);

CREATE TABLE IF NOT EXISTS Manager (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) UNIQUE,
    password CHAR(128),
    access_level INT UNSIGNED,
    failed_attempts INT,
    INDEX(username)
);
