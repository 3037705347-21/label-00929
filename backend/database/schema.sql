-- ============================================
-- 探店达人营销平台 数据库建表脚本
-- MySQL 8.0+
-- 字符集: utf8mb4
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------
-- 用户表
-- -------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL COMMENT '用户名',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
    `email` VARCHAR(100) DEFAULT NULL COMMENT '邮箱',
    `user_type` TINYINT NOT NULL DEFAULT 1 COMMENT '用户类型:1商家2达人3管理员',
    `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:0禁用1正常',
    `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(50) DEFAULT NULL COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_phone` (`phone`),
    KEY `idx_user_type` (`user_type`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- -------------------------------------------
-- 商家详情表
-- -------------------------------------------
DROP TABLE IF EXISTS `merchants`;
CREATE TABLE `merchants` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `shop_name` VARCHAR(100) NOT NULL COMMENT '店铺名称',
    `shop_logo` VARCHAR(255) DEFAULT NULL COMMENT '店铺Logo',
    `province` VARCHAR(50) DEFAULT NULL COMMENT '省份',
    `city` VARCHAR(50) DEFAULT NULL COMMENT '城市',
    `district` VARCHAR(50) DEFAULT NULL COMMENT '区县',
    `address` VARCHAR(255) DEFAULT NULL COMMENT '详细地址',
    `longitude` DECIMAL(10,7) DEFAULT NULL COMMENT '经度',
    `latitude` DECIMAL(10,7) DEFAULT NULL COMMENT '纬度',
    `contact_name` VARCHAR(50) DEFAULT NULL COMMENT '联系人',
    `contact_phone` VARCHAR(20) DEFAULT NULL COMMENT '联系电话',
    `category_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '所属分类',
    `description` TEXT COMMENT '店铺介绍',
    `images` TEXT COMMENT '店铺图片JSON',
    `business_license` VARCHAR(255) DEFAULT NULL COMMENT '营业执照',
    `verify_status` TINYINT NOT NULL DEFAULT 0 COMMENT '认证状态:0待审核1已通过2已拒绝',
    `verify_note` VARCHAR(255) DEFAULT NULL COMMENT '审核备注',
    `verified_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_category` (`category_id`),
    KEY `idx_verify_status` (`verify_status`),
    KEY `idx_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商家详情表';

-- -------------------------------------------
-- 达人信息表
-- -------------------------------------------
DROP TABLE IF EXISTS `influencers`;
CREATE TABLE `influencers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `nickname` VARCHAR(50) NOT NULL COMMENT '昵称',
    `real_name` VARCHAR(50) DEFAULT NULL COMMENT '真实姓名',
    `id_card` VARCHAR(20) DEFAULT NULL COMMENT '身份证号',
    `gender` TINYINT DEFAULT 0 COMMENT '性别:0未知1男2女',
    `birthday` DATE DEFAULT NULL COMMENT '生日',
    `province` VARCHAR(50) DEFAULT NULL COMMENT '所在省份',
    `city` VARCHAR(50) DEFAULT NULL COMMENT '所在城市',
    `bio` VARCHAR(500) DEFAULT NULL COMMENT '个人简介',
    `platform_type` VARCHAR(20) NOT NULL COMMENT '主平台:douyin/xiaohongshu/kuaishou',
    `platform_account` VARCHAR(100) NOT NULL COMMENT '平台账号ID',
    `platform_url` VARCHAR(255) DEFAULT NULL COMMENT '主页链接',
    `follower_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '粉丝数',
    `follower_screenshot` VARCHAR(255) DEFAULT NULL COMMENT '粉丝截图',
    `content_style` VARCHAR(100) DEFAULT NULL COMMENT '内容风格',
    `content_categories` VARCHAR(255) DEFAULT NULL COMMENT '擅长领域JSON',
    `avg_views` DECIMAL(12,2) DEFAULT 0 COMMENT '平均播放量',
    `avg_likes` DECIMAL(12,2) DEFAULT 0 COMMENT '平均点赞数',
    `avg_comments` DECIMAL(12,2) DEFAULT 0 COMMENT '平均评论数',
    `completed_tasks` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '完成任务数',
    `total_earnings` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '累计收益',
    `rating` DECIMAL(3,2) DEFAULT 5.00 COMMENT '评分',
    `verify_status` TINYINT NOT NULL DEFAULT 0 COMMENT '认证状态:0待审核1已通过2已拒绝',
    `verify_note` VARCHAR(255) DEFAULT NULL COMMENT '审核备注',
    `verified_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`),
    KEY `idx_platform` (`platform_type`),
    KEY `idx_follower` (`follower_count`),
    KEY `idx_verify_status` (`verify_status`),
    KEY `idx_city` (`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='达人信息表';

-- -------------------------------------------
-- 达人标签表
-- -------------------------------------------
DROP TABLE IF EXISTS `influencer_tags`;
CREATE TABLE `influencer_tags` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `influencer_id` BIGINT UNSIGNED NOT NULL,
    `tag_name` VARCHAR(50) NOT NULL COMMENT '标签名',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_influencer` (`influencer_id`),
    KEY `idx_tag` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='达人标签表';

-- -------------------------------------------
-- 分类表
-- -------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL COMMENT '分类名称',
    `icon` VARCHAR(100) DEFAULT NULL COMMENT '图标',
    `parent_id` BIGINT UNSIGNED DEFAULT 0 COMMENT '父级ID',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:0禁用1启用',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_parent` (`parent_id`),
    KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分类表';


-- -------------------------------------------
-- 任务表
-- -------------------------------------------
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `merchant_id` BIGINT UNSIGNED NOT NULL COMMENT '商家ID',
    `title` VARCHAR(200) NOT NULL COMMENT '任务标题',
    `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '封面图',
    `task_type` TINYINT NOT NULL DEFAULT 1 COMMENT '任务类型:1云探店2到店实探',
    `requirements` TEXT NOT NULL COMMENT '任务要求',
    `content_form` VARCHAR(100) DEFAULT NULL COMMENT '内容形式:视频/图文/直播',
    `publish_platform` VARCHAR(100) DEFAULT NULL COMMENT '发布平台',
    `publish_deadline` DATE DEFAULT NULL COMMENT '发布截止日期',
    `target_product` VARCHAR(200) DEFAULT NULL COMMENT '推广商品/套餐',
    `product_price` DECIMAL(10,2) DEFAULT NULL COMMENT '商品价格',
    `product_images` TEXT COMMENT '商品图片JSON',
    `influencer_requirements` TEXT COMMENT '达人要求JSON',
    `min_followers` INT UNSIGNED DEFAULT 0 COMMENT '最低粉丝要求',
    `commission_type` TINYINT NOT NULL DEFAULT 1 COMMENT '佣金类型:1一口价2CPS',
    `commission_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '一口价金额',
    `cps_rate` DECIMAL(5,2) DEFAULT 0 COMMENT 'CPS分成比例%',
    `total_budget` DECIMAL(12,2) NOT NULL COMMENT '总预算',
    `frozen_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '已冻结金额',
    `spent_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '已支出金额',
    `max_influencers` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '最大接单人数',
    `applied_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已报名人数',
    `accepted_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已接单人数',
    `completed_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已完成人数',
    `start_date` DATE NOT NULL COMMENT '任务开始日期',
    `end_date` DATE NOT NULL COMMENT '任务结束日期',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0草稿1审核中2进行中3已结束4已取消5审核拒绝',
    `review_note` VARCHAR(255) DEFAULT NULL COMMENT '审核备注',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_merchant` (`merchant_id`),
    KEY `idx_status` (`status`),
    KEY `idx_task_type` (`task_type`),
    KEY `idx_commission_type` (`commission_type`),
    KEY `idx_end_date` (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务表';

-- -------------------------------------------
-- 任务订单表
-- -------------------------------------------
DROP TABLE IF EXISTS `task_orders`;
CREATE TABLE `task_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `task_id` BIGINT UNSIGNED NOT NULL COMMENT '任务ID',
    `influencer_id` BIGINT UNSIGNED NOT NULL COMMENT '达人ID',
    `merchant_id` BIGINT UNSIGNED NOT NULL COMMENT '商家ID',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:1已报名2已接单3执行中4待审核5已完成6已拒绝7已取消',
    `apply_note` TEXT COMMENT '报名说明',
    `reject_reason` VARCHAR(255) DEFAULT NULL COMMENT '拒绝原因',
    `commission_type` TINYINT NOT NULL COMMENT '佣金类型',
    `commission_amount` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '预计佣金',
    `cps_rate` DECIMAL(5,2) DEFAULT 0 COMMENT 'CPS比例',
    `cps_sales` DECIMAL(12,2) DEFAULT 0 COMMENT 'CPS销售额',
    `final_commission` DECIMAL(10,2) DEFAULT 0 COMMENT '最终佣金',
    `platform_fee` DECIMAL(10,2) DEFAULT 0 COMMENT '平台服务费',
    `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '报名时间',
    `accepted_at` DATETIME DEFAULT NULL COMMENT '接单时间',
    `started_at` DATETIME DEFAULT NULL COMMENT '开始执行时间',
    `submitted_at` DATETIME DEFAULT NULL COMMENT '提交审核时间',
    `completed_at` DATETIME DEFAULT NULL COMMENT '完成时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    UNIQUE KEY `uk_task_influencer` (`task_id`, `influencer_id`),
    KEY `idx_influencer` (`influencer_id`),
    KEY `idx_merchant` (`merchant_id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='任务订单表';

-- -------------------------------------------
-- 作品表
-- -------------------------------------------
DROP TABLE IF EXISTS `works`;
CREATE TABLE `works` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL COMMENT '订单ID',
    `influencer_id` BIGINT UNSIGNED NOT NULL COMMENT '达人ID',
    `stage` TINYINT NOT NULL COMMENT '阶段:1脚本2素材3成片',
    `title` VARCHAR(200) DEFAULT NULL COMMENT '作品标题',
    `content` TEXT COMMENT '内容描述',
    `file_urls` TEXT COMMENT '文件链接JSON',
    `publish_url` VARCHAR(500) DEFAULT NULL COMMENT '发布链接',
    `publish_time` DATETIME DEFAULT NULL COMMENT '发布时间',
    `view_count` INT UNSIGNED DEFAULT 0 COMMENT '播放/阅读量',
    `like_count` INT UNSIGNED DEFAULT 0 COMMENT '点赞数',
    `comment_count` INT UNSIGNED DEFAULT 0 COMMENT '评论数',
    `share_count` INT UNSIGNED DEFAULT 0 COMMENT '分享数',
    `review_status` TINYINT NOT NULL DEFAULT 0 COMMENT '审核状态:0待审核1通过2拒绝',
    `review_note` TEXT COMMENT '审核备注',
    `reviewer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核人ID',
    `reviewer_type` TINYINT DEFAULT NULL COMMENT '审核人类型:1商家2平台',
    `submitted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '提交时间',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_influencer` (`influencer_id`),
    KEY `idx_stage` (`stage`),
    KEY `idx_review_status` (`review_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='作品表';

-- -------------------------------------------
-- 钱包表
-- -------------------------------------------
DROP TABLE IF EXISTS `wallets`;
CREATE TABLE `wallets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `balance` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '可用余额',
    `frozen_amount` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '冻结金额',
    `total_recharge` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '累计充值',
    `total_expense` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '累计支出',
    `total_income` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '累计收入',
    `total_withdraw` DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT '累计提现',
    `version` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '乐观锁版本',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钱包表';

-- -------------------------------------------
-- 交易流水表
-- -------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `trans_no` VARCHAR(32) NOT NULL COMMENT '交易流水号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `wallet_id` BIGINT UNSIGNED NOT NULL COMMENT '钱包ID',
    `type` TINYINT NOT NULL COMMENT '类型:1充值2消费3冻结4解冻5结算收入6提现7退款8平台服务费',
    `amount` DECIMAL(12,2) NOT NULL COMMENT '金额',
    `balance_before` DECIMAL(12,2) NOT NULL COMMENT '交易前余额',
    `balance_after` DECIMAL(12,2) NOT NULL COMMENT '交易后余额',
    `frozen_before` DECIMAL(12,2) DEFAULT 0 COMMENT '交易前冻结',
    `frozen_after` DECIMAL(12,2) DEFAULT 0 COMMENT '交易后冻结',
    `related_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联ID',
    `related_type` VARCHAR(50) DEFAULT NULL COMMENT '关联类型:task/order/withdraw',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '描述',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:0失败1成功2处理中',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_trans_no` (`trans_no`),
    KEY `idx_user` (`user_id`),
    KEY `idx_wallet` (`wallet_id`),
    KEY `idx_type` (`type`),
    KEY `idx_related` (`related_type`, `related_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='交易流水表';

-- -------------------------------------------
-- 提现申请表
-- -------------------------------------------
DROP TABLE IF EXISTS `withdrawals`;
CREATE TABLE `withdrawals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `withdraw_no` VARCHAR(32) NOT NULL COMMENT '提现单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `amount` DECIMAL(12,2) NOT NULL COMMENT '提现金额',
    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '手续费',
    `actual_amount` DECIMAL(12,2) NOT NULL COMMENT '实际到账',
    `withdraw_type` TINYINT NOT NULL DEFAULT 1 COMMENT '提现方式:1银行卡2支付宝3微信',
    `account_name` VARCHAR(50) NOT NULL COMMENT '账户名',
    `account_no` VARCHAR(50) NOT NULL COMMENT '账号',
    `bank_name` VARCHAR(100) DEFAULT NULL COMMENT '银行名称',
    `bank_branch` VARCHAR(100) DEFAULT NULL COMMENT '开户支行',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待审核1已通过2已拒绝3已打款4打款失败',
    `review_note` VARCHAR(255) DEFAULT NULL COMMENT '审核备注',
    `reviewer_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '审核人',
    `reviewed_at` DATETIME DEFAULT NULL COMMENT '审核时间',
    `paid_at` DATETIME DEFAULT NULL COMMENT '打款时间',
    `pay_trans_no` VARCHAR(64) DEFAULT NULL COMMENT '打款流水号',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_withdraw_no` (`withdraw_no`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提现申请表';

-- -------------------------------------------
-- 支付订单表
-- -------------------------------------------
DROP TABLE IF EXISTS `payment_orders`;
CREATE TABLE `payment_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_no` VARCHAR(32) NOT NULL COMMENT '订单号',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `amount` DECIMAL(12,2) NOT NULL COMMENT '支付金额',
    `pay_method` VARCHAR(20) NOT NULL COMMENT '支付方式:wechat/alipay',
    `order_type` VARCHAR(20) NOT NULL DEFAULT 'recharge' COMMENT '订单类型:recharge充值',
    `status` TINYINT NOT NULL DEFAULT 0 COMMENT '状态:0待支付1已支付2支付失败3已退款',
    `transaction_id` VARCHAR(64) DEFAULT NULL COMMENT '第三方交易号',
    `expire_at` DATETIME DEFAULT NULL COMMENT '过期时间',
    `paid_at` DATETIME DEFAULT NULL COMMENT '支付时间',
    `refund_at` DATETIME DEFAULT NULL COMMENT '退款时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_order_no` (`order_no`),
    KEY `idx_user` (`user_id`),
    KEY `idx_status` (`status`),
    KEY `idx_pay_method` (`pay_method`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='支付订单表';

-- -------------------------------------------
-- 消息表
-- -------------------------------------------
DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联订单ID',
    `sender_id` BIGINT UNSIGNED NOT NULL COMMENT '发送者ID',
    `receiver_id` BIGINT UNSIGNED NOT NULL COMMENT '接收者ID',
    `content` TEXT NOT NULL COMMENT '消息内容',
    `msg_type` TINYINT NOT NULL DEFAULT 1 COMMENT '消息类型:1文本2图片3文件',
    `attachments` TEXT COMMENT '附件JSON',
    `is_read` TINYINT NOT NULL DEFAULT 0 COMMENT '是否已读',
    `read_at` DATETIME DEFAULT NULL COMMENT '阅读时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order` (`order_id`),
    KEY `idx_sender` (`sender_id`),
    KEY `idx_receiver` (`receiver_id`),
    KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息表';

-- -------------------------------------------
-- 内容广场展示表
-- -------------------------------------------
DROP TABLE IF EXISTS `showcase_works`;
CREATE TABLE `showcase_works` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `work_id` BIGINT UNSIGNED NOT NULL COMMENT '作品ID',
    `influencer_id` BIGINT UNSIGNED NOT NULL COMMENT '达人ID',
    `merchant_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '商家ID',
    `title` VARCHAR(200) NOT NULL COMMENT '展示标题',
    `description` TEXT COMMENT '描述',
    `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '封面图',
    `platform` VARCHAR(20) DEFAULT NULL COMMENT '发布平台',
    `publish_url` VARCHAR(500) DEFAULT NULL COMMENT '作品链接',
    `view_count` INT UNSIGNED DEFAULT 0 COMMENT '播放量',
    `like_count` INT UNSIGNED DEFAULT 0 COMMENT '点赞数',
    `comment_count` INT UNSIGNED DEFAULT 0 COMMENT '评论数',
    `conversion_count` INT UNSIGNED DEFAULT 0 COMMENT '转化数',
    `conversion_rate` DECIMAL(5,2) DEFAULT 0 COMMENT '转化率%',
    `is_featured` TINYINT NOT NULL DEFAULT 0 COMMENT '是否精选',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT '状态:0下架1上架',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_work` (`work_id`),
    KEY `idx_influencer` (`influencer_id`),
    KEY `idx_featured` (`is_featured`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='内容广场展示表';

-- -------------------------------------------
-- 系统配置表
-- -------------------------------------------
DROP TABLE IF EXISTS `system_configs`;
CREATE TABLE `system_configs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `config_key` VARCHAR(100) NOT NULL COMMENT '配置键',
    `config_value` TEXT COMMENT '配置值',
    `config_type` VARCHAR(20) DEFAULT 'string' COMMENT '值类型:string/number/json/boolean',
    `description` VARCHAR(255) DEFAULT NULL COMMENT '描述',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- -------------------------------------------
-- 操作日志表
-- -------------------------------------------
DROP TABLE IF EXISTS `operation_logs`;
CREATE TABLE `operation_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '操作用户ID',
    `username` VARCHAR(50) DEFAULT NULL COMMENT '用户名',
    `module` VARCHAR(50) NOT NULL COMMENT '模块',
    `action` VARCHAR(50) NOT NULL COMMENT '操作',
    `target_type` VARCHAR(50) DEFAULT NULL COMMENT '目标类型',
    `target_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '目标ID',
    `params` TEXT COMMENT '请求参数',
    `result` TEXT COMMENT '操作结果',
    `ip` VARCHAR(50) DEFAULT NULL COMMENT 'IP地址',
    `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'UA',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user` (`user_id`),
    KEY `idx_module` (`module`),
    KEY `idx_action` (`action`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='操作日志表';

-- -------------------------------------------
-- 初始数据
-- -------------------------------------------

-- 管理员账号 (密码: admin123)
INSERT INTO `users` (`username`, `password_hash`, `phone`, `user_type`, `status`) VALUES
('admin', '$2y$10$7D.zMKxu1aVAFvj4oWgr1ObuVtTyOmRz1ImuwYLMJQqhNXqUO7lVC', '13800000000', 3, 1);

-- 初始分类
INSERT INTO `categories` (`name`, `icon`, `parent_id`, `sort_order`, `status`) VALUES
('美食餐饮', '🍜', 0, 1, 1),
('休闲娱乐', '🎮', 0, 2, 1),
('丽人美发', '💇', 0, 3, 1),
('酒店民宿', '🏨', 0, 4, 1),
('亲子母婴', '👶', 0, 5, 1),
('运动健身', '🏃', 0, 6, 1),
('医疗健康', '🏥', 0, 7, 1),
('教育培训', '📚', 0, 8, 1);

-- 系统配置
INSERT INTO `system_configs` (`config_key`, `config_value`, `config_type`, `description`) VALUES
('platform_fee_rate', '10', 'number', '平台服务费比例%'),
('withdraw_fee_rate', '0.5', 'number', '提现手续费比例%'),
('min_withdraw_amount', '100', 'number', '最低提现金额'),
('max_withdraw_amount', '50000', 'number', '单次最高提现金额'),
('task_review_required', 'true', 'boolean', '任务是否需要审核'),
('work_review_required', 'true', 'boolean', '作品是否需要平台审核'),
('site_name', '探店达人平台', 'string', '网站名称'),
('site_logo', '/assets/images/logo.png', 'string', '网站Logo'),
('contact_email', 'support@tandianda.com', 'string', '联系邮箱'),
('contact_phone', '400-888-8888', 'string', '联系电话');

SET FOREIGN_KEY_CHECKS = 1;
