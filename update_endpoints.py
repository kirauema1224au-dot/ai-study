
import os

file_path = r'c:\Users\uamak\OneDrive\Desktop\docker\nyo\api\index.php'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

target_me = """    respond(200, [
      "ok" => true,
      "logged_in" => true,
      "user" => [
        "id" => $uid,
        "name" => $_SESSION['username'],
        "created_questions_count" => (int) $count
      ]
    ]);"""

replacement_me = """    // Get user details
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
    ]);"""

if target_me in content:
    content = content.replace(target_me, replacement_me)
    print("/me endpoint updated.")
else:
    print("/me target not found.")

target_login = """"user" => ["id" => $user['user_id'], "name" => $user['username']]]);"""
replacement_login = """"user" => ["id" => $user['user_id'], "name" => $user['username'], "avatar_seed" => $user['avatar_seed'] ?? null]]);"""

if target_login in content:
    content = content.replace(target_login, replacement_login)
    print("/login endpoint updated.")
else:
    print("/login target not found.")

# Add /profile endpoint
target_profile_hook = """  } else {
    respond(200, ["ok" => true, "logged_in" => false]);
  }
}

// ... (DB Connection Setup"""

replacement_profile = """  } else {
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

// ... (DB Connection Setup"""

# Note: The target_profile_hook in previous attempts included a comment that might vary.
# Let's search for a more reliable anchor.
# It seems the previous hook ended with `// ... (DB Connection Setup maintained here ...` which is not in the file.
# The file structure has:
# ...
#   } else {
#     respond(200, ["ok" => true, "logged_in" => false]);
#   }
# }
# 
# // ... (DB Connection Setup maintained here in original file, skipping to next change) ...
# 
# 
# 
# 
# if ($route === '/' || $route === '/health') {
# ...

# Let's try to find the end of the /me block and insert before /health or just after /me.

target_after_me = """  } else {
    respond(200, ["ok" => true, "logged_in" => false]);
  }
}"""

replacement_after_me = """  } else {
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
}"""

if target_after_me in content:
    content = content.replace(target_after_me, replacement_after_me)
    print("/profile endpoint added.")
else:
    print("/profile insertion point not found.")

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
