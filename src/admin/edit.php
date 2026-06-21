<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/template_repo.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/upload.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$template = $id ? get_template($id) : null;
$error = null;
$form_data = [];
$authors = fetch_authors();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $preview_images = [];

    if (!empty($_POST['existing_images'])) {
        foreach ($_POST['existing_images'] as $img) {
            if (trim($img)) {
                $preview_images[] = trim($img);
            }
        }
    }

    if (!empty($_FILES['new_images']['name'][0])) {
        foreach ($_FILES['new_images']['name'] as $key => $name) {
            if ($_FILES['new_images']['error'][$key] === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['new_images']['name'][$key],
                    'type' => $_FILES['new_images']['type'][$key],
                    'tmp_name' => $_FILES['new_images']['tmp_name'][$key],
                    'error' => $_FILES['new_images']['error'][$key],
                    'size' => $_FILES['new_images']['size'][$key],
                ];
                $uploaded = handle_file_upload($file, 'images');
                if ($uploaded) {
                    $preview_images[] = $uploaded;
                }
            }
        }
    }

    $download_url = trim($_POST['download_url'] ?? '');
    if (!empty($_FILES['download_file']['name']) && $_FILES['download_file']['error'] === UPLOAD_ERR_OK) {
        $uploaded = handle_file_upload($_FILES['download_file'], 'files');
        if ($uploaded) {
            $download_url = $uploaded;
        }
    }

    if (empty($download_url) && $template) {
        $download_url = $template['download_url'] ?? '';
    }

    $is_ai = isset($_POST['is_ai_generated']) ? 1 : 0;

    $payload = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'article' => trim($_POST['article'] ?? ''),
        'download_url' => $download_url,
        'tags' => trim($_POST['tags'] ?? ''),
        'preview_images' => $preview_images,
        'author_id' => $_POST['author_id'] ?? null,
        'is_ai_generated' => $is_ai,
        'ai_tool' => $is_ai ? trim($_POST['ai_tool'] ?? '') : null,
        'ai_commercial_basis' => $is_ai ? trim($_POST['ai_commercial_basis'] ?? '') : null,
        'ai_has_portrait' => $is_ai && isset($_POST['ai_has_portrait']) ? 1 : 0,
    ];

    if (!$payload['title'] || !$payload['download_url']) {
        $error = '标题和下载链接为必填项。';
        $form_data = $payload;
    } elseif ($is_ai && !$payload['author_id']) {
        $error = 'AI 生成素材必须绑定作者。';
        $form_data = $payload;
    } elseif ($is_ai && !$payload['ai_tool']) {
        $error = 'AI 生成素材必须声明所使用的 AI 工具。';
        $form_data = $payload;
    } elseif ($is_ai && !$payload['ai_commercial_basis']) {
        $error = 'AI 生成素材必须声明可商用依据。';
        $form_data = $payload;
    } else {
        upsert_template($payload, $id);
        header('Location: /admin/dashboard.php');
        exit;
    }
}

$display_data = !empty($form_data) ? $form_data : $template;
if (!empty($form_data)) {
    $images = $form_data['preview_images'] ?? [];
} else {
    $images = $template ? format_preview_images($template['preview_images']) : [];
}
$is_ai_checked = !empty($form_data) ? (!empty($form_data['is_ai_generated'])) : ($template ? (!empty($template['is_ai_generated'])) : false);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $id ? '编辑模板' : '新增模板'; ?></title>
    <link rel="stylesheet" href="/assets/styles.css">
    <style>
        .wrap {max-width: 900px;margin:40px auto;padding:0 20px;}
        form {display:grid;gap:14px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:22px;box-shadow:0 14px 30px rgba(15,23,42,0.08);}
        label {font-weight:600;display:block;margin-bottom:6px;}
        input, textarea {width:100%;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px;font-size:1rem;}
        textarea {min-height:120px;resize:vertical;}
        .error {color:#b91c1c;background:rgba(248,113,113,0.12);border:1px solid rgba(248,113,113,0.3);padding:10px 12px;border-radius:12px;}
        .images-section {border:2px dashed #e2e8f0;padding:16px;border-radius:12px;background:#f8fafc;}
        .image-preview {display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-top:10px;}
        .preview-item {position:relative;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;aspect-ratio:1;}
        .preview-item img {width:100%;height:100%;object-fit:cover;}
        .preview-item .remove {position:absolute;top:4px;right:4px;background:#ef4444;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;font-size:0.85rem;}
        .file-input-group {display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
        .hint {color:#64748b;font-size:0.9rem;margin-top:4px;}
        .download-section {display:grid;gap:10px;}
        .ai-section {border:2px solid #c084fc;padding:16px;border-radius:12px;background:#faf5ff;}
    </style>
</head>
<body>
<div class="wrap">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h2 style="margin:0;"><?php echo $id ? '编辑模板' : '新增模板'; ?></h2>
        <a class="btn btn-ghost" href="/admin/dashboard.php">返回列表</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div>
            <label>标题*</label>
            <input name="title" value="<?php echo e($display_data['title'] ?? ''); ?>" required>
        </div>
        <div>
            <label>作者</label>
            <select name="author_id" style="width:100%;padding:12px 14px;border:1px solid #e2e8f0;border-radius:12px;font-size:1rem;">
                <option value="">-- 选择作者 --</option>
                <?php foreach ($authors as $author): ?>
                    <option value="<?php echo e($author['id']); ?>" <?php echo (($display_data['author_id'] ?? '') == $author['id']) ? 'selected' : ''; ?>><?php echo e($author['name']); ?>（信用 <?php echo e($author['credit_score']); ?>）</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>简要描述</label>
            <textarea name="description"><?php echo e($display_data['description'] ?? ''); ?></textarea>
        </div>
        <div>
            <label>正文/文章</label>
            <textarea name="article" style="min-height:160px;"><?php echo e($display_data['article'] ?? ''); ?></textarea>
        </div>
        
        <div class="images-section">
            <label>预览图片</label>
            <div class="hint">可上传图片文件（JPG/PNG/GIF/WEBP，最大 50MB）或保留现有图片</div>
            
            <?php if (!empty($images)): ?>
                <div class="image-preview" id="existingImages">
                    <?php foreach ($images as $idx => $img): ?>
                        <?php if ($img): ?>
                            <div class="preview-item" data-url="<?php echo e($img); ?>">
                                <img src="<?php echo e($img); ?>" alt="预览图">
                                <button type="button" class="remove" onclick="removeImage(this)">删除</button>
                                <input type="hidden" name="existing_images[]" value="<?php echo e($img); ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div style="margin-top:12px;">
                <label for="newImagesBtn" style="cursor:pointer;display:inline-block;padding:10px 16px;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:8px;">
                    + 选择图片添加
                </label>
                <input type="file" id="newImagesBtn" accept="image/*" multiple style="display:none;" onchange="addNewImages(this)">
            </div>
            <div class="image-preview" id="newImagesPreviews" style="margin-top:10px;"></div>
        </div>

        <div class="download-section">
            <label>下载资源</label>
            <div class="hint">可上传文件（ZIP/RAR/7Z 等，最大 50MB）或填写外部下载链接</div>
            
            <div>
                <label style="font-weight:normal;font-size:0.95rem;">下载链接（URL）</label>
                <input name="download_url" placeholder="https://example.com/file.zip" value="<?php echo e($display_data['download_url'] ?? ''); ?>">
            </div>
            
            <div>
                <label style="font-weight:normal;font-size:0.95rem;">或上传文件</label>
                <input type="file" name="download_file" accept=".zip,.rar,.7z,.tar,.gz">
                <div class="hint">上传文件后将自动使用文件地址，覆盖上方填写的链接</div>
            </div>
        </div>

        <div>
            <label>标签（用逗号分隔）</label>
            <input name="tags" placeholder="企业,响应式" value="<?php echo e($display_data['tags'] ?? ''); ?>">
        </div>

        <div class="ai-section">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="is_ai_generated" id="is_ai_generated" value="1" <?php echo $is_ai_checked ? 'checked' : ''; ?> onchange="toggleAiFields()">
                此素材由 AI 生成或辅助生成
            </label>
            <div id="ai_fields" style="<?php echo $is_ai_checked ? '' : 'display:none;'; ?>margin-top:12px;display:<?php echo $is_ai_checked ? 'grid' : 'none'; ?>;gap:12px;">
                <div>
                    <label>AI 工具名称*</label>
                    <input name="ai_tool" id="ai_tool" placeholder="如 Midjourney V6、DALL·E 3、Stable Diffusion 等" value="<?php echo e($display_data['ai_tool'] ?? ''); ?>">
                    <div class="hint">声明用于生成此素材的 AI 工具及版本</div>
                </div>
                <div>
                    <label>可商用依据*</label>
                    <input name="ai_commercial_basis" id="ai_commercial_basis" placeholder="如 付费订阅可商用、开源协议 MIT、CC BY 4.0 等" value="<?php echo e($display_data['ai_commercial_basis'] ?? ''); ?>">
                    <div class="hint">说明此 AI 生成素材可商用的法律/协议依据</div>
                </div>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:normal;">
                    <input type="checkbox" name="ai_has_portrait" value="1" <?php echo !empty($display_data['ai_has_portrait']) ? 'checked' : ''; ?>>
                    素材中包含 AI 生成的人物肖像
                </label>
            </div>
        </div>
        
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <a class="btn btn-ghost" href="/admin/dashboard.php">取消</a>
            <button class="btn btn-primary" type="submit">保存</button>
        </div>
    </form>
</div>

<script>
let newImageFiles = [];

function toggleAiFields() {
    const checked = document.getElementById('is_ai_generated').checked;
    const aiFields = document.getElementById('ai_fields');
    aiFields.style.display = checked ? 'grid' : 'none';
}

function removeImage(btn) {
    if (confirm('确认删除此图片？')) {
        btn.closest('.preview-item').remove();
    }
}

function addNewImages(input) {
    if (!input.files || input.files.length === 0) return;
    
    Array.from(input.files).forEach(file => {
        if (file.type.startsWith('image/')) {
            newImageFiles.push(file);
            
            const reader = new FileReader();
            reader.onload = (e) => {
                const container = document.getElementById('newImagesPreviews');
                const div = document.createElement('div');
                div.className = 'preview-item';
                div.setAttribute('data-index', newImageFiles.length - 1);
                div.innerHTML = `
                    <img src="${e.target.result}" alt="${file.name}">
                    <button type="button" class="remove" onclick="removeNewImage(this)">删除</button>
                `;
                container.appendChild(div);
            };
            reader.readAsDataURL(file);
        }
    });
    
    input.value = '';
}

function removeNewImage(btn) {
    if (confirm('确认删除此图片？')) {
        const item = btn.closest('.preview-item');
        const index = parseInt(item.getAttribute('data-index'));
        newImageFiles[index] = null;
        item.remove();
    }
}

document.querySelector('form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // 添加所有未删除的新图片文件到隐藏容器
    let fileInputsContainer = document.getElementById('dynamicFileInputs');
    if (!fileInputsContainer) {
        fileInputsContainer = document.createElement('div');
        fileInputsContainer.id = 'dynamicFileInputs';
        fileInputsContainer.style.display = 'none';
        this.appendChild(fileInputsContainer);
    }
    fileInputsContainer.innerHTML = '';
    
    // 只添加有效的图片文件
    const validFiles = newImageFiles.filter(f => f !== null);
    if (validFiles.length > 0) {
        validFiles.forEach((file) => {
            const input = document.createElement('input');
            input.type = 'file';
            input.name = 'new_images[]';
            input.style.display = 'none';
            
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            input.files = dataTransfer.files;
            
            fileInputsContainer.appendChild(input);
        });
    }
    
    // 直接提交表单（保留所有原始 input，包括下载文件）
    this.submit();
});
</script>
</body>
</html>
