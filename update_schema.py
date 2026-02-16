
import os

file_path = r'c:\Users\uamak\OneDrive\Desktop\docker\nyo\api\index.php'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target = """      user_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      username VARCHAR(64) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
  ");"""

replacement = """      user_id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
      username VARCHAR(64) NOT NULL UNIQUE,
      password_hash VARCHAR(255) NOT NULL,
      avatar_seed VARCHAR(64) DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;
  ");

  execIgnore($pdo, "ALTER TABLE users ADD COLUMN avatar_seed VARCHAR(64) DEFAULT NULL");"""

if target in content:
    new_content = content.replace(target, replacement)
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Schema updated successfully.")
else:
    print("Target string not found.")
