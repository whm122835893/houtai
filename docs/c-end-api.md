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

## 6. 后续模块契约占位（待实现）

藏品浏览（collection/detail）、市场寄售（market）、持仓（hold）、转赠（transfer）、置换（swap）、钱包流水（wallet）、活动（签到/抽奖/邀请）——表结构与后台管理接口已就绪，按本契约风格补充即可。
