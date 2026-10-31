/* 数字藏品后台 · 模拟数据 */
const NOW = Date.parse('2026-10-28T02:20:00+08:00') / 1000;

function mkUsers() {
  const names = ['星河旅人','青瓷不语','北屿','雾里看花','拾光者','南栀','长安客','柚子茶','南山南','半夏微凉','云深不知处','橘颂'];
  return names.map((n, i) => ({
    id: 10001 + i, nickname: n, username: 'user_' + (10001 + i),
    mobile: '138' + String(71000000 + i * 137913).slice(0, 8),
    email: i % 3 ? '' : 'u' + i + '@qq.com',
    real_name: i % 4 === 0 ? ['陈星河','林青瓷','王北屿','周拾光'][i % 4] : '',
    tags: i % 3 === 0 ? 'vip' : i % 5 === 0 ? '内测' : i % 7 === 0 ? '达人' : '',
    invite_code: 'IC' + (58200 + i * 7),
    balance: (128000 + i * 45600) * 100 % 9000000,
    frozen: i % 5 === 0 ? 52000 : 0,
    total_income: (5000 + i * 1230) * 100,
    total_expense: (3200 + i * 870) * 100,
    order_count: 3 + i * 2, hold_count: 5 + i * 3,
    status: i === 8 ? 0 : 1,
    last_login_time: NOW - i * 7300,
    createtime: NOW - (30 + i * 9) * 86400,
  }));
}

const CATEGORIES = [
  { id: 1, name: '国风非遗', weigh: 100, status: 1, count: 22, createtime: NOW - 90 * 86400 },
  { id: 2, name: '敦煌系列', weigh: 90, status: 1, count: 12, createtime: NOW - 80 * 86400 },
  { id: 3, name: '山海经', weigh: 80, status: 1, count: 15, createtime: NOW - 60 * 86400 },
  { id: 4, name: '数字艺术', weigh: 70, status: 1, count: 28, createtime: NOW - 45 * 86400 },
  { id: 5, name: '联名IP', weigh: 60, status: 0, count: 6, createtime: NOW - 30 * 86400 },
];

function mkCollections() {
  const rows = [
    ['敦煌飞天 · 壹', 2, '李慕白', 3900, 69900, 10000, 8632, 0, 2, NOW - 3 * 86400],
    ['敦煌飞天 · 贰', 2, '李慕白', 3900, 69900, 10000, 5210, 1, 2, NOW - 3 * 86400],
    ['山海经 · 白泽', 3, '顾云舟', 9900, 199900, 5000, 3800, 1, 2, NOW - 10 * 86400],
    ['非遗剪纸 · 锦鲤', 1, '陈锦绣', 2900, 49900, 29000, 19888, 1, 2, NOW - 1 * 86400],
    ['千里江山 · 数字卷', 4, '宋青绿', 19900, 399900, 3000, 0, 1, 1, NOW + 5 * 86400],
    ['敦煌九色鹿', 2, '李慕白', 6900, 129900, 8000, 0, 0, 0, 0],
    ['山海经 · 穷奇', 3, '顾云舟', 9900, 199900, 6000, 4980, 1, 3, NOW - 20 * 86400],
    ['城市记忆 · 老街', 4, '王小满', 1900, 29900, 50000, 12304, 0, 0, 0],
  ];
  const publishers = ['灵境数字科技', '灵境数字科技', '云舟文化传媒', '锦绣非遗文化', '青绿数字艺术', '灵境数字科技', '云舟文化传媒', '满仓数字艺术'];
  const list = rows.map((r, i) => ({
    id: i + 1, name: r[0], category_id: r[1], author: r[2], publisher: publishers[i], cover: '',
    price: r[3], market_price: r[4], stock: r[5], sold: r[6], resale: r[7],
    status: r[8], sale_time: r[9],
    detail: '<p>「' + r[0] + '」以' + r[2] + '先生原作为蓝本，经 3D 建模与程序化生成铸造而成，每一枚均对应链上唯一凭证。</p><p>· 唯一编号，不可复制<br/>· 支持二级市场寄售流转<br/>· 持有人享有线下展优先购权益</p>',
    createtime: NOW - (20 + i) * 86400,
  }));
  // 优先购演示：#1 持有指定藏品（优先购进行中），#5 白名单（已排期待启动）
  Object.assign(list[0], { pre_rule_type: 1, pre_rule_value: '6', pre_sale_time: NOW - 3600, pre_limit: 2, sale_time: NOW + 3600 * 2 });
  Object.assign(list[4], { pre_rule_type: 2, pre_rule_value: '10001,10002,10007', pre_sale_time: NOW + 4 * 86400, pre_limit: 1 });
  // 发售结束时间 + 寄售限价（开启二级市场的藏品必填限价）
  list.forEach(c => { c.sale_end_time = 0; c.resale_price_limit = 0; c.resale_price_min = 0; });
  // 单品转赠开关（与寄售开关互相独立）
  list.forEach((c, i) => { c.transfer_enabled = [1, 1, 0, 1, 1, 1, 0, 1][i]; });
  Object.assign(list[1], { sale_end_time: NOW - 3600, resale_price_limit: 99900, resale_price_min: 30000 }); // 已到期未售完 → 演示自动售罄（仍展示）；下限 300 元 → 128 元挂单演示「低于下限」
  Object.assign(list[2], { resale_price_limit: 45900 }); // 存在 52000 挂单 → 演示「超限」标记
  Object.assign(list[3], { resale_price_limit: 79900 });
  Object.assign(list[4], { sale_end_time: NOW + 25 * 86400, resale_price_limit: 599900 });
  Object.assign(list[6], { resale_price_limit: 259900 });
  return list;
}

function mkOrders(users) {
  const types = [1, 2], stats = [1, 1, 1, 0, 2, 3];
  return Array.from({ length: 14 }, (_, i) => {
    const u = users[(i * 3) % users.length];
    const c = { id: (i % 8) + 1, name: mkCollections()[i % 8].name };
    const t = types[i % 2];
    return {
      id: 90000 + i, order_no: (t === 1 ? 'NFT' : 'MKT') + '20261028' + String(120000 + i * 37),
      user_id: u.id, nickname: u.nickname,
      collection_id: c.id, collection_name: c.name,
      type: t, num: 1,
      amount: (t === 1 ? [3900, 9900, 2900, 19900][i % 4] : [52000, 12800, 6900][i % 3]),
      pay_type: ['wechat', 'alipay', 'balance'][i % 3],
      pay_no: i % 4 === 3 ? '' : 'PAY' + (726300000 + i * 977),
      status: stats[i % 6],
      paytime: stats[i % 6] === 1 ? NOW - i * 4600 : 0,
      createtime: NOW - i * 5000 - 120,
    };
  });
}

function mkMarket(users, collections) {
  const mo = Array.from({ length: 9 }, (_, i) => {
    const c = collections[i % 6];
    const u = users[(i * 5) % users.length];
    return {
      id: 700 + i, order_no: 'SELL202610' + (27 - i) + '0' + i,
      seller_id: u.id, seller: u.nickname,
      collection_id: c.id, collection_name: c.name, sn: 'SN' + (100860 + i * 13),
      price: [8800, 39900, 52000, 15800, 9900, 26800, 6900, 12800, 18800][i],
      status: [0, 0, 1, 0, 2, 0, 1, 0, 0][i],
      buyer_id: 0, soldtime: 0,
      createtime: NOW - i * 9600 - 300,
    };
  });
  const buys = Array.from({ length: 5 }, (_, i) => {
    const u = users[(i * 7) % users.length];
    return { id: 800 + i, user_id: u.id, user: u.nickname, collection_id: (i % 5) + 1, collection_name: collections[i % 5].name, price: [3500, 9500, 2600, 21000, 6800][i], status: i % 3, createtime: NOW - i * 12300 };
  });
  return { mo, buys };
}

function mkHolds(users, collections) {
  const base = Array.from({ length: 12 }, (_, i) => {
    const c = collections[i % 6];
    const u = users[(i * 5 + 2) % users.length];
    return {
      id: 5000 + i, sn: 'SN' + (100860 + i * 13),
      collection_id: c.id, collection_name: c.name,
      user_id: u.id, user: u.nickname,
      source: [1, 2, 3, 4, 5, 6][i % 6],
      status: [0, 0, 1, 0, 2, 0][i % 6],
      mint_time: NOW - (15 - i) * 86400,
      createtime: NOW - (15 - i) * 86400,
    };
  });
  /* 演示：已持有敦煌飞天 · 壹的两位用户（i=0 / i=6）各追加 1 份（持有 2 份场景，供名单「持有数量」筛选演示） */
  const h0 = base[0], h6 = base[6];
  base.push({ id: 5012, sn: 'SN100876', collection_id: 1, collection_name: collections[0].name, user_id: h0.user_id, user: h0.user, source: 6, status: 0, mint_time: NOW - 10 * 86400, createtime: NOW - 10 * 86400 });
  base.push({ id: 5013, sn: 'SN100889', collection_id: 1, collection_name: collections[0].name, user_id: h6.user_id, user: h6.user, source: 6, status: 0, mint_time: NOW - 9 * 86400, createtime: NOW - 9 * 86400 });
  return base;
}

function buildDB() {
  const users = mkUsers();
  const collections = mkCollections();
  const mkt = mkMarket(users, collections);
  const db = {
    users,
    categories: CATEGORIES,
    collections,
    orders: mkOrders(users),
    market: mkt.mo, marketBuys: mkt.buys,
    holds: mkHolds(users, collections),
    walletLogs: Array.from({ length: 10 }, (_, i) => ({
      id: 6000 + i, user_id: 10001,
      type: ['recharge', 'income', 'consume', 'expense', 'refund', 'admin_adjust', 'freeze'][i % 7],
      related_no: 'NFT20261027' + (120000 + i * 13), amount: [1000000, 52000, -3900, -9900, 2900, 500000, -100000][i],
      before: 500000 + i * 40000, after: 500000 + i * 40000 + [1000000, 52000, -3900, -9900, 2900, 500000, -100000][i],
      remark: ['支付宝充值', '寄售卖出', '一级市场购买', '一级市场购买', '订单退款', '活动奖励调整', '寄售冻结'][i % 7],
      createtime: NOW - i * 8000,
    })),
    airdrops: [
      { id: 1, name: '十周年 · 全员空投', collection_id: 4, collection_name: '非遗剪纸 · 锦鲤', num: 5000, rule_type: 2, rule_value: '', success_num: 5000, fail_num: 12, revoke_num: 0, claim_mode: 0, dup_policy: 1, dup_reward: 0, plan_time: null, reason: '', claim_pending: 0, status: 2, createtime: NOW - 8 * 86400 },
      { id: 2, name: '老用户回馈', collection_id: 7, collection_name: '山海经 · 穷奇', num: 200, rule_type: 1, rule_value: '10001,10002,10003', success_num: 186, fail_num: 0, revoke_num: 0, claim_mode: 0, dup_policy: 1, dup_reward: 0, plan_time: null, reason: '', claim_pending: 0, status: 2, createtime: NOW - 3 * 86400 },
      { id: 3, name: '邀请达人专享', collection_id: 5, collection_name: '千里江山 · 数字卷', num: 100, rule_type: 3, rule_value: '5', success_num: 0, fail_num: 0, revoke_num: 0, claim_mode: 0, dup_policy: 1, dup_reward: 0, plan_time: null, reason: '', claim_pending: 0, status: 0, createtime: NOW - 3600 * 5 },
      { id: 4, name: '九色鹿持有者空投 · 第二期', collection_id: 1, collection_name: '敦煌飞天 · 壹', num: 800, rule_type: 4, rule_value: '6', success_num: 0, fail_num: 0, revoke_num: 0, claim_mode: 1, dup_policy: 3, dup_reward: 0, plan_time: NOW + 3600 * 20, reason: '因持有「敦煌九色鹿」获得', claim_pending: 0, status: 0, createtime: NOW - 3600 * 2 },
      { id: 5, name: '大藏家专享（持仓≥50）', collection_id: 2, collection_name: '敦煌飞天 · 贰', num: 300, rule_type: 5, rule_value: '50', success_num: 0, fail_num: 0, revoke_num: 0, claim_mode: 0, dup_policy: 2, dup_reward: 1, plan_time: null, reason: '', claim_pending: 0, status: 0, createtime: NOW - 3600 * 20 },
      { id: 6, name: '双十一白名单福利', collection_id: 3, collection_name: '山海经 · 白泽', num: 120, rule_type: 6, rule_value: '10001,10002,10005,13800138000,10009', success_num: 116, fail_num: 4, revoke_num: 0, claim_mode: 0, dup_policy: 1, dup_reward: 0, plan_time: null, reason: '双十一白名单专享福利', claim_pending: 0, status: 2, createtime: NOW - 86400 },
      { id: 7, name: 'VIP 标签用户回馈', collection_id: 7, collection_name: '山海经 · 穷奇', num: 150, rule_type: 10, rule_value: 'vip', success_num: 85, fail_num: 3, revoke_num: 42, claim_mode: 0, dup_policy: 1, dup_reward: 0, plan_time: null, reason: 'VIP 用户专属回馈', claim_pending: 0, status: 2, createtime: NOW - 5 * 86400 },
    ],
    lotteries: [
      { id: 1, title: '双十一 · 天天抽', chance_rules: { daily_free: { enabled: true, num: 1 }, register: { enabled: true, num: 2 }, hold: { enabled: true, items: [{ collection_id: 6, num: 2 }] }, invite: { enabled: true, num: 1, daily_cap: 5 }, consume: { enabled: true, items: [{ collection_id: 1, num: 1 }] } }, status: 1, start_time: NOW - 5 * 86400, end_time: NOW + 10 * 86400, createtime: NOW - 6 * 86400,
        prizes: [
          { id: 1, name: '敦煌九色鹿', type: 1, collection_id: 6, probability: 50, stock: 100, sent: 23 },
          { id: 2, name: '再抽 1 次', type: 2, num: 1, probability: 2000, stock: 10000, sent: 3120 },
          { id: 3, name: '谢谢参与', type: 3, probability: 7000, stock: 0, sent: 0 },
          { id: 4, name: '锦鲤剪纸', type: 1, collection_id: 4, probability: 950, stock: 500, sent: 187 },
        ] },
      { id: 2, title: '元旦新岁抽', chance_rules: { daily_free: { enabled: false, num: 1 }, register: { enabled: false, num: 1 }, hold: { enabled: false, items: [] }, invite: { enabled: false, num: 1, daily_cap: 5 }, consume: { enabled: false, items: [] } }, status: 0, start_time: NOW + 15 * 86400, end_time: NOW + 25 * 86400, createtime: NOW - 2 * 86400, prizes: [] },
    ],
    lotteryRecords: Array.from({ length: 8 }, (_, i) => ({ id: 900 + i, activity_id: 1, user_id: 10001 + i, user: mkUsers()[i].nickname, prize_name: ['再抽 1 次', '谢谢参与', '锦鲤剪纸', '谢谢参与', '再抽 1 次', '谢谢参与', '再抽 1 次', '敦煌九色鹿'][i], createtime: NOW - i * 5400 })),
    blindboxes: [
      { id: 1, name: '国风幸运福袋', price: 6900, total_supply: 10000, sold_count: 3210, status: 1, createtime: NOW - 12 * 86400,
        prizes: [
          { collection_id: 5, name: '千里江山 · 数字卷', probability: 40, quantity: 60, sent: 19 },
          { collection_id: 3, name: '山海经 · 白泽', probability: 25, quantity: 800, sent: 262 },
          { collection_id: 7, name: '山海经 · 穷奇', probability: 10, quantity: 320, sent: 98 },
          { collection_id: 4, name: '非遗剪纸 · 锦鲤', probability: 15, quantity: 1500, sent: 503 },
          { collection_id: 6, name: '敦煌九色鹿', probability: 5, quantity: 100, sent: 31 },
          { collection_id: 1, name: '敦煌飞天 · 壹', probability: 3, quantity: 60, sent: 18 },
          { collection_id: 2, name: '敦煌飞天 · 贰', probability: 2, quantity: 40, sent: 11 },
        ] },
      { id: 2, name: '元旦新岁盒', price: 19900, total_supply: 5000, sold_count: 0, status: 0, createtime: NOW - 2 * 86400, prizes: [] },
      { id: 3, name: '城市记忆 · 快闪盒', price: 3900, total_supply: 2000, sold_count: 1866, status: 1, createtime: NOW - 20 * 86400,
        prizes: [
          { collection_id: 8, name: '城市记忆 · 老街', probability: 70, quantity: 1400, sent: 1306 },
          { collection_id: 4, name: '非遗剪纸 · 锦鲤', probability: 30, quantity: 600, sent: 560 },
        ] },
    ],
    blindboxRecords: Array.from({ length: 8 }, (_, i) => ({
      id: 1300 + i, box_id: i < 5 ? 1 : 3, box_name: i < 5 ? '国风幸运福袋' : '城市记忆 · 快闪盒',
      user_id: 10001 + i, user: mkUsers()[i].nickname,
      prize_name: ['山海经 · 白泽', '非遗剪纸 · 锦鲤', '空盒（库存降级）', '敦煌九色鹿', '千里江山 · 数字卷', '城市记忆 · 老街', '城市记忆 · 老街', '非遗剪纸 · 锦鲤'][i],
      createtime: NOW - i * 4300,
    })),
    transfers: [
      { id: 1, transfer_no: 'TRF20261028001', collection_name: '敦煌飞天 · 壹', from_user: '星河旅人', to_user: '青瓷不语', status: 1, createtime: NOW - 6 * 86400, done_time: NOW - 6 * 86400 + 7200 },
      { id: 2, transfer_no: 'TRF20261027002', collection_name: '山海经 · 白泽', from_user: '北屿', to_user: '雾里看花', status: 1, createtime: NOW - 4 * 86400, done_time: NOW - 4 * 86400 + 5400 },
      { id: 3, transfer_no: 'TRF20261028003', collection_name: '非遗剪纸 · 锦鲤', from_user: '拾光者', to_user: '南栀', status: 0, createtime: NOW - 3600 * 3, done_time: 0 },
      { id: 4, transfer_no: 'TRF20261026004', collection_name: '敦煌九色鹿', from_user: '长安客', to_user: '柚子茶', status: 2, createtime: NOW - 2 * 86400, done_time: NOW - 2 * 86400 + 3600, revoke_reason: '误转给陌生账号，风控核实后撤销回收' },
      { id: 5, transfer_no: 'TRF20261025005', collection_name: '山海经 · 穷奇', from_user: '南山南', to_user: '半夏微凉', status: 3, createtime: NOW - 3 * 86400, done_time: 0 },
      { id: 6, transfer_no: 'TRF20261028006', collection_name: '城市记忆 · 老街', from_user: '云深不知处', to_user: '橘颂', status: 1, createtime: NOW - 86400, done_time: NOW - 86400 + 4000 },
    ],
    chanceLogs: [
      { id: 1, user_id: 10001, user: mkUsers()[0].nickname, num: 1, source: 1, ref: '连续签到 3 天 · 11月28日', createtime: NOW - 3600 * 2 },
      { id: 2, user_id: 10002, user: mkUsers()[1].nickname, num: 2, source: 5, ref: '消耗「星际漫游者」1 件', createtime: NOW - 3600 * 5 },
      { id: 3, user_id: 10003, user: mkUsers()[2].nickname, num: 2, source: 2, ref: '新人注册礼包', createtime: NOW - 3600 * 8 },
      { id: 4, user_id: 10004, user: mkUsers()[3].nickname, num: 1, source: 4, ref: '邀请好友 1 人（今日 3/5）', createtime: NOW - 3600 * 11 },
      { id: 5, user_id: 10005, user: mkUsers()[4].nickname, num: 2, source: 3, ref: '持有「敦煌九色鹿」×1', createtime: NOW - 3600 * 14 },
      { id: 6, user_id: 10006, user: mkUsers()[5].nickname, num: -1, source: 6, ref: '参与抽奖', createtime: NOW - 3600 * 16 },
      { id: 7, user_id: 10007, user: mkUsers()[6].nickname, num: 3, source: 7, ref: '客服补偿（订单异常）', createtime: NOW - 3600 * 20 },
      { id: 8, user_id: 10008, user: mkUsers()[7].nickname, num: -1, source: 6, ref: '参与抽奖', createtime: NOW - 3600 * 22 },
    ],
    raffles: [
      // 报名中
      { id: 1, collection_id: 5, total: 3000, per_limit: 1, apply_start: NOW - 2 * 86400, apply_end: NOW + 2 * 86400, buy_deadline: NOW + 6 * 86400, apply_count: 18652, win_count: 0, status: 1, whitelist: '13871000000,13871413739', createtime: NOW - 3 * 86400 },
      // 已截止待抽签
      { id: 2, collection_id: 4, total: 500, per_limit: 2, apply_start: NOW - 5 * 86400, apply_end: NOW - 3600, buy_deadline: NOW + 2 * 86400, apply_count: 9240, win_count: 0, status: 2, createtime: NOW - 6 * 86400 },
      // 已抽签（购买期）
      { id: 3, collection_id: 1, total: 100, per_limit: 1, apply_start: NOW - 10 * 86400, apply_end: NOW - 2 * 86400, buy_deadline: NOW + 3600 * 20, apply_count: 45321, win_count: 100, status: 3, createtime: NOW - 11 * 86400 },
    ],
    raffleApplys: (() => {
      const users = mkUsers();
      const mk = (rid, n, drawn, winRate) => Array.from({ length: n }, (_, i) => {
        const win = drawn && i % Math.round(1 / winRate) === 0;
        return { id: rid * 1000 + i, raffle_id: rid, user_id: 10001 + (i * 3) % 12, user: users[(i * 3) % 12].nickname,
          status: drawn ? (win ? 1 : 2) : 0, win_num: win ? [1, 2][i % 2] : 0, paid: win && i % 3 === 0 ? 1 : 0, createtime: NOW - i * 4200 };
      });
      return [...mk(1, 6, false, 0), ...mk(2, 6, false, 0), ...mk(3, 8, true, 0.375)];
    })(),
    syntheses: [
      { id: 1, name: '飞天合体 · 九色鹿降临', target_collection_id: 6, target_name: '敦煌九色鹿', materials: [{ collection_id: 1, num: 2 }, { collection_id: 2, num: 2 }], limit_num: 3, done_num: 861, status: 1, start_time: NOW - 2 * 86400, end_time: NOW + 20 * 86400, createtime: NOW - 3 * 86400 },
    ],
    synthesisRecords: Array.from({ length: 6 }, (_, i) => ({ id: 950 + i, synthesis_id: 1, user_id: 10001 + i, user: mkUsers()[i].nickname, status: i % 4 === 3 ? 2 : 1, createtime: NOW - i * 7200 })),
    inviteRules: [
      { id: 1, invite_num: 3, reward_type: 2, collection_id: 0, reward_num: 3, status: 1, createtime: NOW - 40 * 86400 },
      { id: 2, invite_num: 10, reward_type: 1, collection_id: 4, collection_name: '非遗剪纸 · 锦鲤', reward_num: 0, status: 1, createtime: NOW - 40 * 86400 },
      { id: 3, invite_num: 30, reward_type: 3, collection_id: 0, pre_collection_id: 5, reward_num: 1, status: 0, createtime: NOW - 20 * 86400 },
    ],
    inviteRecords: Array.from({ length: 8 }, (_, i) => ({ id: 970 + i, inviter: mkUsers()[i].nickname, invitee: mkUsers()[(i + 5) % 12].nickname, reward_status: i % 3 === 0 ? 1 : 0, createtime: NOW - i * 6600 })),
    notices: [
      { id: 1, title: '「敦煌飞天」系列 10 月 25 日 10:00 正式开售', category: 2, is_top: 1, status: 1, publish_time: NOW - 3 * 86400, createtime: NOW - 4 * 86400, content: '<p>本次发售共 10,000 份，每人限购 5 份…</p>' },
      { id: 2, title: '二级市场手续费临时下调至 3%', category: 3, is_top: 0, status: 1, publish_time: NOW - 6 * 86400, createtime: NOW - 6 * 86400, content: '<p>即日起至月底…</p>' },
      { id: 3, title: '关于防范冒充官方客服的公告', category: 1, is_top: 0, status: 1, publish_time: NOW - 12 * 86400, createtime: NOW - 12 * 86400, content: '<p>官方不会以任何形式索要验证码…</p>' },
      { id: 4, title: '系统维护通知（草稿）', category: 3, is_top: 0, status: 0, publish_time: 0, createtime: NOW - 3600, content: '' },
    ],
    banners: [
      { id: 1, title: '敦煌飞天主推', image: '', link: '/collection/1', weigh: 100, status: 1 },
      { id: 2, title: '双十一抽奖活动', image: '', link: '/lottery', weigh: 90, status: 1 },
      { id: 3, title: '邀请有礼', image: '', link: '/invite', weigh: 80, status: 0 },
    ],
    thirdparty: {
      huifu: {
        enabled: 1,
        items: [
          { name: 'sys_id', title: '汇聚系统号（Sys ID）', value: '6666000108881234' },
          { name: 'merchant_id', title: '汇付商户号', value: 'HF202610280001' },
          { name: 'store_id', title: '门店编号', value: 'ST0001' },
          { name: 'private_key', title: '商户 RSA 私钥', value: 'MIIEvQIBADANBgkqhkiG9w0BAQ********' },
          { name: 'cert_sn', title: '证书序列号', value: '6A8F3D2C1107BEE4' },
          { name: 'gateway', title: '网关地址', value: 'https://api.huifu.com' },
          { name: 'notify_url', title: '异步通知地址', value: 'https://api.lingjing.cn/pay/huifu/notify' },
        ],
      },
      sms: {
        enabled: 1,
        items: [
          { name: 'provider', title: '服务商', value: '腾讯云' },
          { name: 'access_key', title: 'AccessKey ID', value: 'AKIDjq7B2xNf9ZtX' },
          { name: 'secret_key', title: 'AccessKey Secret', value: 'wX3kKp8vQ2mR7sYd********' },
          { name: 'sign_name', title: '短信签名', value: '灵境数藏' },
          { name: 'tpl_code', title: '验证码模板 ID', value: 'SMS_2026102801' },
          { name: 'daily_limit', title: '单用户每日发送上限（条）', value: '10' },
          { name: 'code_ttl', title: '验证码有效期（分钟）', value: '5' },
        ],
      },
    },
    // 优先购白名单库（collection_id 0 = 全部藏品；活动侧「从白名单库导入」生成快照）
    whitelists: [
      { id: 1, mobile: '13800001001', collection_id: 0, remark: 'VIP 大客户', status: 1, createtime: NOW - 20 * 86400 },
      { id: 2, mobile: '13800001002', collection_id: 1, remark: '内部员工', status: 1, createtime: NOW - 15 * 86400 },
      { id: 3, mobile: '13800001003', collection_id: 1, remark: '老用户回馈', status: 1, createtime: NOW - 12 * 86400 },
      { id: 4, mobile: '13800001004', collection_id: 2, remark: '渠道合作', status: 1, createtime: NOW - 9 * 86400 },
      { id: 5, mobile: '13800001005', collection_id: 0, remark: '媒体测评（已停用）', status: 0, createtime: NOW - 6 * 86400 },
      { id: 6, mobile: '13800001006', collection_id: 5, remark: '社区管理员', status: 1, createtime: NOW - 3 * 86400 },
    ],
    agreements: [
      {
        id: 1, name: '用户服务协议', version: 'v2.1', status: 1,
        effective_time: NOW - 15 * 86400, updatetime: NOW - 15 * 86400,
        content: '<p>欢迎您使用本平台数字藏品相关服务。本协议是您与平台之间就数字藏品的购买、持有、转赠、寄售等事宜所订立的有效合约。</p><p><b>一、账号注册</b></p><p>您承诺以真实身份注册账号，并完成实名认证后方可参与藏品发售与二级市场交易。</p><p><b>二、藏品性质</b></p><p>数字藏品为虚拟商品，经链上铸造后对应唯一凭证，不可复制、不可篡改。您理解并同意藏品不具有类似货币的支付属性。</p><p><b>三、交易规则</b></p><p>二级市场寄售需遵守平台限价规则与手续费标准；抽签发售以抽签结果为准，中签后请在限定期限内完成支付，逾期视为放弃。</p><p><b>四、违规处理</b></p><p>利用外挂、脚本等手段抢购或恶意炒作藏品的，平台有权封禁账号并限制相关资产流转。</p>',
        history: [
          { version: 'v2.1', editor: '管理员', note: '新增抽签发售与限价条款', time: NOW - 15 * 86400 },
          { version: 'v2.0', editor: '法务-陈律师', note: '二级市场规则全面改版', time: NOW - 90 * 86400 },
          { version: 'v1.0', editor: '管理员', note: '初始版本', time: NOW - 300 * 86400 },
        ],
      },
      {
        id: 2, name: '隐私政策', version: 'v1.3', status: 0,
        effective_time: NOW + 3 * 86400, updatetime: NOW - 3600 * 20,
        content: '<p>本平台深知个人信息对您的重要性，将按照法律法规要求，采取安全保护措施保护您的个人信息。</p><p><b>一、我们收集的信息</b></p><p>为实现实名认证与交易安全，我们收集您的手机号、姓名、身份证号；为优化服务，收集您的设备信息与操作日志。</p><p><b>二、信息的使用</b></p><p>您的信息仅用于身份核验、风险防控、订单履约与客服支持，不用于任何与藏品业务无关的用途。</p><p><b>三、信息的存储与保护</b></p><p>身份证号等敏感信息加密存储，访问需二次授权并记录审计日志。</p><p><b>四、您的权利</b></p><p>您可随时在「我的-设置」中查询、更正个人信息，或申请注销账号，注销后信息将在 15 个工作日内删除。</p>',
        history: [
          { version: 'v1.3（草稿）', editor: '法务-陈律师', note: '补充注销与信息删除时限', time: NOW - 3600 * 20 },
          { version: 'v1.2', editor: '管理员', note: '实名认证信息收集范围更新', time: NOW - 60 * 86400 },
          { version: 'v1.0', editor: '管理员', note: '初始版本', time: NOW - 300 * 86400 },
        ],
      },
    ],
    settings: {
      brand: { site_logo: '', site_name: '灵境数藏' },
      trade: [
        { name: 'sell_fee_rate', title: '寄售手续费率(%)', value: '5' },
        { name: 'order_auto_cancel', title: '订单自动取消(分钟)', value: '15' },
        { name: 'market_open', title: '二级市场开关', value: '1' },
      ],
      activity: [
        { name: 'lottery_daily_times', title: '每日抽奖次数', value: '3' },
        { name: 'synthesis_daily_times', title: '每日合成次数', value: '5' },
      ],
      reward: [
        { name: 'invite_reward_enable', title: '邀请奖励开关', value: '1' },
        { name: 'register_reward_chance', title: '注册奖励抽奖次数', value: '1' },
        { name: 'register_pre_num', title: '新人优先购资格次数', value: '2' },
      ],
    },
    checkin: {
      enabled: 1, repair_limit: 3, daily: { type: 1, num: 1 },
      rules: [
        { day_num: 3, type: 1, num: 2, collection_id: 0 },
        { day_num: 7, type: 2, num: 1, collection_id: 4 },
        { day_num: 15, type: 3, num: 2, collection_id: 0, pre_collection_id: 5 },
        { day_num: 30, type: 1, num: 10, collection_id: 0 },
      ],
    },
    checkinRecords: Array.from({ length: 10 }, (_, i) => ({
      id: 1100 + i, user: mkUsers()[i % 12].nickname,
      date: new Date((NOW - i * 86400) * 1000).toISOString().slice(0, 10),
      continuous: 12 - i, reward: ['1 次抽奖', '非遗剪纸 · 锦鲤 ×1（连续 7 天阶梯）', '1 次抽奖', '1 次抽奖（补签）'][i % 4],
      status: 1, createtime: NOW - i * 86400 + 3600,
    })),
    register: {
      enabled: 1, chance: 1, lot_activity_id: 1, collection_id: 4, pre_collection_id: 5, pre_num: 2, inviter: { type: 1, num: 1, collection_id: 0 },
    },
    registerLogs: Array.from({ length: 8 }, (_, i) => ({
      id: 1200 + i, user: mkUsers()[(i + 3) % 12].nickname,
      reward: '1 次抽奖 + 非遗剪纸 · 锦鲤 ×1 + 优先购 2 次',
      inviter: i % 3 === 0 ? mkUsers()[i].nickname : '',
      status: i === 0 ? 0 : 1, createtime: NOW - i * 43200,
    })),
    operlogs: Array.from({ length: 12 }, (_, i) => ({
      id: 300 + i, admin: ['管理员', '运营-小李', '运营-小王'][i % 3],
      module: ['藏品管理', '用户管理', '公告管理', '定向发放', '运营参数', '市场寄售'][i % 6],
      action: ['开售', '禁用', '发布公告', '执行发放', '保存参数', '下架'][i % 6],
      target: ['藏品#' + (i + 1), '用户#' + (10001 + i), '公告#' + (i + 1), '任务#' + (i % 3 + 1), 'trade 组', '挂单#' + (700 + i)][i % 6],
      detail: ['设置开售时间为 10-25 10:00', '违规发布外链', '敦煌飞天系列预热', '5000 份全员空投完成', '手续费率 5% → 5%', '违规商品下架'][i % 6],
      ip: '113.88.' + (20 + i) + '.' + (100 + i * 7),
      createtime: NOW - i * 9200,
    })),
  };
  /* 名单快照（人群包）：预置一份演示名单 = 10-27 15:00 前有签到记录的用户 */
  /* 演示：首位用户补齐最近 3 天签到（加今日共连续 4 天，供名单「累计/连续签到天数」筛选演示） */
  const u0name = db.users[0].nickname;
  [1, 2, 3].forEach((d, i) => db.checkinRecords.push({ id: 1120 + i, user: u0name, date: new Date((NOW - d * 86400) * 1000).toISOString().slice(0, 10), continuous: 4 - i, reward: '1 次抽奖', status: 1, createtime: NOW - d * 86400 + 7200 }));
  const CUT15 = Date.parse('2026-10-27T15:00:00+08:00') / 1000;
  const ckNames = [...new Set(db.checkinRecords.filter(r => r.createtime <= CUT15).map(r => r.user))];
  db.rosters = [{ id: 1, name: '10-27 15:00 前签到用户', cond_type: 1, cond_desc: `签到截至 2026-10-27 15:00`, user_count: 0, status: 1, createtime: NOW - 86400 }];
  db.rosterItems = ckNames.map(n => {
    const u = db.users.find(x => x.nickname === n);
    return { roster_id: 1, user_id: u.id, mobile: u.mobile, reason: '2026-10-27 15:00 前有签到记录' };
  });
  db.rosters[0].user_count = db.rosterItems.length;
  return db;
}
