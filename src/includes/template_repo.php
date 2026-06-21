<?php
require_once __DIR__ . '/bootstrap.php';

function fetch_templates(?string $keyword = null, ?bool $hide_ai = null, string $status = 'active'): array
{
    $pdo = db();
    $conditions = ['t.status = :status'];
    $params = [':status' => $status];

    if ($keyword) {
        $like = '%' . $keyword . '%';
        $conditions[] = '(t.title LIKE :k OR t.tags LIKE :k)';
        $params[':k'] = $like;
    }

    if ($hide_ai === true) {
        $conditions[] = 't.is_ai_generated = 0';
    }

    $where = implode(' AND ', $conditions);
    $stmt = $pdo->prepare("SELECT t.*, a.name AS author_name, a.credit_score AS author_credit FROM templates t LEFT JOIN authors a ON t.author_id = a.id WHERE {$where} ORDER BY t.created_at DESC");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_template(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT t.*, a.name AS author_name, a.credit_score AS author_credit FROM templates t LEFT JOIN authors a ON t.author_id = a.id WHERE t.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function upsert_template(array $data, ?int $id = null): void
{
    $pdo = db();
    $images = array_values(array_filter(array_map('trim', $data['preview_images'] ?? [])));
    $imagesJson = json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $fields = [
        'title' => $data['title'] ?? '',
        'description' => $data['description'] ?? '',
        'article' => $data['article'] ?? '',
        'preview_images' => $imagesJson,
        'download_url' => $data['download_url'] ?? '',
        'tags' => $data['tags'] ?? '',
        'author_id' => $data['author_id'] ?: null,
        'is_ai_generated' => (int)($data['is_ai_generated'] ?? 0),
        'ai_tool' => $data['ai_tool'] ?? null,
        'ai_commercial_basis' => $data['ai_commercial_basis'] ?? null,
        'ai_has_portrait' => (int)($data['ai_has_portrait'] ?? 0),
    ];

    if ($id === null) {
        $cols = implode(', ', array_keys($fields));
        $placeholders = implode(', ', array_map(fn($k) => ':' . $k, array_keys($fields)));
        $stmt = $pdo->prepare("INSERT INTO templates ({$cols}) VALUES ({$placeholders})");
        $stmt->execute(array_combine(array_map(fn($k) => ':' . $k, array_keys($fields)), array_values($fields)));
    } else {
        $setClause = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($fields)));
        $fields['id'] = $id;
        $stmt = $pdo->prepare("UPDATE templates SET {$setClause} WHERE id = :id");
        $stmt->execute(array_combine(array_map(fn($k) => ':' . $k, array_keys($fields)), array_values($fields)));
    }
}

function delete_template(int $id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM templates WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function takedown_template(int $id, bool $penalize_credit = false): void
{
    $tpl = get_template_raw($id);
    if (!$tpl) {
        return;
    }

    $already_down = $tpl['status'] === 'taken_down';

    $pdo = db();
    $stmt = $pdo->prepare('UPDATE templates SET status = :status WHERE id = :id');
    $stmt->execute([':status' => 'taken_down', ':id' => $id]);

    if ($penalize_credit && !$already_down && !empty($tpl['author_id'])) {
        penalize_author_credit((int)$tpl['author_id'], 10);
    }
}

function restore_template(int $id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE templates SET status = :status WHERE id = :id');
    $stmt->execute([':status' => 'active', ':id' => $id]);
}

function get_template_raw(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM templates WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetch_authors(): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT * FROM authors ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function get_author(int $id): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM authors WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function upsert_author(array $data, ?int $id = null): void
{
    $pdo = db();
    if ($id === null) {
        $stmt = $pdo->prepare('INSERT INTO authors (name) VALUES (:name)');
        $stmt->execute([':name' => $data['name'] ?? '']);
    } else {
        $stmt = $pdo->prepare('UPDATE authors SET name = :name WHERE id = :id');
        $stmt->execute([':name' => $data['name'] ?? '', ':id' => $id]);
    }
}

function delete_author(int $id): void
{
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM authors WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function penalize_author_credit(int $author_id, int $points = 10): void
{
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE authors SET credit_score = GREATEST(0, credit_score - :points) WHERE id = :id');
    $stmt->execute([':points' => $points, ':id' => $author_id]);
}

function format_preview_images(?string $json): array
{
    if (!$json) {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}
