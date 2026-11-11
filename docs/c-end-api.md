# C 端开放 API 契约 — 用户认证 + 盲盒链路 v1.0

> 生成日期：2026-11-11
> 状态：**已实现并真实跑通**（模拟支付版）
> 基地址：`{BASE}/api`（BASE 为后端域名；本地调试 `http://127.0.0.1:8787/api`）
> 响应统一格式：`{"code":1成功/0业务失败/303需登录, "msg":"", "data":{}}`

---

## 1. 通用约定

| 项 | 约定 |
|----|------|
| 鉴权 | 请求头 `ba-user-token: {token}`（登录后返回，有效期 24h） |
| 金额 | **对外一律「元」字符串两位小数**（如 `"19.90"`）；库内为「分」 |
| 编码 | JSON / UTF-8；POST 用 `Content-Type: application/json` |
| 需登录标记 | 未登录返回 `code:303, data.type:"need login"` |
| URL 说明 | 控制器多词用下划线风格：`nft_auth` / `nft_box`（已豁免 SPA 跳转） |

## 2. 用户认证（NftAuth）

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| POST | `/api/nft_auth/register` | 注册 | 否 |
| POST | `/api/nft_auth/login` | 登录 | 否 |
| GET | `/api/nft_auth/profile` | 当前用户信息 | 是 |

**POST register** `{username, password, nickname?}`
- username 规则：字母开头，4~16 位字母/数字/下划线（后端正则 `^[a-zA-Z][a-zA-Z0-9_]{2,15}$`）
- password：6~32 位
- 返回 `data.token` + `data.userInfo`

**POST login** `{username, password}` → 同上。

**GET profile** → `data.userInfo`（id/username/nickname/avatar/money/score/hold_count 等）。

## 3. 盲盒链路（NftBox）

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| GET | `/api/nft_box/index` | 在售盲盒列表 | 否 |
| GET | `/api/nft_box/detail?id={box_id}` | 详情 + 奖池概率公示 | 否 |
| POST | `/api/nft_box/order` | 下单（创建待支付订单） | 是 |
| POST | `/api/nft_box/pay_mock` | **模拟支付并立即开盒** | 是 |
| GET | `/api/nft_box/records?page=&limit=` | 我的开盒记录 | 是 |

### 3.1 GET index
`data.list[]`: `{id, name, cover, description, price(元), total_supply, sold_count, limit_per_user, remaining, is_on_sale}`

### 3.2 GET detail
`data.box`: 同上 + `rule_text`
`data.prizes[]`: `{id, collection_id, name, probability(万分比), quantity, sent, prob_pct(百分比字符串), remaining}`

### 3.3 POST order `{box_id, num(1~99,默认1)}`
校验链：在售 → 库存（sold_count+num ≤ total_supply）→ 限购（limit_per_user，按用户已开出份数计）
返回 `data`: `{order_no, amount(元), num, pay_type:"mock"}`
订单状态：`0=待支付, 1=已支付`（`order` 表，`box_id` 列关联盲盒）

### 3.4 POST pay_mock `{order_no}` — 支付成功回调（模拟）
**购买即开盒**。事务内完成：锁单（幂等，重复支付拒绝）→ 锁盒 → 原子扣销量（`sold_count+num≤total_supply` 才放行）→ 逐份抽奖：
- 按万分比概率加权随机命中奖品
- 原子扣奖池 `sent+1`（`sent<quantity` 才放行，被并发抢走自动重抽，最多 5 轮）
- 全部奖品发完 → **降级空盒**（只写记录，不发持仓）
- 命中奖品 → 铸造 `hold` 持仓（SN 规则 `B{boxId}O{orderId}N{seq}`，source=1 一级购）→ 写 `blindbox_record`
- 订单置已支付（pay_type 当前为 `mock`，上线真实支付后替换为 `wechat` 等，回调逻辑不变）

返回 `data.results[]`: `{seq, is_empty, prize_name, collection_id, hold_id}`

### 3.5 GET records
`data.list[]`: `{id, box_id, box_name, prize_name, collection_id, collection_name, cover, hold_id, order_no, createtime, is_empty}`
`data.total`: 总条数。

## 4. 数据一致性保障（后端已实现）

| 风险 | 机制 |
|------|------|
| 超发（两个用户开到同一份） | 奖池原子更新 `sent<quantity` + 盒锁 + 本请求内本地计数 |
| 超卖 | 下单预检 + 支付时原子更新 `sold_count+num≤total_supply` |
| 重复支付 | 锁单 + 状态机校验，天然幂等 |
| 部分成功 | 全链路同事务，任一步失败整体回滚 |
| 库存凭空回补 | 后台重建奖池保留 `sent`（管理侧已有逻辑） |

## 5. 上线前待办（不在本版范围）

- [ ] 真实支付：替换 `pay_mock` → 微信/支付宝下单 + 异步回调（回调体复用 pay_mock 内部逻辑）
- [ ] 开盒结果动画素材（C 端前端消费 `results` 渲染）
- [ ] 概率公示页面合规文案（`rule_text` 已支持）
- [ ] C 端登录验证码/短信（如需合规，接入 NftAuth）
- [x] ~~活动参与 API（签到/抽奖/邀请）~~ → 已实现，见第 10~12 节
- [ ] 抽签发售用户侧（报名/中签/购付——raffle 系列表已就绪，开奖按运营节奏走后台）

## 10. 持仓 / 钱包 / 置换查询 — 已实现跑通

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| GET | `/api/nft_hold/index?status=&page=&limit=` | 我的持仓（含藏品信息/可售标记） | 是 |
| GET | `/api/nft_hold/detail?id=` | 持仓详情（含藏品档案） | 是 |
| GET | `/api/nft_wallet/index` | 钱包概览（余额/累计收支，元） | 是 |
| GET | `/api/nft_wallet/logs?type=&page=` | 流水列表（type 可选过滤） | 是 |
| GET | `/api/nft_swap_c/index?page=` | 我的置换记录（含换入换出藏品名） | 是 |

## 11. 签到（NftCheckin）— 已实现跑通

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| GET | `/api/nft_checkin/config` | 签到面板：开关/今日状态/连签天数/规则/本月日历 | 是 |
| POST | `/api/nft_checkin/sign` | 每日签到（连签奖励自动发放） | 是 |
| POST | `/api/nft_checkin/repair` | 补签 `{date: Y-m-d}`（ck_repair_limit 天内，补签不发奖） | 是 |
| GET | `/api/nft_checkin/records` | 签到记录 | 是 |

- 配置来源（后台「签到」模块）：`ck_enabled` / `ck_daily_type`(1=抽奖次数 2=藏品 3=优先购资格) / `ck_daily_num` / `ck_daily_collection_id` / `ck_daily_pre_collection_id` / `ck_repair_limit` + `checkin_rule` 连签奖励（day_num 连续天数）
- 奖励发放通用规则：藏品→铸造持仓（SN 前缀 CKD/CKR，sold+1 占库存池）；抽奖次数→发到最新进行中活动；优先购资格→写白名单
- 并发安全：用户行锁 + 当日唯一校验，双签不可能

## 12. 抽奖（NftLottery）— 已实现跑通

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| GET | `/api/nft_lottery/index` | 进行中/预告活动列表 | 否 |
| GET | `/api/nft_lottery/detail?id=` | 详情（奖池概率公示+剩余次数） | 否 |
| GET | `/api/nft_lottery/chances?id=` | 我的次数（**自动结算**每日免费/持有获得） | 是 |
| POST | `/api/nft_lottery/draw` | 开奖 `{id}`（消耗 1 次） | 是 |
| GET | `/api/nft_lottery/records?id=` | 我的中奖记录 | 是 |

- 次数获取 `chance_rules(JSON)`：`daily_free` 每日免费（chances 接口自动发）/ `register` 注册（NftAuth 自动发）/ `hold` 持有 ×N（chances 接口补差）/ `invite` 邀请（注册绑定后自动给邀请人）
- 开奖：万分比加权抽取 → 奖品原子扣库存（`stock>0` 才可扣，防超发）→ 发放（type1 藏品铸造持仓 SN 前缀 LP / type2 加次数 / type3 谢谢参与）→ 记录，全事务
- 奖品「再抽一次」(type2) 开出即返还 1 次次数

## 13. 邀请（NftInvite）— 已实现跑通

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| GET | `/api/nft_invite/index` | 邀请码/好友列表/规则进度（含可领次数） | 是 |
| POST | `/api/nft_invite/claim` | 领取达标奖励 `{rule_id}` | 是 |

- 绑定：注册时传 `invite_code`（NftAuth），自动建 `invite_record` 并给邀请人发抽奖次数（source=4）
- 邀请码：`user.invite_code`，8 位唯一（去易混淆字符），无则自动生成
- 达标模型：邀请人数每达 `invite_rule.invite_num` 门槛一次可领一次该规则奖励（标记落 `invite_record.rule_id`+`reward_status`，防重复领）
- 奖励类型 `invite_rule.reward_type`：**1=藏品 2=抽奖次数 3=优先购资格**（注意与签到枚举顺序不同）
- 总开关：`setting.invite_reward_enable`（后台可改）

## 6. 藏品直购（NftCollection）— 已实现跑通

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| GET | `/api/nft_collection/index` | 发售中列表 | 否 |
| GET | `/api/nft_collection/detail?id=` | 详情（含发售窗口/优先购信息） | 否 |
| POST | `/api/nft_collection/order` | 下单 `{collection_id, num}` | 是 |
| POST | `/api/nft_collection/pay_mock` | 模拟支付并铸造持仓 `{order_no}` | 是 |
| GET | `/api/nft_collection/orders` | 我的一级购订单 | 是 |

- 发售窗口：`sale_end_time` 过后拦截；库存口径与管理端一致（stock−sold−空投−置换−盲盒占用−销毁+回收）
- **优先购**：`pre_sale_time ~ sale_time` 窗口内仅资格用户可买（`pre_rule_type` 1=持有凭证藏品 / 2=指定用户ID），并受 `pre_limit` 限购
- 支付即铸造：SN 规则 `C{collectionId}O{orderId}N{seq}`，source=1 一级购；钱包记支出流水

## 7. 市场寄售（NftMarket）— 已实现跑通

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| GET | `/api/nft_market/index?collection_id=` | 在售寄售单 | 否 |
| GET | `/api/nft_market/mine` | 我的寄售单（含预估净入） | 是 |
| POST | `/api/nft_market/sell` | 挂单 `{hold_id, price(元)}` | 是 |
| POST | `/api/nft_market/buy` | 买入 `{id}` | 是 |
| POST | `/api/nft_market/cancel` | 撤单 `{id}`（卖家） | 是 |

- 三重开关：`market_open` 总闸 → `collection.resale` 单品开关 → 限价区间 `[resale_price_min, resale_price_limit]`
- 成交：手续费率实时读 `setting.sell_fee_rate`（后台运营参数可改），卖家净入 price−fee 记 income（**余额联动**），买家记 expense
- 持仓过户：hold → 买家，source=2 市场购，`sell_order_id` 关联寄售单；挂单期持仓锁定 status=1

## 8. 转赠（NftTransfer）— 已实现跑通

| Method | Path | 说明 | 鉴权 |
|--------|------|------|------|
| GET | `/api/nft_transfer/records` | 我的转赠记录（发出+收到） | 是 |
| POST | `/api/nft_transfer/create` | 发起 `{hold_id, to_mobile}` | 是 |
| POST | `/api/nft_transfer/accept` | 接收 `{transfer_no}`（接收方） | 是 |
| POST | `/api/nft_transfer/cancel` | 撤销 `{transfer_no}`（发起方） | 是 |

- 单品开关：`collection.transfer_enabled`（后台藏品编辑控制）；手续费 `setting.transfer_fee`（元，0=免费，后台可改），撤销自动退回
- 流程：发起 → 持仓锁定(status=2) → 接收过户 / 撤销回退；手机号定位接收方

## 9. 定时任务（运维）

```
php think nft:maintain          # 全部：售罄自动置位 + 待支付订单超时取消
php think nft:maintain soldout  # 仅售罄置位
php think nft:maintain orders   # 仅订单超时取消（读 setting.order_auto_cancel 分钟）
```

建议 crontab 每分钟一次。已验证：库存池归零 → status=4 售罄；超时订单 → status=2 取消。

## 10. 后续模块契约占位（待实现）

持仓查询（hold）、置换（swap 用户侧）、钱包流水查询（wallet_log）、活动参与（签到/抽奖/邀请，需先建开奖引擎）——表结构与管理接口就绪，按本契约风格补充即可。
