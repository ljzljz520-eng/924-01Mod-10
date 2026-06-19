USE templates_db;

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS authors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  credit_score INT NOT NULL DEFAULT 100,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS templates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  article TEXT,
  preview_images TEXT,
  download_url VARCHAR(255) NOT NULL,
  tags VARCHAR(255),
  author_id INT DEFAULT NULL,
  is_ai_generated TINYINT(1) NOT NULL DEFAULT 0,
  ai_tool VARCHAR(255) DEFAULT NULL,
  ai_commercial_basis VARCHAR(255) DEFAULT NULL,
  ai_has_portrait TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','taken_down') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES authors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO authors (name) VALUES ('默认作者');

INSERT INTO templates (title, description, article, preview_images, download_url, tags, author_id, is_ai_generated, ai_tool, ai_commercial_basis, ai_has_portrait)
VALUES
('极简企业官网', '适合科技类公司的响应式企业官网模板，含产品、案例与联系表单。', '这是一套现代化的企业官网模板，支持移动端自适应，包含首页、产品、案例与联系我们板块。', '["https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1521737604893-d14cc237f11d?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/company-template.zip', '企业,响应式,科技', 1, 0, NULL, NULL, 0),
('作品集展示', '为设计师或自由职业者打造的作品集模板，配色大胆，支持多图预览。', '模板包含首页、作品列表、作品详情与联系页面，可快速替换图片与文案。', '["https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=800&q=80","https://images.unsplash.com/photo-1506765515384-028b60a970df?auto=format&fit=crop&w=800&q=80"]', 'https://example.com/downloads/portfolio-template.zip', '作品集,创意,展示', 1, 1, 'Midjourney V6', '付费订阅可商用', 1);
