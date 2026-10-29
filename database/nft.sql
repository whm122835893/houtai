-- ============================================================
-- 数字藏品平台 · BuildAdmin 业务扩展 SQL
-- 适用：BuildAdmin v2.x（ThinkPHP8 + Vue3 + Element Plus）
-- 说明：
--   1. 默认表前缀为 ba_，如果你的项目使用了其它前缀，请全局替换。
--   2. 本脚本包含：业务数据表 + 后台菜单/权限规则(admin_rule)。
--   3. 金额字段一律以「分」落库，前端展示与输入均使用「元」。
--   4. 时间字段沿用 BuildAdmin 惯例（bigint Unix 时间戳）。
-- ============================================================

-- ------------------------------------------------------------
-- 一、业务数据表
-- ------------------------------------------------------------

-- 平台会员（C端用户）
CREATE TABLE IF NOT EXISTS `ba_nft_user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
  `username` varchar(50) NOT NULL DEFAULT '' COMMENT '账号',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `email` varchar(100) NOT NULL DEFAULT '' COMMENT '邮箱',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像',
  `real_name` varchar(50) NOT NULL DEFAULT '' COMMENT '实名姓名',
  `idcard` varchar(30) NOT NULL DEFAULT '' COMMENT '身份证号',
  `invite_code` varchar(20) NOT NULL DEFAULT '' COMMENT '我的邀请码',
  `inviter_id` int unsigned NOT NULL DEFAULT 0 COMMENT '邀请人ID',
  `balance` bigint NOT NULL DEFAULT 0 COMMENT '钱包余额(分)',
  `frozen` bigint NOT NULL DEFAULT 0 COMMENT '冻结金额(分)',
  `total_income` bigint NOT NULL DEFAULT 0 COMMENT '累计收入(分)',
  `total_expense` bigint NOT NULL DEFAULT 0 COMMENT '累计支出(分)',
  `order_count` int unsigned NOT NULL DEFAULT 0 COMMENT '订单数',
  `hold_count` int unsigned NOT NULL DEFAULT 0 COMMENT '持仓数',
  `tags` varchar(255) NOT NULL DEFAULT '' COMMENT '用户标签(逗号分隔,如vip,内测,达人)',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=禁用,1=启用',
  `last_login_time` bigint NULL DEFAULT NULL COMMENT '最近登录时间',
  `createtime` bigint NULL DEFAULT NULL COMMENT '注册时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_invite_code` (`invite_code`),
  KEY `idx_inviter_id` (`inviter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='平台会员表';

-- 钱包流水
CREATE TABLE IF NOT EXISTS `ba_nft_wallet_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '会员ID',
  `type` enum('recharge','consume','income','expense','freeze','unfreeze','refund','admin_adjust') NOT NULL DEFAULT 'income' COMMENT '类型:recharge=充值,consume=消费,income=收款,expense=支出,freeze=冻结,unfreeze=解冻,refund=退款,admin_adjust=后台调整',
  `related_no` varchar(50) NOT NULL DEFAULT '' COMMENT '关联单号',
  `amount` bigint NOT NULL DEFAULT 0 COMMENT '变动金额(分,正收入负支出)',
  `before` bigint NOT NULL DEFAULT 0 COMMENT '变动前余额(分)',
  `after` bigint NOT NULL DEFAULT 0 COMMENT '变动后余额(分)',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_related_no` (`related_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钱包流水表';

-- 藏品分类
CREATE TABLE IF NOT EXISTS `ba_nft_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称',
  `weigh` int NOT NULL DEFAULT 0 COMMENT '排序(越大越前)',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=停用,1=启用',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='藏品分类表';

-- 藏品
CREATE TABLE IF NOT EXISTS `ba_nft_collection` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '藏品名称',
  `category_id` int unsigned NOT NULL DEFAULT 0 COMMENT '分类ID',
  `author` varchar(50) NOT NULL DEFAULT '' COMMENT '作者',
  `cover` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
  `price` bigint NOT NULL DEFAULT 0 COMMENT '售价(分)',
  `market_price` bigint NOT NULL DEFAULT 0 COMMENT '市场参考价(分)',
  `stock` int unsigned NOT NULL DEFAULT 0 COMMENT '发行量',
  `sold` int unsigned NOT NULL DEFAULT 0 COMMENT '已售数量',
  `detail` mediumtext NULL COMMENT '图文详情(富文本)',
  `resale` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '二级市场:0=关闭,1=开启',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=未发售,1=已排期,2=发售中,3=已下架,4=已售罄',
  `sale_time` bigint NULL DEFAULT NULL COMMENT '开售时间',
  `sale_end_time` bigint NULL DEFAULT NULL COMMENT '发售结束时间(NULL=不限，到期自动置为已售罄，C端仍展示仅停止购买)',
  `resale_price_limit` bigint NOT NULL DEFAULT 0 COMMENT '寄售限价(分，开启二级市场必填，挂单价不得超过)',
  `pre_sale_time` bigint NULL DEFAULT NULL COMMENT '优先购开始时间(窗口起点,至开售时间截止)',
  `pre_rule_type` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '优先购资格:0=不启用,1=持有指定藏品,2=白名单用户',
  `pre_rule_value` varchar(1000) NOT NULL DEFAULT '' COMMENT '优先购参数(凭证藏品ID或用户ID,逗号分隔)',
  `pre_limit` int unsigned NOT NULL DEFAULT 1 COMMENT '优先购每人限购数量',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='藏品表';

-- 订单（一级购/市场购）
CREATE TABLE IF NOT EXISTS `ba_nft_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单号',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '买家会员ID',
  `type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '类型:1=一级购,2=市场购',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID',
  `hold_id` int unsigned NOT NULL DEFAULT 0 COMMENT '关联持仓ID(市场购)',
  `num` int unsigned NOT NULL DEFAULT 1 COMMENT '数量',
  `amount` bigint NOT NULL DEFAULT 0 COMMENT '实付金额(分)',
  `pay_type` varchar(20) NOT NULL DEFAULT '' COMMENT '支付方式:wechat/alipay/balance',
  `pay_no` varchar(64) NOT NULL DEFAULT '' COMMENT '第三方支付单号',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=待支付,1=已支付,2=已取消,3=已退款',
  `paytime` bigint NULL DEFAULT NULL COMMENT '支付时间',
  `createtime` bigint NULL DEFAULT NULL COMMENT '下单时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_collection_id` (`collection_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单表';

-- NFT 持仓（每一个数字藏品凭证）
CREATE TABLE IF NOT EXISTS `ba_nft_hold` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `sn` varchar(30) NOT NULL DEFAULT '' COMMENT '藏品编号',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '所属藏品ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '持有人ID',
  `source` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '来源:1=一级购,2=市场购,3=定向发放,4=抽奖,5=合成,6=邀请奖励',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=持有,1=寄售中,2=已锁定',
  `sell_order_id` int unsigned NOT NULL DEFAULT 0 COMMENT '关联寄售单ID',
  `claim_status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '领取状态:0=待领取,1=已领取(空投领取式到账用)',
  `ref_id` int unsigned NOT NULL DEFAULT 0 COMMENT '来源业务ID(空投任务ID等)',
  `mint_time` bigint NULL DEFAULT NULL COMMENT '铸造时间',
  `createtime` bigint NULL DEFAULT NULL COMMENT '获得时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sn` (`sn`),
  KEY `idx_collection_id` (`collection_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='NFT持仓表';

-- 市场寄售挂单
CREATE TABLE IF NOT EXISTS `ba_nft_market_order` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '挂单号',
  `seller_id` int unsigned NOT NULL DEFAULT 0 COMMENT '卖家会员ID',
  `hold_id` int unsigned NOT NULL DEFAULT 0 COMMENT '持仓ID',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID',
  `price` bigint NOT NULL DEFAULT 0 COMMENT '单价(分)',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=在售,1=已售,2=已下架',
  `buyer_id` int unsigned NOT NULL DEFAULT 0 COMMENT '买家会员ID',
  `soldtime` bigint NULL DEFAULT NULL COMMENT '售出时间',
  `createtime` bigint NULL DEFAULT NULL COMMENT '挂单时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_seller_id` (`seller_id`),
  KEY `idx_collection_id` (`collection_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='市场寄售挂单表';

-- 市场求购单
CREATE TABLE IF NOT EXISTS `ba_nft_market_buy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '求购人ID',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID',
  `price` bigint NOT NULL DEFAULT 0 COMMENT '求购单价(分)',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=求购中,1=已完成,2=已取消',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_collection_id` (`collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='市场求购单表';

-- 定向发放任务
CREATE TABLE IF NOT EXISTS `ba_nft_airdrop_task` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '任务名称',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID',
  `num` int unsigned NOT NULL DEFAULT 0 COMMENT '发放份数',
  `rule_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '目标规则:1=指定用户,2=全部用户,3=按邀请数,4=持有指定藏品,5=持仓门槛,6=白名单导入,7=按消费金额,8=按注册时间,9=随机抽样,10=按用户标签,11=邀请排行',
  `rule_value` varchar(2000) NOT NULL DEFAULT '' COMMENT '规则参数(按rule_type含义不同)',
  `success_num` int unsigned NOT NULL DEFAULT 0 COMMENT '成功数',
  `fail_num` int unsigned NOT NULL DEFAULT 0 COMMENT '失败数',
  `revoke_num` int unsigned NOT NULL DEFAULT 0 COMMENT '已撤销数',
  `claim_mode` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '到账方式:0=直接到账,1=需用户领取',
  `dup_policy` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '重复拥有策略:1=跳过,2=换发抽奖次数,3=强制叠加',
  `dup_reward` int unsigned NOT NULL DEFAULT 0 COMMENT '重复换发抽奖次数(策略=2)',
  `plan_time` bigint NULL DEFAULT NULL COMMENT '定时空投时间(到点由定时任务执行)',
  `reason` varchar(255) NOT NULL DEFAULT '' COMMENT '空投凭证文案(C端展示发放原因)',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=待发放,1=发放中,2=完成',
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '创建管理员',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_collection_id` (`collection_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='定向发放任务表';

-- 抽奖活动
CREATE TABLE IF NOT EXISTS `ba_nft_lottery_activity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '活动名称',
  `chance_rules` text NULL COMMENT '次数获取配置(JSON): daily_free签到/register注册/hold持有/invite邀请/consume消耗藏品',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=关闭,1=开启',
  `start_time` bigint NULL DEFAULT NULL COMMENT '开始时间',
  `end_time` bigint NULL DEFAULT NULL COMMENT '结束时间',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖活动表';

-- 抽奖次数流水
CREATE TABLE IF NOT EXISTS `ba_nft_lottery_chance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `activity_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动ID(0=通用)',
  `num` int NOT NULL DEFAULT 0 COMMENT '变动次数(正增负减)',
  `source` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '来源:1=每日签到,2=新人注册,3=持有藏品,4=邀请好友,5=消耗藏品,6=抽奖消耗,7=后台发放',
  `ref` varchar(100) NOT NULL DEFAULT '' COMMENT '关联说明(藏品名/订单号等)',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_activity` (`user_id`,`activity_id`),
  KEY `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖次数流水表';

-- 抽奖奖池
CREATE TABLE IF NOT EXISTS `ba_nft_lottery_prize` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `activity_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '奖品名称',
  `type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '类型:1=藏品,2=抽奖次数,3=谢谢参与',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID(类型=藏品)',
  `num` int unsigned NOT NULL DEFAULT 0 COMMENT '抽奖次数(类型=抽奖次数)',
  `probability` int unsigned NOT NULL DEFAULT 0 COMMENT '概率(万分比)',
  `stock` int unsigned NOT NULL DEFAULT 0 COMMENT '库存',
  `sent` int unsigned NOT NULL DEFAULT 0 COMMENT '已发出',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_activity_id` (`activity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖奖池表';

-- 抽奖中奖记录
CREATE TABLE IF NOT EXISTS `ba_nft_lottery_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `activity_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `prize_id` int unsigned NOT NULL DEFAULT 0 COMMENT '奖品ID',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_activity_id` (`activity_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽奖记录表';

-- 合成活动
CREATE TABLE IF NOT EXISTS `ba_nft_synthesis` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '活动名称',
  `target_collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '目标藏品ID',
  `materials` text NULL COMMENT '材料配置JSON:[{"collection_id":1,"num":2}]',
  `limit_num` int unsigned NOT NULL DEFAULT 0 COMMENT '限合成次数(0=不限)',
  `done_num` int unsigned NOT NULL DEFAULT 0 COMMENT '已完成次数',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=关闭,1=开启',
  `start_time` bigint NULL DEFAULT NULL COMMENT '开始时间',
  `end_time` bigint NULL DEFAULT NULL COMMENT '结束时间',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_target_collection_id` (`target_collection_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合成活动表';

-- 合成记录
CREATE TABLE IF NOT EXISTS `ba_nft_synthesis_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `synthesis_id` int unsigned NOT NULL DEFAULT 0 COMMENT '活动ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:1=成功,2=失败',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_synthesis_id` (`synthesis_id`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合成记录表';

-- 邀请奖励规则
CREATE TABLE IF NOT EXISTS `ba_nft_invite_rule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `invite_num` int unsigned NOT NULL DEFAULT 0 COMMENT '邀请人数门槛',
  `reward_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '奖励类型:1=藏品,2=抽奖次数,3=优先购资格',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '奖励藏品ID(类型=藏品)',
  `pre_collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '优先购目标藏品ID(类型=优先购资格,未发售可预配置)',
  `reward_num` int unsigned NOT NULL DEFAULT 0 COMMENT '奖励数量(类型=2抽奖次数/3优先购可用次数)',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=停用,1=启用',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请奖励规则表';

-- 邀请记录
CREATE TABLE IF NOT EXISTS `ba_nft_invite_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `inviter_id` int unsigned NOT NULL DEFAULT 0 COMMENT '邀请人ID',
  `invitee_id` int unsigned NOT NULL DEFAULT 0 COMMENT '被邀请人ID',
  `reward_status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '奖励状态:0=未达标,1=已发放',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_inviter_id` (`inviter_id`),
  KEY `idx_invitee_id` (`invitee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请记录表';

-- 公告
CREATE TABLE IF NOT EXISTS `ba_nft_notice` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '标题',
  `category` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '分类:1=公告,2=活动,3=系统',
  `content` mediumtext NULL COMMENT '内容(富文本)',
  `is_top` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '置顶:0=否,1=是',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=隐藏,1=显示',
  `publish_time` bigint NULL DEFAULT NULL COMMENT '发布时间',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公告表';

-- 轮播
CREATE TABLE IF NOT EXISTS `ba_nft_banner` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `image` varchar(255) NOT NULL DEFAULT '' COMMENT '图片',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '跳转链接',
  `weigh` int NOT NULL DEFAULT 0 COMMENT '排序(越大越前)',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态:0=停用,1=启用',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='轮播表';

-- 运营参数（键值对，分组存储；品牌信息也在此表）
CREATE TABLE IF NOT EXISTS `ba_nft_setting` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '参数名',
  `group` varchar(30) NOT NULL DEFAULT 'base' COMMENT '分组:brand=品牌,trade=交易,activity=活动,reward=奖励',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '参数标题',
  `value` text NULL COMMENT '参数值',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='运营参数表';

-- 管理员操作日志（业务审计）
CREATE TABLE IF NOT EXISTS `ba_nft_oper_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '操作管理员ID',
  `admin_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人',
  `module` varchar(50) NOT NULL DEFAULT '' COMMENT '模块',
  `action` varchar(50) NOT NULL DEFAULT '' COMMENT '动作',
  `target` varchar(100) NOT NULL DEFAULT '' COMMENT '目标(如 藏品#12)',
  `detail` varchar(1000) NOT NULL DEFAULT '' COMMENT '详情',
  `ip` varchar(50) NOT NULL DEFAULT '' COMMENT 'IP',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  KEY `idx_admin_id` (`admin_id`),
  KEY `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作日志表';

-- ------------------------------------------------------------
-- 二、后台菜单与权限规则（ba_admin_rule）
-- 说明：ID 从 200 开始，避免与 BuildAdmin 安装数据冲突。
--   type: menu_dir=菜单目录 menu=菜单项 button=页面按钮
--   name: 权限规则名（控制器路径/动作），同时也是前端路由名
--   component: 前端组件路径
--   只读权限 = 仅授权 index/view 类按钮；写权限需授权 add/edit/del 等。
-- ------------------------------------------------------------

-- 顶级目录：概览
INSERT INTO `ba_admin_rule` (`id`,`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(200,0,'menu_dir','概览','nft','nft','fa fa-home',NULL,'','',0,'none','',100,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(201,200,'menu','数据仪表盘','nft/dashboard','nft/dashboard','fa fa-dashboard','tab','','/src/views/backend/nft/dashboard/index.vue',1,'none','平台核心数据概览',99,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 顶级目录：业务管理
INSERT INTO `ba_admin_rule` (`id`,`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(210,0,'menu_dir','业务管理','nft/business','nft/business','fa fa-briefcase',NULL,'','',0,'none','',98,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(211,210,'menu','用户管理','nft/user','nft/user','fa fa-user','tab','','/src/views/backend/nft/user/index.vue',1,'none','',90,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(212,210,'menu','钱包流水','nft/wallet','nft/wallet','fa fa-money','tab','','/src/views/backend/nft/wallet/index.vue',1,'none','',89,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(213,210,'menu','藏品管理','nft/collection','nft/collection','fa fa-gem','tab','','/src/views/backend/nft/collection/index.vue',1,'none','',88,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(214,210,'menu','分类管理','nft/category','nft/category','fa fa-tags','tab','','/src/views/backend/nft/category/index.vue',1,'none','',87,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(215,210,'menu','订单管理','nft/order','nft/order','fa fa-file-text-o','tab','','/src/views/backend/nft/order/index.vue',1,'none','',86,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(216,210,'menu','市场寄售','nft/market','nft/market','fa fa-exchange','tab','','/src/views/backend/nft/market/index.vue',1,'none','',85,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(217,210,'menu','持仓查询','nft/hold','nft/hold','fa fa-cubes','tab','','/src/views/backend/nft/hold/index.vue',1,'none','',84,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(218,210,'menu','定向发放','nft/airdrop','nft/airdrop','fa fa-send-o','tab','','/src/views/backend/nft/airdrop/index.vue',1,'none','',83,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 顶级目录：活动运营
INSERT INTO `ba_admin_rule` (`id`,`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(230,0,'menu_dir','活动运营','nft/activity','nft/activity','fa fa-magic',NULL,'','',0,'none','',97,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(231,230,'menu','抽奖活动','nft/lottery','nft/lottery','fa fa-gift','tab','','/src/views/backend/nft/lottery/index.vue',1,'none','',80,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(232,230,'menu','合成活动','nft/synthesis','nft/synthesis','fa fa-flask','tab','','/src/views/backend/nft/synthesis/index.vue',1,'none','',79,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(233,230,'menu','邀请有礼','nft/invite','nft/invite','fa fa-handshake-o','tab','','/src/views/backend/nft/invite/index.vue',1,'none','',78,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 顶级目录：内容运营
INSERT INTO `ba_admin_rule` (`id`,`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(240,0,'menu_dir','内容运营','nft/content','nft/content','fa fa-newspaper-o',NULL,'','',0,'none','',96,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(241,240,'menu','公告管理','nft/notice','nft/notice','fa fa-bullhorn','tab','','/src/views/backend/nft/notice/index.vue',1,'none','',75,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(242,240,'menu','轮播管理','nft/banner','nft/banner','fa fa-image','tab','','/src/views/backend/nft/banner/index.vue',1,'none','',74,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 顶级目录：系统
INSERT INTO `ba_admin_rule` (`id`,`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(250,0,'menu_dir','系统','nft/system','nft/system','fa fa-cog',NULL,'','',0,'none','',95,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(251,250,'menu','运营参数','nft/setting','nft/setting','fa fa-sliders','tab','','/src/views/backend/nft/setting/index.vue',1,'none','',70,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(252,250,'menu','数据清理','nft/cleanup','nft/cleanup','fa fa-trash-o','tab','','/src/views/backend/nft/cleanup/index.vue',1,'none','',69,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(253,250,'menu','日志审计','nft/operlog','nft/operlog','fa fa-shield','tab','','/src/views/backend/nft/operlog/index.vue',1,'none','',68,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 页面按钮权限（各页面通用 index + 写操作按钮）
INSERT INTO `ba_admin_rule` (`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
-- 用户管理
(211,'button','查看','nft/user/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(211,'button','添加','nft/user/add','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(211,'button','编辑','nft/user/edit','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(211,'button','删除','nft/user/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(211,'button','禁用/启用','nft/user/status','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 钱包流水
(212,'button','查看','nft/wallet/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(212,'button','查看流水明细','nft/wallet/logs','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(212,'button','余额调整','nft/wallet/adjust','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 藏品管理
(213,'button','查看','nft/collection/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(213,'button','添加','nft/collection/add','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(213,'button','编辑','nft/collection/edit','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(213,'button','删除','nft/collection/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(213,'button','开售','nft/collection/sale','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(213,'button','下架','nft/collection/off','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(213,'button','停止发售','nft/collection/soldout','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 分类管理
(214,'button','查看','nft/category/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(214,'button','添加','nft/category/add','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(214,'button','编辑','nft/category/edit','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(214,'button','删除','nft/category/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 订单管理
(215,'button','查看','nft/order/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(215,'button','详情','nft/order/detail','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 市场寄售
(216,'button','查看','nft/market/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(216,'button','下架','nft/market/off','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(216,'button','查看求购单','nft/market/buys','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 持仓查询
(217,'button','查看','nft/hold/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 定向发放
(218,'button','查看','nft/airdrop/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(218,'button','创建任务','nft/airdrop/add','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(218,'button','执行/重试','nft/airdrop/retry','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(218,'button','撤销批次','nft/airdrop/revoke','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(218,'button','删除任务','nft/airdrop/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 抽奖活动
(231,'button','查看','nft/lottery/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(231,'button','添加/编辑活动','nft/lottery/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(231,'button','删除活动','nft/lottery/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(231,'button','奖池配置','nft/lottery/prizes','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(231,'button','中奖记录','nft/lottery/records','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(231,'button','次数流水','nft/lottery/chances','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(231,'button','手动补发次数','nft/lottery/grant','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 合成活动
(232,'button','查看','nft/synthesis/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(232,'button','添加/编辑活动','nft/synthesis/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(232,'button','删除活动','nft/synthesis/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(232,'button','合成记录','nft/synthesis/records','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 邀请有礼
(233,'button','查看','nft/invite/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(233,'button','规则保存','nft/invite/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(233,'button','删除规则','nft/invite/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(233,'button','邀请记录','nft/invite/records','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 活动中心
(236,230,'menu','活动中心','nft/activity','nft/activity','fa fa-flag','tab','','/src/views/backend/nft/activity/index.vue',1,'none','',83,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(236,'button','查看','nft/activity/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(236,'button','开启/关闭活动','nft/activity/toggle','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 公告管理
(241,'button','查看','nft/notice/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(241,'button','添加','nft/notice/add','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(241,'button','编辑','nft/notice/edit','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(241,'button','删除','nft/notice/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 轮播管理
(242,'button','查看','nft/banner/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(242,'button','添加','nft/banner/add','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(242,'button','编辑','nft/banner/edit','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(242,'button','删除','nft/banner/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 运营参数
(251,'button','查看','nft/setting/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(251,'button','保存','nft/setting/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 数据清理
(252,'button','查看','nft/cleanup/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(252,'button','清理','nft/cleanup/clean','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
-- 日志审计
(253,'button','查看','nft/operlog/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- ------------------------------------------------------------
-- 三、运营参数初始数据
-- ------------------------------------------------------------
INSERT INTO `ba_nft_setting` (`name`,`group`,`title`,`value`,`createtime`,`updatetime`) VALUES
('site_logo','brand','平台Logo','/static/images/logo.png',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('site_name','brand','平台名称','灵境数藏',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('sell_fee_rate','trade','寄售手续费率(%)','5',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('order_auto_cancel','trade','订单自动取消(分钟)','15',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('market_open','trade','二级市场开关','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('lottery_daily_times','activity','每日抽奖次数','3',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('synthesis_daily_times','activity','每日合成次数','5',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('invite_reward_enable','reward','邀请奖励开关','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('register_reward_chance','reward','注册奖励抽奖次数','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- ------------------------------------------------------------
-- 四、签到活动 / 注册活动（v2 追加）
-- ------------------------------------------------------------

-- 签到阶梯奖励规则
CREATE TABLE IF NOT EXISTS `ba_nft_checkin_rule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `day_num` int unsigned NOT NULL DEFAULT 0 COMMENT '连续签到天数',
  `reward_type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '奖励类型:1=抽奖次数,2=藏品,3=优先购资格',
  `reward_num` int unsigned NOT NULL DEFAULT 1 COMMENT '奖励数量(次数/藏品份数/优先购次数)',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '奖励藏品ID(类型=藏品)',
  `pre_collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '优先购目标藏品ID(类型=优先购资格,未发售可预配置)',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_day_num` (`day_num`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='签到阶梯奖励规则表';

-- 签到记录
CREATE TABLE IF NOT EXISTS `ba_nft_checkin_record` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `checkin_date` date NOT NULL COMMENT '签到日期',
  `continuous_days` int unsigned NOT NULL DEFAULT 1 COMMENT '连续签到天数',
  `reward` varchar(200) NOT NULL DEFAULT '' COMMENT '获得奖励快照(如: 1次抽奖+锦鲤剪纸x1)',
  `is_repair` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '是否补签:0=否,1=是',
  `createtime` bigint NULL DEFAULT NULL COMMENT '时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_date` (`user_id`,`checkin_date`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='签到记录表';

-- 注册奖励发放记录
CREATE TABLE IF NOT EXISTS `ba_nft_register_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '新用户ID',
  `reward` varchar(200) NOT NULL DEFAULT '' COMMENT '新人礼包快照(如: 1次抽奖+锦鲤剪纸x1+优先购2次)',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '奖励藏品ID',
  `inviter_id` int unsigned NOT NULL DEFAULT 0 COMMENT '邀请人ID',
  `inviter_reward` varchar(100) NOT NULL DEFAULT '' COMMENT '邀请人奖励快照',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=待发放,1=已发放',
  `createtime` bigint NULL DEFAULT NULL COMMENT '注册时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_inviter_id` (`inviter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='注册奖励发放记录表';

-- 菜单（挂载在 230 活动运营目录下）
INSERT INTO `ba_admin_rule` (`id`,`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(234,230,'menu','签到活动','nft/checkin','nft/checkin','fa fa-calendar-check-o','tab','','/src/views/backend/nft/checkin/index.vue',1,'none','',82,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(235,230,'menu','注册活动','nft/register','nft/register','fa fa-user-plus','tab','','/src/views/backend/nft/register/index.vue',1,'none','',81,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

INSERT INTO `ba_admin_rule` (`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(234,'button','查看','nft/checkin/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(234,'button','保存规则','nft/checkin/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(235,'button','查看','nft/register/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(235,'button','保存配置','nft/register/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- 签到/注册参数初始数据
INSERT INTO `ba_nft_setting` (`name`,`group`,`title`,`value`,`createtime`,`updatetime`) VALUES
('ck_enabled','checkin','签到活动开关','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('ck_daily_type','checkin','每日签到奖励类型:1=抽奖次数,2=藏品,3=优先购资格','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('ck_daily_num','checkin','每日签到奖励数量','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('ck_daily_collection_id','checkin','每日签到奖励藏品ID(类型=藏品)','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('ck_daily_pre_collection_id','checkin','每日签到优先购目标藏品ID(类型=优先购资格,未发售可预配置)','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('ck_repair_limit','checkin','每月补签次数上限','3',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_enabled','register','注册活动开关','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_chance','register','注册奖励抽奖次数','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_lot_activity_id','register','注册奖励绑定抽奖活动ID(0=通用,绑定场次未开启时次数待生效)','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_collection_id','register','注册赠送藏品ID','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_pre_num','register','新人优先购资格次数','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_pre_collection_id','register','优先购资格目标藏品ID(可预配置未发售藏品,藏品开售后优先购期生效)','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_inviter_type','register','邀请人奖励类型:1=抽奖次数,2=藏品,3=优先购资格','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_inviter_num','register','邀请人奖励数量','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_inviter_collection_id','register','邀请人奖励藏品ID(类型=藏品)','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
('rg_inviter_pre_collection_id','register','邀请人优先购目标藏品ID(类型=优先购资格,未发售可预配置)','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

-- ------------------------------------------------------------
-- 五、抽签发售（v3 追加）
-- ------------------------------------------------------------

-- 抽签发售活动
CREATE TABLE IF NOT EXISTS `ba_nft_raffle` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `collection_id` int unsigned NOT NULL DEFAULT 0 COMMENT '藏品ID',
  `total` int unsigned NOT NULL DEFAULT 0 COMMENT '发售数量',
  `per_limit` int unsigned NOT NULL DEFAULT 1 COMMENT '中签者每人限购数量',
  `apply_start` bigint NULL DEFAULT NULL COMMENT '报名开始时间',
  `apply_end` bigint NULL DEFAULT NULL COMMENT '报名截止时间',
  `buy_deadline` bigint NULL DEFAULT NULL COMMENT '中签购买截止时间',
  `apply_count` int unsigned NOT NULL DEFAULT 0 COMMENT '报名人数(冗余)',
  `win_count` int unsigned NOT NULL DEFAULT 0 COMMENT '中签人数(冗余)',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=未开始,1=报名中,2=已截止待抽签,3=已抽签,4=已结束',
  `createtime` bigint NULL DEFAULT NULL COMMENT '创建时间',
  `updatetime` bigint NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_collection_id` (`collection_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽签发售活动表';

-- 抽签报名/中签记录
CREATE TABLE IF NOT EXISTS `ba_nft_raffle_apply` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `raffle_id` int unsigned NOT NULL DEFAULT 0 COMMENT '抽签活动ID',
  `user_id` int unsigned NOT NULL DEFAULT 0 COMMENT '用户ID',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '状态:0=待抽签,1=已中签,2=未中签',
  `win_num` int unsigned NOT NULL DEFAULT 0 COMMENT '中签可购数量',
  `paid` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '是否已购买:0=未购,1=已购',
  `createtime` bigint NULL DEFAULT NULL COMMENT '报名时间',
  PRIMARY KEY (`id`),
  KEY `idx_raffle_user` (`raffle_id`,`user_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='抽签报名记录表';

-- 菜单（业务管理组 210 下）
INSERT INTO `ba_admin_rule` (`id`,`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(219,210,'menu','抽签发售','nft/raffle','nft/raffle','fa fa-ticket','tab','','/src/views/backend/nft/raffle/index.vue',1,'none','',82,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

INSERT INTO `ba_admin_rule` (`pid`,`type`,`title`,`name`,`path`,`icon`,`menu_type`,`url`,`component`,`keepalive`,`extend`,`remark`,`weigh`,`status`,`updatetime`,`createtime`) VALUES
(219,'button','查看','nft/raffle/index','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(219,'button','保存活动','nft/raffle/save','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(219,'button','删除活动','nft/raffle/del','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(219,'button','执行抽签','nft/raffle/draw','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(219,'button','报名记录','nft/raffle/applys','','','',NULL,'',0,'none','',0,1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
