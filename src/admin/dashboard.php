<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/template_repo.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$filter_status = $_GET['status'] ?? 'all';
if ($filter_status === 'taken_down') {
    $templates = fetch_templates(null, null, 'taken_down');
} elseif ($filter_status === 'active') {
    $templates = fetch_templates(null, null, 'active');
} else {
    $pdo = db();
    $stmt = $pdo->query('SELECT t.*, a.name AS author_name, a.credit_score AS author_credit FROM templates t LEFT JOIN authors a ON t.author_id = a.id ORDER BY t.created_at DESC');
    $templates = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>模板管理</title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        .wrap {max-width: 1200px;margin:40px auto;padding:0 20px;}
        table {width:100%;border-collapse:collapse;background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 10px 26px rgba(15,23,42,0.08);}
        th, td {padding:12px 14px;border-bottom:1px solid #e2e8f0;text-align:left;}
        th {background:#f8fafc;color:#475569;font-weight:600;}
        tr:last-child td {border-bottom:none;}
        .actions {display:flex;gap:8px;flex-wrap:wrap;}
        .muted {color:#64748b;font-size:0.92rem;}
        .status-active {display:inline-block;padding:3px 10px;background:#d1fae5;color:#065f46;border-radius:999px;font-size:0.82rem;font-weight:600;}
        .status-down {display:inline-block;padding:3px 10px;background:#fee2e2;color:#991b1b;border-radius:999px;font-size:0.82rem;font-weight:600;}
        .ai-badge-admin {display:inline-block;padding:2px 8px;background:#ede9fe;color:#6d28d9;border-radius:999px;font-size:0.78rem;font-weight:600;}
        .filter-bar {display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;align-items:center;}
        .filter-bar a {padding:8px 14px;border-radius:10px;font-size:0.92rem;text-decoration:none;border:1px solid var(--border);background:#fff;color:var(--text);}
        .filter-bar a.active {background:var(--accent);color:#fff;border-color:var(--accent);}
        .credit-low {color:#dc2626;font-weight:700;}
        .credit-ok {color:#059669;}
    </style>
</head>
<body>
<div class="wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <div>
            <h2 style="margin:0;">模板管理</h2>
            <p class="muted">已登录：<?php echo e($_SESSION['admin_username'] ?? ''); ?></p>
        </div>
        <div style="display:flex;gap:10px;">
            <a class="btn btn-ghost" href="/">返回前台</a>
            <a class="btn btn-primary" href="/admin/edit.php">新增模板</a>
            <a class="btn btn-ghost" href="/admin/logout.php">退出登录</a>
        </div>
    </div>

    <div class="filter-bar">
        <span style="font-weight:600;color:#475569;">筛选状态：</span>
        <a href="/admin/dashboard.php?status=all" class="<?php echo $filter_status === 'all' ? 'active' : ''; ?>">全部</a>
        <a href="/admin/dashboard.php?status=active" class="<?php echo $filter_status === 'active' ? 'active' : ''; ?>">已上架</a>
        <a href="/admin/dashboard.php?status=taken_down" class="<?php echo $filter_status === 'taken_down' ? 'active' : ''; ?>">已下架</a>
    </div>

    <div class="notice" style="margin-bottom:16px;">
        提示：「下架」仅隐藏素材不扣信用；「AI未声明·下架」用于发现 AI 素材未如实声明时，将同时扣除作者 10 信用分。已下架素材重复操作不会重复扣分。
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>标题</th>
                <th>作者</th>
                <th>AI 标识</th>
                <th>状态</th>
                <th>更新时间</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $tpl): ?>
                <tr>
                    <td><?php echo e($tpl['id']); ?></td>
                    <td><?php echo e($tpl['title']); ?></td>
                    <td>
                        <?php if (!empty($tpl['author_name'])): ?>
                            <?php echo e($tpl['author_name']); ?>
                            <span class="<?php echo ($tpl['author_credit'] ?? 100) < 60 ? 'credit-low' : 'credit-ok'; ?>" style="font-size:0.82rem;">（信用 <?php echo e($tpl['author_credit'] ?? 100); ?>）</span>
                        <?php else: ?>
                            <span class="muted">未指定</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($tpl['is_ai_generated'])): ?>
                            <span class="ai-badge-admin">AI 生成</span>
                            <div class="muted" style="font-size:0.8rem;margin-top:2px;"><?php echo e($tpl['ai_tool']); ?></div>
                        <?php else: ?>
                            <span class="muted">人工</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($tpl['status'] === 'active'): ?>
                            <span class="status-active">已上架</span>
                        <?php else: ?>
                            <span class="status-down">已下架</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e($tpl['updated_at']); ?></td>
                    <td>
                        <div class="actions">
                            <a class="btn btn-ghost" href="/admin/edit.php?id=<?php echo e($tpl['id']); ?>">编辑</a>
                            <?php if ($tpl['status'] === 'active'): ?>
                                <a class="btn btn-ghost" href="/admin/review.php?action=takedown&id=<?php echo e($tpl['id']); ?>" onclick="return confirm('确认下架此模板？');">下架</a>
                                <a class="btn btn-ghost" style="background:#fef3c7;border-color:#f59e0b;color:#92400e;" href="/admin/review.php?action=takedown&penalize=1&id=<?php echo e($tpl['id']); ?>" onclick="return confirm('确认标记为「AI 未声明」并下架？将扣除作者 10 信用分。');">AI未声明·下架</a>
                            <?php else: ?>
                                <a class="btn btn-ghost" href="/admin/review.php?action=restore&id=<?php echo e($tpl['id']); ?>" onclick="return confirm('确认恢复此模板？');">恢复</a>
                            <?php endif; ?>
                            <form method="post" action="/admin/delete.php" onsubmit="return confirm('确认删除该模板？');">
                                <input type="hidden" name="id" value="<?php echo e($tpl['id']); ?>">
                                <button class="btn btn-primary" style="background:#ef4444;box-shadow:none;">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
