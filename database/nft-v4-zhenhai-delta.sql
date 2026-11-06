-- ============================================================
-- 数字藏品平台 · BuildAdmin 业务扩展 SQL v4 —— 对接 zhenhai 增量
-- 日期 2026-11-05
-- 适用：MySQL 8.0+ / InnoDB / utf8mb4_unicode_ci（与 zhenhaiQZ database/create_schema.sql 同约定）
-- 说明：
--   1. 本脚本在 zhenhai 库（shengtai）中执行；zhenhai 已有的表一律不建（复用映射见
--      docs/数据库设计-对接zhenhai-v4.md 第 2 节）。
--   2. 业务表全部无前缀（zhenhai 风格），已核对与 zhenhai 49 张表零字面冲突；
--      BuildAdmin 系统表名跟随框架安装前缀（本次约定安装时前缀留空）。
--   3. 金额一律以「分」落库；时间字段 bigint Unix 秒（0 = 不限/常驻）。
--   4. 新增表统一带 is_deleted 软删（后台回收站语义）；存量表软删另批处理。
--   5. ALTER 语句使用 ADD COLUMN IF NOT EXISTS 语义的兼容写法（MySQL 8.0 原生
--      不支持 IF NOT EXISTS，重复执行会报 1060，可按需注释）。
-- ============================================================

-- ------------------------------------------------------------
-- 一、存量表扩展（v1~v3 已建的 * 表）
-- ------------------------------------------------------------

-- 1) 抽签活动：补齐码配置三组参数（发码模式/码格式/邀请得码/开奖模式，原型已锁定）
ALTER TABLE `raffle`
  ADD COLUMN `base_mode`   tinyint unsigned NOT NULL DEFAULT 1 COMMENT '中奖口径:1=按人中签(一人一签),2=按签中签(多码多签)' AFTER `per_limit`,
  ADD COLUMN `code_fmt`    tinyint unsigned NOT NULL DEFAULT 1 COMMENT '码格式:1=纯数字10位,2=纯字母8位(去易混淆字符),3=前缀+数字' AFTER `base_mode`,
  ADD COLUMN `code_prefix` varchar(8)  NOT NULL DEFAULT ''  COMMENT '码前缀(格式=3时使用,如 ZH)' AFTER `code_fmt`,
  ADD COLUMN `invite_on`   tinyint unsigned NOT NULL DEFAULT 0 COMMENT '邀请得码:0=关,1=开' AFTER `code_prefix`,
  ADD COLUMN `invite_per`  int unsigned NOT NULL DEFAULT 3 COMMENT '每邀请N人得1码' AFTER `invite_on`,
  ADD COLUMN `invite_cap`  int unsigned NOT NULL DEFAULT 5 COMMENT '邀请得码上限' AFTER `invite_per`,
  ADD COLUMN `invite_new`  tinyint unsigned NOT NULL DEFAULT 1 COMMENT '仅有效新用户计入邀请:1=是,0=否' AFTER `invite_cap`,
  ADD COLUMN `draw_mode`   tinyint unsigned NOT NULL DEFAULT 1 COMMENT '开奖模式:1=按码等概率(抽码),2=按签抽券(单人可中多签)' AFTER `invite_new`,
  ADD COLUMN `max_wins`    int unsigned NOT NULL DEFAULT 0 COMMENT '按签模式下单人最多中签数(0=不限)' AFTER `draw_mode`,
  ADD COLUMN `whitelist`   text NULL COMMENT '定向白名单手机号串(逗号分隔,必中占名额)' AFTER `max_wins`;

-- 2) 空投任务：支持盲盒对象 + 领取式计数
ALTER TABLE `airdrop_task`
  ADD COLUMN `target_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '对象类型:1=藏品,2=盲盒' AFTER `name`,
  ADD COLUMN `blindbox_id` int unsigned NOT NULL DEFAULT 0 COMMENT '盲盒ID(target_type=2)' AFTER `target_type`,
  ADD COLUMN `claim_pending` int unsigned NOT NULL DEFAULT 0 COMMENT '待领取数(claim_mode=1)' AFTER `revoke_num`;

-- 3) 多场次：规则与记录挂场次 ID（0 = 常驻默认场）
ALTER TABLE `invite_rule`   ADD COLUMN `activity_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动场次ID(0=常驻)' AFTER `id`;
ALTER TABLE `checkin_rule`  ADD COLUMN `activity_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动场次ID(0=常驻)' AFTER `id`;
ALTER TABLE `checkin_record` ADD COLUMN `activity_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动场次ID(0=常驻)' AFTER `id`;
ALTER TABLE `invite_record` ADD COLUMN `activity_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动场次ID(0=常驻)' AFTER `id`;
ALTER TABLE `register_log`  ADD COLUMN `activity_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动场次ID(0=常驻)' AFTER `id`;

-- ------------------------------------------------------------
-- 二、新增表（zhenhai 没有，共 14 张）
-- ------------------------------------------------------------

-- 1) 抽签码
CREATE TABLE IF NOT EXISTS `raffle_code` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `raffle_id` int unsigned NOT NULL DEFAULT 0 COMMENT '抽签活动ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '持码用户ID',
  `source` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '来源:1=报名,2=邀请,3=后台手动发放',
  `invite_from_user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '邀请人ID(source=2)',
  `code` varchar(32) NOT NULL DEFAULT '' COMMENT '码值(数字10位/字母8位/前缀+数字)',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=有效,1=已中签,2=已作废',
  `remark` varchar(100) NOT NULL DEFAULT '' COMMENT '备注(手动发放原因等)',
  `createtime` bigint NULL DEFAULT NULL COMMENT '发码时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_raffle_code` (`raffle_id`,`code`),
  KEY `idx_raffle_user` (`raffle_id`,`user_id`),
  KEY `idx_invite_from` (`invite_from_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽签码表';

-- 2) 多场次活动（邀请/签到/注册统一场次）
CREATE TABLE IF NOT EXISTS `act_session` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '类型:1=邀请,2=签到,3=注册',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '场次名称',
  `start_time` bigint NOT NULL DEFAULT 0 COMMENT '开始时间(0=立即)',
  `end_time` bigint NOT NULL DEFAULT 0 COMMENT '结束时间(0=常驻)',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '人工开关:0=关闭,1=开启(展示态=开关×时间窗:已关闭/未开始/开启中/已结束)',
  `config` text NULL COMMENT '场次差异化配置JSON(注册类:chance/collection_id/pre_num/inviter等)',
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '创建管理员',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '软删:0=否,1=是',
  PRIMARY KEY (`id`),
  KEY `idx_type_status` (`type`,`status`,`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='多场次活动表';

-- 3) 抽奖机会流水
CREATE TABLE IF NOT EXISTS `chance_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `num` int NOT NULL DEFAULT 0 COMMENT '变动次数(正增负减)',
  `source` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '来源:1=连续签到,2=注册礼包,3=持有藏品,4=邀请好友,5=消耗藏品,6=活动配置,7=后台调整',
  `ref` varchar(100) NOT NULL DEFAULT '' COMMENT '关联说明(如: 连续签到3天/消耗「星际漫游者」1件)',
  `balance_after` int unsigned NOT NULL DEFAULT 0 COMMENT '变动后剩余次数',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖机会流水表';

-- 4) 盲盒
CREATE TABLE IF NOT EXISTS `blindbox` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '盲盒名称',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `description` varchar(500) NOT NULL DEFAULT '' COMMENT '描述',
  `price` bigint NOT NULL DEFAULT 0 COMMENT '售价(分)',
  `total_supply` int unsigned NOT NULL DEFAULT 0 COMMENT '发行总量',
  `sold_count` int unsigned NOT NULL DEFAULT 0 COMMENT '已售数量',
  `airdropped` int unsigned NOT NULL DEFAULT 0 COMMENT '已空投数量(独立账,不吃藏品库存池)',
  `limit_per_user` int unsigned NOT NULL DEFAULT 1 COMMENT '每人限购',
  `rule_text` text NULL COMMENT '玩法规则(富文本)',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=下架,1=上架',
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '创建管理员',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '软删:0=否,1=是',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`,`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盲盒表';

-- 5) 盲盒奖池
CREATE TABLE IF NOT EXISTS `blindbox_prize` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `box_id` int unsigned NOT NULL DEFAULT 0 COMMENT '盲盒ID',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID(0=空盒条目,库存降级)',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '奖品名称快照',
  `probability` int unsigned NOT NULL DEFAULT 0 COMMENT '概率(万分比)',
  `quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '份数',
  `sent` int unsigned NOT NULL DEFAULT 0 COMMENT '已开出',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=停用,1=启用',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_box_id` (`box_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盲盒奖池表';

-- 6) 开盒记录
CREATE TABLE IF NOT EXISTS `blindbox_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `box_id` int unsigned NOT NULL DEFAULT 0 COMMENT '盲盒ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `order_id` int unsigned NOT NULL DEFAULT 0 COMMENT '关联订单ID',
  `prize_id` int unsigned NOT NULL DEFAULT 0 COMMENT '奖池条目ID',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '开出藏品ID(0=空盒)',
  `prize_name` varchar(100) NOT NULL DEFAULT '' COMMENT '奖品名称快照(如: 空盒(库存降级))',
  `hold_id` int unsigned NOT NULL DEFAULT 0 COMMENT '开出的持仓ID(空盒=0)',
  `createtime` bigint NULL DEFAULT NULL COMMENT '开盒时间',
  PRIMARY KEY (`id`),
  KEY `idx_box_id` (`box_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='盲盒开盒记录表';

-- 7) 空投明细（逐人逐条，撤销/回收/补发的对账基础）
CREATE TABLE IF NOT EXISTS `airdrop_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `task_id` int unsigned NOT NULL DEFAULT 0 COMMENT '空投任务ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号快照',
  `target_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '对象类型:1=藏品,2=盲盒',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID(target_type=1)',
  `blindbox_id` int unsigned NOT NULL DEFAULT 0 COMMENT '盲盒ID(target_type=2)',
  `num` int unsigned NOT NULL DEFAULT 1 COMMENT '份数',
  `hold_id` int unsigned NOT NULL DEFAULT 0 COMMENT '发放的持仓ID(藏品类)',
  `status` tinyint unsigned NOT NULL DEFAULT 2 COMMENT '状态:0=已撤销,1=已发放,2=发放失败',
  `grant_time` bigint NULL DEFAULT NULL COMMENT '发放时间',
  `revoke_time` bigint NULL DEFAULT NULL COMMENT '撤销时间',
  `remark` varchar(100) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_task_status` (`task_id`,`status`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_collection_status` (`collection_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='空投发放明细表';

-- 8) 发放名录
CREATE TABLE IF NOT EXISTS `roster` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '名录名称(如: 10-27 15:00 前签到用户)',
  `cond_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '圈选条件:1=签到,2=持有藏品,3=消费,4=注册时间,5=手工导入',
  `cond_value` varchar(1000) NOT NULL DEFAULT '' COMMENT '条件参数JSON(时间窗/藏品ID/金额等)',
  `cond_desc` varchar(200) NOT NULL DEFAULT '' COMMENT '条件描述快照',
  `user_count` int unsigned NOT NULL DEFAULT 0 COMMENT '人数',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=停用,1=启用',
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '创建管理员',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '软删:0=否,1=是',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发放名录表';

-- 9) 名录明细
CREATE TABLE IF NOT EXISTS `roster_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `roster_id` int unsigned NOT NULL DEFAULT 0 COMMENT '名录ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号快照',
  `reason` varchar(200) NOT NULL DEFAULT '' COMMENT '入选原因(如: 2026-10-27 15:00 前有签到记录)',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roster_user` (`roster_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='名录明细表';

-- 10) 白名单（优先购资格载体）
CREATE TABLE IF NOT EXISTS `whitelist` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID(匹配后回填)',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '适用藏品ID(0=全部)',
  `max_qty` int unsigned NOT NULL DEFAULT 1 COMMENT '限购数量',
  `expires` bigint NOT NULL DEFAULT 0 COMMENT '过期时间(0=永久)',
  `remark` varchar(100) NOT NULL DEFAULT '' COMMENT '备注(VIP大客户/内部员工/渠道合作)',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=停用,1=启用',
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '创建管理员',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '软删:0=否,1=是',
  PRIMARY KEY (`id`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_collection_id` (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='白名单表';

-- 11) 库存池动账流水（销毁/回收/置换占用/回补；销售扣减走 orders 不入此表）
CREATE TABLE IF NOT EXISTS `stock_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID',
  `type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '类型:1=销毁,2=回收,3=置换占用,4=置换回补',
  `num` int NOT NULL DEFAULT 0 COMMENT '变动数量(占用为正销毁亦为正,按type语义)',
  `ref` varchar(100) NOT NULL DEFAULT '' COMMENT '关联单据/说明',
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '操作管理员',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_collection_type` (`collection_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='库存池动账流水表';

-- 12) 转赠
CREATE TABLE IF NOT EXISTS `transfer` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `transfer_no` varchar(50) NOT NULL DEFAULT '' COMMENT '转赠单号',
  `hold_id` int unsigned NOT NULL DEFAULT 0 COMMENT '持仓ID',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID',
  `from_user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '转出用户',
  `to_user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '接收用户',
  `to_mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '接收手机号快照',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=待接收,1=已完成,2=已取消',
  `done_time` bigint NULL DEFAULT NULL COMMENT '完成时间',
  `createtime` bigint NULL DEFAULT NULL COMMENT '发起时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '软删:0=否,1=是',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_transfer_no` (`transfer_no`),
  KEY `idx_from_user` (`from_user_id`),
  KEY `idx_to_user` (`to_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='转赠记录表';

-- 13) 置换
CREATE TABLE IF NOT EXISTS `swap` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `swap_no` varchar(50) NOT NULL DEFAULT '' COMMENT '置换单号',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `out_collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '付出藏品ID',
  `out_num` int unsigned NOT NULL DEFAULT 1 COMMENT '付出数量',
  `in_collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '换入藏品ID',
  `in_num` int unsigned NOT NULL DEFAULT 1 COMMENT '换入数量',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=待处理,1=已完成,2=已失败',
  `remark` varchar(200) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '软删:0=否,1=是',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_swap_no` (`swap_no`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='置换记录表';

-- 14) 第三方渠道配置 / 协议
CREATE TABLE IF NOT EXISTS `thirdparty` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `channel` varchar(20) NOT NULL DEFAULT 'pay' COMMENT '渠道类别:pay=支付',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道名称(如: 汇付天下钱包/易宝支付)',
  `enabled` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '启用:0=否,1=是(C端按启用渠道展示支付方式)',
  `config` text NULL COMMENT '渠道参数JSON(sys_id/merchant_id/private_key/cert_sn/gateway等)',
  `weigh` int NOT NULL DEFAULT 0 COMMENT '排序(越大越前)',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '软删:0=否,1=是',
  PRIMARY KEY (`id`),
  KEY `idx_channel` (`channel`,`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方渠道配置表';

CREATE TABLE IF NOT EXISTS `agreement` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '协议名称(用户服务协议/隐私政策)',
  `version` varchar(20) NOT NULL DEFAULT '' COMMENT '版本号(v2.1)',
  `content` mediumtext NULL COMMENT '内容(富文本)',
  `effective_time` bigint NULL DEFAULT NULL COMMENT '生效时间',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=下线,1=生效',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  `is_deleted` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '软删:0=否,1=是',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='协议版本表';

-- ------------------------------------------------------------
-- 三、运营参数种子（模块总开关 + 主题跟随）
-- ------------------------------------------------------------
INSERT INTO `setting` (`name`,`group`,`title`,`value`,`createtime`,`updatetime`) VALUES
('site_theme','brand','C端主题主色(后台主色跟随)','#4a8ff7',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('mod_invite','activity','邀请模块总开关(关闭后C端整体隐藏,场次配置保留)','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('mod_checkin','activity','签到模块总开关','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('mod_register','activity','注册模块总开关','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('mod_lottery','activity','抽奖模块总开关','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('mod_synthesis','activity','合成模块总开关','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- ------------------------------------------------------------
-- 四、新增后台菜单与按钮（admin_rule，ID 从 260 起避免冲突）
-- ------------------------------------------------------------
INSERT INTO `admin_rule` (`id`,`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(220,210,'menu','盲盒管理','nft/blindbox','nft/blindbox','fa fa-cube','tab','','/src/views/backend/nft/blindbox/index.vue',1,'none','',84,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(221,210,'menu','发放名录','nft/roster','nft/roster','fa fa-list-ul','tab','','/src/views/backend/nft/roster/index.vue',1,'none','',82,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(222,210,'menu','白名单','nft/whitelist','nft/whitelist','fa fa-id-card-o','tab','','/src/views/backend/nft/whitelist/index.vue',1,'none','',81,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(224,210,'menu','转赠管理','nft/transfer','nft/transfer','fa fa-exchange','tab','','/src/views/backend/nft/transfer/index.vue',1,'none','',80,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(225,210,'menu','置换管理','nft/swap','nft/swap','fa fa-random','tab','','/src/views/backend/nft/swap/index.vue',1,'none','',79,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(256,250,'menu','第三方渠道','nft/thirdparty','nft/thirdparty','fa fa-plug','tab','','/src/views/backend/nft/thirdparty/index.vue',1,'none','',67,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(257,250,'menu','协议管理','nft/agreement','nft/agreement','fa fa-file-text','tab','','/src/views/backend/nft/agreement/index.vue',1,'none','',66,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

INSERT INTO `admin_rule` (`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(220,'button','查看','nft/blindbox/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(220,'button','添加/编辑','nft/blindbox/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(220,'button','上架/下架','nft/blindbox/status','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(220,'button','奖池配置','nft/blindbox/prizes','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(220,'button','开盒记录','nft/blindbox/records','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(221,'button','查看','nft/roster/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(221,'button','生成名录','nft/roster/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(222,'button','查看','nft/whitelist/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(222,'button','导入/添加','nft/whitelist/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(224,'button','查看','nft/transfer/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(225,'button','查看','nft/swap/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(256,'button','查看','nft/thirdparty/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(256,'button','保存渠道','nft/thirdparty/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(257,'button','查看','nft/agreement/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(257,'button','保存协议','nft/agreement/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 抽签发售页面追加：码管理按钮（挂现有 219 抽签发售）
INSERT INTO `admin_rule` (`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(219,'button','码管理','nft/raffle/codes','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(219,'button','手动发码','nft/raffle/grantcode','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(219,'button','作废码','nft/raffle/voidcode','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
