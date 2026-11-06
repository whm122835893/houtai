# 数字藏品平台管理后台（BuildAdmin 版）

基于 **BuildAdmin v2.x**（ThinkPHP8 + Vue3 setup + TypeScript + Vite + Pinia + Element Plus）实现的数字藏品平台管理后台，共 **19 个页面**，采用「液态玻璃」设计系统（天空蓝画布 + 白色磨砂玻璃 + 主蓝强调 + 衬线斜体数字），**自适应 PC / 平板 / 手机**。

## 一、功能清单

| 分组 | 页面 | 说明 |
|---|---|---|
| 概览 | 数据仪表盘 | 4 指标卡、近 7 日订单纯 CSS 柱状趋势图（hover 提示）、累计成交额、最新订单 |
| 业务管理 | 用户管理 | 关键词/状态筛选、批量+单行删除、详情弹窗（钱包/持仓/订单）、禁用/启用、余额调整 |
| | 钱包流水 | 钱包列表（余额/冻结/累计收支）、展开流水明细（类型/单号/正负着色）、余额调整 |
| | 藏品管理 | 状态列（未发售/已排期/发售中/已下架/已售罄）、开售（起止时间）/下架/停止发售（售罄）、新增编辑（封面上传+富文本+二级市场开关） |
| | ↳ 售罄规则 | 售出达发行量或到达发售结束时间 → 自动置为「已售罄」；售罄在 C 端仍展示（浏览/持仓/二级市场入口），仅停止购买；只有手动「下架」才从 C 端隐藏 |
| | ↳ 优先购 | 藏品支持优先购窗口（优先购开始时间 → 开售时间），资格三种：持有指定藏品凭证 / 白名单用户 / 不启用，每人限购可配，窗口结束自动全员开放 |
| | 分类管理 | 排序、停用启用、被引用删除拦截（提示数量） |
| | 订单管理 | 一级购/市场购、类型与状态 tag、详情含支付信息 |
| | 抽签发售 | 报名期→截止抽签→中签购买期；中签名额=发售量÷每人限购，报名不足全中；随机抽签事务化、报名/中签记录可查；活动表 ba_nft_raffle + 报名表 ba_nft_raffle_apply |
| | 市场寄售 | 挂单列表（在售/已售/已下架）、下架（事务回滚持仓）、求购单 |
| | 持仓查询 | NFT 编号/所属藏品/持有人/来源/状态，按用户或藏品筛选 |
| | 定向发放 | 创建任务（藏品+份数+规则）、执行/重试、进度展示 |
| | ↳ 空投规则 11 种 | 指定用户/全部用户/按邀请数/持有指定藏品(持有N份投N份)/持仓门槛/白名单导入(支持手机号)/按消费金额/按注册时间/随机抽样/按用户标签/邀请排行 |
| | ↳ 空投机制 | 到账方式(直接到账/需用户领取 claim_status)、定时空投(plan_time)、重复拥有策略(跳过/换发抽奖次数/强制叠加)、空投凭证文案、批次撤销(revoke 回收未寄售持仓)、撤销后补发差额 |
| 活动运营 | 活动中心 | 四类活动统一开关（邀请/签到/注册为系统唯一活动，抽奖为多场次）、新建活动→配置奖励的引导流程、抽奖次数获取方式绑定到具体抽奖场次 |
| | 抽奖活动 | 活动开关、奖池配置（奖品=藏品/抽奖次数/谢谢参与+概率+库存+概率合计校验）、次数获取方式（签到/注册/持有藏品/邀请/消耗藏品，按场次绑定）、中奖记录 |
| | 合成活动 | 材料配方（材料+份数→目标藏品）、记录列表 |
| | 邀请有礼 | 全局活动开关、奖励规则（邀请人数→藏品/抽奖次数/优先购资格）、邀请记录 |
| | 签到活动 | 活动开关、每日签到奖励（抽奖次数/藏品/优先购三类型）、连续签到阶梯奖励（三类型）、补签上限、签到记录（奖励快照） |
| | 注册活动 | 活动开关、新人礼包（抽奖次数+绑定抽奖场次，场次未开启次数待生效；藏品空投；优先购资格次数+目标藏品，未发售藏品可预配置）、邀请人额外奖励（三类型）、发放记录（奖励快照） |
| 内容运营 | 公告管理 | 富文本、置顶、发布时间、删除 |
| | 轮播管理 | 图片上传、排序、跳转链接、启用停用 |
| 系统 | 运营参数 | 平台品牌卡（logo+名称，保存即全站生效）+ 交易/活动/奖励参数分组（仅超管） |
| | 数据清理 | 四组勾选 + 数量统计 + 输入「清空」确认 + 结果回显（仅超管，硬删不可恢复） |
| | 日志审计 | 管理员操作日志（操作人/模块/动作/目标/详情/IP/时间） |

## 二、环境要求

- PHP >= 8.0.2（扩展：mbstring、curl、gd、bcmath）
- MySQL >= 5.7
- Node.js >= 20.14、npm >= 9.8.1（或 pnpm）
- BuildAdmin v2.x（ThinkPHP8 分支，官方仓库 <https://gitee.com/wonderful-code/buildadmin>）

## 三、安装步骤

### 1. 先装好一个干净的 BuildAdmin

```bash
git clone https://gitee.com/wonderful-code/buildadmin.git my-nft-admin
cd my-nft-admin
composer install          # 完整包可跳过
php think run             # 访问 http://localhost:8000 完成可视化安装
```

### 2. 导入业务数据库

```bash
mysql -u用户名 -p 数据库名 < database/nft.sql
```

> 该脚本创建 20 张 `ba_nft_*` 业务表 + 17 个菜单/全部按钮权限（`ba_admin_rule`，ID 从 200 起）+ 运营参数初始数据。
> 若你的 BuildAdmin 表前缀不是 `ba_`，请先全局替换前缀再导入。

### 3. 覆盖复制本目录文件到 BuildAdmin 项目

| 本目录 | 目标（BuildAdmin 根目录内） |
|---|---|
| `app/admin/controller/nft/` | `app/admin/controller/nft/` |
| `app/admin/model/nft/` | `app/admin/model/nft/` |
| `app/admin/validate/nft/` | `app/admin/validate/nft/` |
| `web/src/api/backend/nft/` | `web/src/api/backend/nft/` |
| `web/src/views/backend/nft/` | `web/src/views/backend/nft/` |
| `web/src/utils/nft.ts` | `web/src/utils/nft.ts` |
| `web/src/styles/nft-glass.scss` | `web/src/styles/nft-glass.scss` |

```bash
cp -r nft-admin/app/admin/controller/nft  my-nft-admin/app/admin/controller/
cp -r nft-admin/app/admin/model/nft       my-nft-admin/app/admin/model/
cp -r nft-admin/app/admin/validate/nft    my-nft-admin/app/admin/validate/
cp -r nft-admin/web/src/api/backend/nft   my-nft-admin/web/src/api/backend/
cp -r nft-admin/web/src/views/backend/nft my-nft-admin/web/src/views/backend/
cp nft-admin/web/src/utils/nft.ts          my-nft-admin/web/src/utils/
cp nft-admin/web/src/styles/nft-glass.scss my-nft-admin/web/src/styles/
```

### 4. 引入玻璃主题

编辑 `web/src/styles/index.scss`，在文件末尾追加一行：

```scss
@import './nft-glass.scss';
```

### 5. 构建前端并重启

```bash
cd web
pnpm install
pnpm run dev      # 开发调试；生产 pnpm run build 后产物发布到 public/
```

### 6. 进入后台

1. 登录后台（超管默认可见全部菜单；其他角色请在「权限管理→角色组」勾选数字藏品菜单及按钮）。
2. 若菜单未立即出现：后台右上角 **清除缓存**，或重新登录。

## 四、关键约定

- **金额**：输入与展示均为「元」，落库统一「分」（bigint）。前端工具 `web/src/utils/nft.ts` 提供 `fen2yuan / yuan2fen / fmtTime / nftApi`。
- **时间**：统一 `YYYY-MM-DD HH:mm` 展示，落库 Unix 时间戳。
- **权限**：
  - 写按钮统一 `v-if="auth('nft/xxx/动作')"`；给运营人员配「只读」角色时只勾选各页 `index` 按钮即可隐藏全部写操作；
  - 运营参数、数据清理两页后端强制 `isSuperAdmin()`（401 拦截）。
- **操作日志**：所有写操作自动写入 `ba_nft_oper_log`（模型 `OperLog::record()`）。
- **主题**：设计系统全部在 `web/src/styles/nft-glass.scss`（`.glass .stat-card .tbl .btn .tag .pager .modal .bars` 等），页面只写少量私有样式；≤768px 自动切换移动端布局。

## 五、C 端（Vue）对接

- 本 SQL 的 20 张表即 C 端数据模型：藏品 `ba_nft_collection`、持仓 `ba_nft_hold`、订单 `ba_nft_order`、寄售 `ba_nft_market_order`、公告 `ba_nft_notice`、轮播 `ba_nft_banner`、品牌与运营参数 `ba_nft_setting`。
- 在 BuildAdmin 的 `app/api/controller/` 下新增 C 端控制器（如 `Nft.php`），用 `Db::name('nft_setting')` 等读取数据即可；品牌 logo/名称保存后 C 端直接读 `ba_nft_setting` 即时生效。
- C 端购买/寄售/抽奖等写业务建议复用 `app/admin/controller/nft/` 内同等事务逻辑（铸造持仓、扣减库存、写流水），或抽到 `app/common/service/` 供两端调用。

## 六、二次开发

新增页面请遵循 `docs/开发约定.md`（控制器/模型/验证器/API/页面五层写法、菜单 SQL 模板、主题类清单），保持与现有 19 页一致。

## 七、目录结构

```
nft-admin/
├── README.md                     本文档
├── database/nft.sql              建表 + 菜单权限 + 初始参数
├── docs/开发约定.md              模块开发规范
├── app/admin/controller/nft/     20 个后台控制器
├── app/admin/model/nft/          25 个模型
├── app/admin/validate/nft/       9 个验证器
└── web/src/
    ├── api/backend/nft/          18 个 API 模块
    ├── views/backend/nft/        19 个页面
    ├── utils/nft.ts              金额/时间/请求公共工具
    └── styles/nft-glass.scss     液态玻璃主题
```
