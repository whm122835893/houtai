# 数据库设计文档 · 对接 zhenhai 版（v4 增量）

> 生成日期：2026-11-05
> 前置文档：`docs/开发约定.md`、`database/nft.sql`（v1~v3 已有 26 张 * 表）
> 关联仓库：`zhenhaiQZ`（C端 + 盛泰后端 shengtai-api，库 `shengtai`）、`qboxQZ`（第二 C 端）
> **核心决策（用户拍板）：zhenhai 已有的表一律不建，后续用我们的后台（BuildAdmin）替换 zhenhai 的 admin-web。**

---

## 1. 总体架构与库策略

```
zhenhaiQZ 后端(shengtai-api) ──► MySQL 库 shengtai（zhenhai 49 张业务表，保持不动）
                                      ▲
我们后台(BuildAdmin/ThinkPHP8) ───────┘ 同库接入：直接读写 zhenhai 表（模型指定真实表名）
                                      ＋ 新建无前缀增量表（zhenhai 没有，共 14 张，zhenhai 风格）
```

- **同库接入**：我们后台的「用户/藏品/订单/寄售」等模块直接操作 zhenhai 的 `users / items / nfts / orders / sell_orders` 等表，**不再经中间同步**，天然账本统一。
- **全部无前缀（zhenhai 流派）**：业务表一律裸表名（`user`、`collection`、`blindbox`…），与 zhenhai 的无前缀风格统一；已核对与 zhenhai 49 张表**零字面冲突**（zhenhai 用复数/不同词：`users`/`orders`/`notices`）。语义重复的表（`user`/`order`/`notice` 等）在 §2 复用切换后退役。
- **框架安装约定：表前缀留空**，BuildAdmin 系统表即为 `admin_rule` 等裸名；模型层 `$name`/`Db::name()` 已同步去前缀。
- **命名约定跟随现有 nft.sql**：`int unsigned AUTO_INCREMENT` 主键、`createtime/updatetime`（bigint Unix 秒）、金额一律**分**、`utf8mb4_unicode_ci`。新增表统一带 `is_deleted` 软删（原型回收站语义），存量表是否补软删另批处理。

---

## 2. 复用映射：退役的 * 表 → zhenhai 表

后台替换 zhenhai admin 后，以下我们自建的表**退役**（数据一次性迁移或直接废弃），改用 zhenhai 表：

| 退役表（*） | 对接 zhenhai 表 | 说明 |
|---|---|---|
| `user` | `users` + `user_profiles` + `user_wallets` | zhenhai 含实名/二级密码/雪花ID，字段更全 |
| `wallet_log` | `wallet_transactions` | |
| `category` | `item_categories` | |
| `collection` | `items`（发行）/ `item_authors` / `item_copyrights` | 优先购配置对应 items 的 `is_primary*` 字段族 |
| `order` | `orders` + `order_items` + `order_payments` | |
| `hold` | `nfts`（每个藏品实例） | 转赠/锁定对应 `nfts.status / sell_status` |
| `market_order` | `sell_orders` | |
| `market_buy` | `market_apply_orders`（求购）/ `market_trust_orders`（委托） | |
| `lottery_activity` / `_prize` / `_record` | `activity_lotteries` / `activity_lottery_prizes` / `activity_lottery_logs` | 转盘抽奖，zhenhai 已全 |
| `lottery_chance` | `activity_points_logs`（积分流水）+ 本文新增 `chance_log` | 抽奖机会流水 zhenhai 无，保留增量表 |
| `synthesis` / `_record` | `merge_recipes` / `merge_records` | 合成 |
| `notice` | `notices` + `notice_categories` | |
| `banner` | `banners` | |
| `oper_log` | `admin_operation_logs` | |

**保留我们自己的表**（zhenhai 没有对应物或结构不够用）：

| 保留表 | 原因 |
|---|---|
| `setting` | 运营参数/品牌键值，zhenhai 无结构化配置表（`common_rules` 是规则文案，不同物） |
| `invite_rule` / `invite_record` | 多场次邀请活动规则；zhenhai `user_invites` 仅存原始邀请关系，作底层关系表继续使用 |
| `checkin_rule` / `checkin_record` | zhenhai `activity_sign_records` 无阶梯奖励、无场次、无补签 |
| `register_log` | zhenhai 无注册礼包 |
| `raffle` / `raffle_apply` | 码制抽签模型，zhenhai 只有奖品池抽奖，完全不同 |
| `airdrop_task` | 空投任务（含撤销/重复策略），zhenhai 无 |

---

## 3. 新增增量表（14 张，v4）

> 详细 DDL 见 `database/nft-v4-zhenhai-delta.sql`，此处列用途与关键字段。

| # | 表 | 用途 | 关键字段 |
|---|-----|------|----------|
| 1 | `raffle_code` | 抽签码本体（报名/邀请/手动三种来源） | raffle_id, user_id, source, invite_from_user_id, code, status(0有效/1已中/2作废) |
| 2 | `act_session` | 多场次活动统一场次表（邀请/签到/注册三类） | type, title, start_time, end_time(0=常驻), status(人工开关，三态=开关×时间窗), config(JSON) |
| 3 | `chance_log` | 抽奖机会流水（签到/注册/持有/邀请/消耗藏品获得，抽奖消耗） | user_id, num(±), source, ref |
| 4 | `blindbox` | 盲盒 | price(分), total_supply, sold_count, **airdropped(空投独立账)**, limit_per_user |
| 5 | `blindbox_prize` | 盲盒奖池（含空盒条目） | box_id, collection_id(0=空盒), probability(万分比), quantity, sent |
| 6 | `blindbox_record` | 开盒记录 | box_id, user_id, prize_id, collection_id, hold_id |
| 7 | `airdrop_item` | 空投明细（逐人逐条，支持撤销/回收/补发对账） | task_id, user_id, target_type(1藏品/2盲盒), collection_id/blindbox_id, hold_id, status(0已撤销/1已发放/2失败) |
| 8 | `roster` | 发放名录（空投对象圈选：按签到/持有/消费/手工导入） | cond_type, cond_value, user_count |
| 9 | `roster_item` | 名录明细 | roster_id, user_id, mobile, reason |
| 10 | `whitelist` | 白名单（优先购资格载体） | mobile, user_id, collection_id(0=全部), max_qty, expires |
| 11 | `stock_log` | **库存池非销售动账流水**（销毁/回收/置换占用/回补），库存池可审计 | collection_id, type(1销毁/2回收/3置换占用/4置换回补), num, ref |
| 12 | `transfer` | 转赠 | transfer_no, hold_id, from_user_id, to_user_id, status |
| 13 | `swap` | 置换 | swap_no, out_collection_id/num, in_collection_id/num |
| 14 | `thirdparty` / `agreement` | 第三方渠道配置（支付多渠道 JSON）/ 协议版本管理 | channel, config(JSON) / name, version, content |

### 对存量表的 ALTER（4 张）

| 表 | 新增列 | 说明 |
|---|---|---|
| `raffle` | base_mode, code_fmt, code_prefix, invite_on, invite_per, invite_cap, invite_new, draw_mode, max_wins, whitelist | 原型已锁定的码配置三组参数（发码模式/码格式/邀请得码/开奖模式） |
| `airdrop_task` | target_type(1藏品/2盲盒), blindbox_id, claim_pending | 支持空投盲盒 + 领取式待到账计数 |
| `invite_rule` / `checkin_rule` | activity_id | 规则挂到多场次的场次 ID（0=常驻默认场） |
| `checkin_record` / `invite_record` / `register_log` | activity_id | 记录归属场次（多场次数据模型） |

### setting 种子（模块总开关 + 主题）

`setting` 追加：`mod_invite / mod_checkin / mod_register / mod_lottery / mod_synthesis`（活动中心五个模块总开关，group=activity）、`site_theme`（brand 组，C 端主色跟随）。

---

## 4. 库存池对账口径（基于复用表 + 增量表）

> 公式沿用原型锁定版本，字段全部可追溯到真实表：

```
库存池(藏品) = items.issue_quantity              -- 发行量
             − items.buy_quantity                -- 已售
             − 空投净发放                          -- airdrop_item: status=1 − status=0（target_type=1）
             − 盲盒占用                           -- Σ blindbox_prize(quantity − sent)（该藏品在池中的未开出部分）
             − 已销毁 + 已回收                    -- stock_log type=1/2
             − 置换空投                           -- stock_log type=3 − type=4
```

盲盒剩余可投 = `total_supply − sold_count − airdropped`（独立账，不吃藏品池）。

---

## 5. 与两个 C 端的对接映射

| 开放能力 | 后台数据来源 | qboxQZ（路由已预留） | zhenhaiQZ |
|---|---|---|---|
| 空投到账查询 | `airdrop_item` | `airdrop/records`、`airdrop/eligibilities` | 无（新增页面） |
| 抽签报名/我的码/开奖结果 | `raffle_apply` + `raffle_code` | `draw/*`（后端未实现，正好补位） | 无（新增页面） |
| 签到（多场次） | `checkin_record`(挂 activity_id) | `addons/signin/*`（后端未实现） | `/activity/sign`（数据源切换或双写） |
| 邀请关系 | zhenhai `user_invites` + `invite_record` | `user/invite_records` | `/user/invite` |
| 品牌/主题/模块开关下发 | `setting` | `site/settings`、`index/getConfig` | `/common/rule` 类配置接口 |
| 盲盒购买/开盒 | `blindbox*`（库存扣减在后台账本） | `box/blind_box/buy`、`openbox`（已有实现，改调后台） | 无 |

---

## 6. 迁移步骤建议

1. 在 `shengtai` 库执行 `nft-v4-zhenhai-delta.sql`（新表 + ALTER + 种子 + 菜单）
2. 后台 BuildAdmin 的模型逐步切换到 zhenhai 真实表名（先只读展示，再切写路径）
3. 存量演示数据（原型 S.db）按 §2 映射一次性导入 zhenhai 表
4. 退役表保留 30 天观察期后归档删除
5. C 端对接按 §5 顺序联调：先 qbox 空投/抽签，再 zhenhai 签到/邀请切换
