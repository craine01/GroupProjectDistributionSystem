<?php
// api.php - backend for "Let's Team Up!" (MySQL: group_project_db)
header('Content-Type: application/json; charset=utf-8');

const DB_HOST = 'localhost';
const DB_NAME = 'group_project_db';
const DB_USER = 'root';   // change for your server
const DB_PASS = '';       // change for your server

function out($arr, $code = 200) { http_response_code($code); echo json_encode($arr); exit; }
function fail($msg, $code = 400) { out(['success' => false, 'message' => $msg], $code); }

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) { fail('Database connection failed.', 500); }

$action = $_GET['action'] ?? '';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$code = trim($in['project_code'] ?? ($_GET['project_code'] ?? ''));

function q($sql, $args = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($args); return $s; }
function project($code) {
    $p = q('SELECT * FROM projects WHERE project_code = ?', [$code])->fetch();
    if (!$p) fail('Invalid Group Code.', 404);
    return $p;
}

try {
    switch ($action) {

    case 'get_data': {
        $p = project($code);
        $name = trim($_GET['member_name'] ?? '');
        if ($name !== '') q('INSERT IGNORE INTO members (project_id, name, role) VALUES (?, ?, "Member")', [$p['id'], $name]); // zero-friction join
        out([
            'success' => true,
            'project' => ['title' => $p['title'], 'project_code' => $p['project_code']],
            'members' => q('SELECT name, role FROM members WHERE project_id = ? ORDER BY id', [$p['id']])->fetchAll(),
            'deadlines' => q('SELECT deadline_date AS date, title FROM deadlines WHERE project_id = ? ORDER BY id', [$p['id']])->fetchAll(),
            'goals' => q('SELECT title, description FROM goals WHERE project_id = ? ORDER BY id', [$p['id']])->fetchAll(),
            'group_deliverables' => q('SELECT id, title, is_verified FROM group_deliverables WHERE project_id = ? ORDER BY id', [$p['id']])->fetchAll(),
            'sub_tasks' => q('SELECT t.id, t.deliverable_id, t.assignee, t.title, t.status FROM sub_tasks t JOIN group_deliverables d ON d.id = t.deliverable_id WHERE d.project_id = ? ORDER BY t.id', [$p['id']])->fetchAll(),
            'weekly_logs' => q('SELECT member_name AS member, week, commentary, proof_link AS link FROM weekly_logs WHERE project_id = ? ORDER BY id', [$p['id']])->fetchAll(),
        ]);
    }

    case 'create_project': {
        $title = trim($in['title'] ?? ''); $pin = trim($in['admin_pin'] ?? '');
        if (!$title || !$code || !$pin) fail('Complete all fields.');
        if (q('SELECT id FROM projects WHERE project_code = ?', [$code])->fetch()) fail('That Group Code is already taken.', 409);
        q('INSERT INTO projects (project_code, title, admin_pin_hash) VALUES (?, ?, ?)', [$code, $title, password_hash($pin, PASSWORD_DEFAULT)]);
        out(['success' => true]);
    }

    case 'verify_admin': {
        $p = project($code);
        if (!password_verify($in['admin_pin'] ?? '', $p['admin_pin_hash'])) fail('Incorrect PIN.', 403);
        out(['success' => true]);
    }

    case 'update_task_status': {
        $allowed = ['PENDING', 'IN PROGRESS', 'UNDER REVIEW', 'DONE'];
        $status = $in['status'] ?? '';
        if (!in_array($status, $allowed, true)) fail('Invalid status.');
        $id = (int)($in['task_id'] ?? 0);
        q('UPDATE sub_tasks SET status = ? WHERE id = ?', [$status, $id]);
        if ($status !== 'DONE') q('UPDATE group_deliverables SET is_verified = 0 WHERE id = (SELECT deliverable_id FROM sub_tasks WHERE id = ?)', [$id]);
        out(['success' => true]);
    }

    case 'add_deliverable': {
        $p = project($code); $title = trim($in['title'] ?? '');
        if (!$title) fail('Title required.');
        q('INSERT INTO group_deliverables (project_id, title) VALUES (?, ?)', [$p['id'], $title]);
        out(['success' => true, 'id' => $pdo->lastInsertId()]);
    }

    case 'edit_deliverable':
        q('UPDATE group_deliverables SET title = ? WHERE id = ?', [trim($in['title'] ?? ''), (int)$in['deliverable_id']]); out(['success' => true]);

    case 'delete_deliverable':
        q('DELETE FROM group_deliverables WHERE id = ?', [(int)$in['deliverable_id']]); out(['success' => true]);

    case 'verify_deliverable': {
        $id = (int)$in['deliverable_id']; $v = (int)($in['verified'] ?? 0);
        if ($v) {
            $r = q('SELECT COUNT(*) total, SUM(status = "DONE") done FROM sub_tasks WHERE deliverable_id = ?', [$id])->fetch();
            if ((int)$r['total'] === 0 || (int)$r['total'] !== (int)$r['done']) fail('All sub-tasks must be approved first.');
        }
        q('UPDATE group_deliverables SET is_verified = ? WHERE id = ?', [$v, $id]);
        out(['success' => true]);
    }

    case 'add_sub_task':
        q('INSERT INTO sub_tasks (deliverable_id, assignee, title) VALUES (?, ?, ?)', [(int)$in['deliverable_id'], trim($in['assignee']), trim($in['title'])]); out(['success' => true]);

    case 'edit_sub_task':
        q('UPDATE sub_tasks SET deliverable_id = ?, assignee = ?, title = ? WHERE id = ?', [(int)$in['deliverable_id'], trim($in['assignee']), trim($in['title']), (int)$in['task_id']]); out(['success' => true]);

    case 'delete_sub_task':
        q('DELETE FROM sub_tasks WHERE id = ?', [(int)$in['task_id']]); out(['success' => true]);

    case 'add_member': {
        $p = project($code);
        $role = ($in['role'] ?? '') === 'Leader' ? 'Leader' : 'Member';
        q('INSERT INTO members (project_id, name, role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role)', [$p['id'], trim($in['name']), $role]);
        out(['success' => true]);
    }

    case 'remove_member': { $p = project($code); q('DELETE FROM members WHERE project_id = ? AND name = ?', [$p['id'], $in['name']]); out(['success' => true]); }

    case 'add_deadline': { $p = project($code); q('INSERT INTO deadlines (project_id, deadline_date, title) VALUES (?, ?, ?)', [$p['id'], trim($in['date']), trim($in['title'])]); out(['success' => true]); }

    case 'add_goal': { $p = project($code); q('INSERT INTO goals (project_id, title, description) VALUES (?, ?, ?)', [$p['id'], trim($in['title']), trim($in['description'])]); out(['success' => true]); }

    case 'remove_goal': { $p = project($code); q('DELETE FROM goals WHERE project_id = ? AND title = ? LIMIT 1', [$p['id'], $in['title']]); out(['success' => true]); }

    case 'submit_weekly': {
        $p = project($code);
        q('INSERT INTO weekly_logs (project_id, member_name, week, commentary, proof_link) VALUES (?, ?, ?, ?, ?)', [$p['id'], $in['member_name'], $in['week'], $in['commentary'], $in['link']]);
        out(['success' => true]);
    }

    default: fail('Unknown action.', 404);
    }
} catch (Exception $e) { fail('Server error.', 500); }
