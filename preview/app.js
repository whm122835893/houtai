/* 数字藏品后台 · 完整交互原型（模拟数据驱动） */
'use strict';
const $ = s => document.querySelector(s);
const fmtY = f => (Number(f || 0) / 100).toFixed(2);
const fmtT = ts => { if (!ts) return '-'; const d = new Date(ts * 1000); const p = n => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`; };
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const CAT_NAME = id => (DB.categories.find(c => c.id === id) || {}).name || '-';
const COL_NAME = id => (DB.collections.find(c => c.id === id) || {}).name || '-';

const NAV = [
  { group: '概览', items: [['dashboard', '数据仪表盘', '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>']] },
  { group: '业务管理', items: [
    ['user', '用户管理', '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>'],
    ['wallet', '钱包流水', '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>'],
    ['collection', '藏品管理', '<path d="M6 3h12l4 6-10 12L2 9z"/>'],
    ['category', '分类管理', '<path d="M20 12l-8 8-9-9 9-8h8z"/>'],
    ['order', '订单管理', '<path d="M14 2H6v20h12V6z"/><path d="M14 2v4h4"/>'],
    ['market', '市场寄售', '<path d="M4 7h13m0 0-3-3m3 3-3 3M20 17H7m0 0 3 3m-3-3 3-3"/>'],
    ['hold', '持仓查询', '<path d="M12 2 2 7l10 5 10-5zM2 12l10 5 10-5M2 17l10 5 10-5"/>'],
    ['raffle', '抽签发售', '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 3.5"/><path d="M12 3a9 9 0 0 1 9 9"/>'],
    ['airdrop', '定向发放', '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>'],
  ] },
  { group: '活动运营', items: [
    ['activity', '活动中心', '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>'],
    ['lottery', '抽奖活动', '<rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 8c0-2 2-3 4-3 3 0 5 3 5 3s2-3 5-3c2 0 4 1 4 3"/>'],
    ['synthesis', '合成活动', '<path d="M9 3h6v6l4 9a2 2 0 0 1-2 3H7a2 2 0 0 1-2-3l4-9z"/>'],
    ['invite', '邀请有礼', '<path d="M3 9l9-6 9 6-9 6zM6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>'],
    ['checkin', '签到活动', '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 15.5l2 2 4-4"/>'],
    ['register', '注册活动', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>'],
  ] },
  { group: '内容运营', items: [
    ['notice', '公告管理', '<path d="M3 11l18-8-7 18-2.5-7z"/>'],
    ['banner', '轮播管理', '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>'],
  ] },
  { group: '系统', items: [
    ['setting', '运营参数', '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.06-.4.1-.8.1-1.2z"/>'],
    ['cleanup', '数据清理', '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>'],
    ['operlog', '日志审计', '<path d="M12 2 4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5z"/>'],
  ] },
];

const S = {
  page: 'dashboard', collapsed: localStorage.getItem('nft_sidebar') === '1',
  readonly: false, db: buildDB(),
  expandedWallet: 0,
  q: { user: { keyword: '', status: -1, page: 1 }, wallet: { keyword: '', page: 1 }, collection: { keyword: '', status: -1, category_id: 0 }, category: {}, order: { keyword: '', type: 0, status: -1, page: 1 }, market: { tab: 0, status: -1 }, hold: { keyword: '', status: -1, collection_id: 0 }, airdrop: {}, lottery: {}, synthesis: {}, invite: {}, checkin: { keyword: '', page: 1 }, register: { keyword: '', status: -1, page: 1 }, notice: { keyword: '', status: -1 }, banner: {}, operlog: { keyword: '', page: 1 } },
};
const canWrite = () => !S.readonly;
const W = html => canWrite() ? html : '';
const DB = S.db; // 共享模拟数据库（各页面/弹窗统一读写）

/* ---------- 通用 UI ---------- */
function toast(msg, type = '') {
  const el = document.createElement('div');
  el.className = 'toast ' + type; el.textContent = msg;
  $('#toast-wrap').appendChild(el);
  setTimeout(() => el.remove(), 2400);
}
function openModal(html) { $('#modal-root').innerHTML = `<div class="modal-mask fadein" data-act="modal-mask-click"><div class="modal glass pop">${html}</div></div>`; }
function closeModal() { $('#modal-root').innerHTML = ''; }
function confirmBox(text, act, arg) {
  openModal(`<div class="modal-head"><h3 style="color:var(--danger)">危险操作</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body"><p style="line-height:1.8;color:var(--ink-2)">${text}</p></div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-danger" data-act="${act}" data-arg="${esc(arg)}">确认执行</button></div>`);
}
function pagerHtml(page, total, limit, act) {
  const max = Math.max(1, Math.ceil(total / limit));
  let btns = '';
  const win = [];
  for (let i = Math.max(1, page - 2); i < Math.max(1, page - 2) + 5 && i <= max; i++) win.push(i);
  if (win[0] > 2) win.unshift(0);
  if (win[win.length - 1] < max - 1) win.push(0);
  win.forEach(p => btns += `<button ${p ? `data-act="${act}" data-arg="${p}"` : 'disabled'} class="${p === page ? 'active' : ''}">${p || '…'}</button>`);
  return `<div class="pager"><span>共 ${total} 条</span><button ${page <= 1 ? 'disabled' : ''} data-act="${act}" data-arg="${page - 1}">‹</button>${btns}<button ${page >= max ? 'disabled' : ''} data-act="${act}" data-arg="${page + 1}">›</button></div>`;
}
const pager = (act, page, total, limit = 10) => pagerHtml(page, total, limit, act);
const emptyBox = (t = '暂无数据') => `<div class="empty"><span class="glyph"><svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="9" cy="10" r="5.2"/><circle cx="16.8" cy="15.8" r="3.4"/><circle cx="6.8" cy="6.4" r="1.2" fill="currentColor" stroke="none"/></svg></span>${t}</div>`;

/* ---------- 页面：登录 ---------- */
function renderLogin() {
  document.body.innerHTML = `
  <div id="login-view">
    <div class="login-card glass pop">
      <div class="login-logo">灵</div>
      <h1>${esc(S.db.settings.brand.site_name)}</h1>
      <div class="en">ADMIN CONSOLE</div>
      <div class="form-row"><label>账号</label><input id="lg-user" class="input" placeholder="admin" value="admin"></div>
      <div class="form-row"><label>密码</label><input id="lg-pass" type="password" class="input" placeholder="123456" value="123456"></div>
      <button class="btn btn-primary" data-act="login">登 录</button>
      <div class="login-tip">原型演示：账号 admin / 密码 123456</div>
    </div>
  </div>
  <div id="modal-root"></div>
  <div id="toast-wrap"></div>`;
}

/* ---------- 布局 ---------- */
function renderShell() {
  let nav = '';
  NAV.forEach(g => {
    nav += `<div class="group-label">${g.group}</div>`;
    g.items.forEach(([key, name, icon]) => {
      nav += `<button class="nav-item ${S.page === key ? 'active' : ''}" data-act="nav" data-arg="${key}">
        <svg viewBox="0 0 24 24">${icon}</svg><span class="nav-text">${name}</span></button>`;
    });
  });
  document.body.innerHTML = `
  <div class="layout">
    <aside id="sidebar" class="glass ${S.collapsed ? 'collapsed' : ''}">
      <button class="collapse-btn" data-act="collapse">‹</button>
      <div class="brand"><div class="logo">${esc(S.db.settings.brand.site_name[0] || '灵')}</div>
        <div class="brand-text"><b>${esc(S.db.settings.brand.site_name)}</b><span>ADMIN CONSOLE</span></div></div>
      ${nav}
      <div class="user-chip"><div class="avatar">管</div>
        <div class="user-info"><b>管理员</b><span>超级管理员</span></div>
        <div class="chip-btns"><button class="btn btn-ghost btn-sm" data-act="chpwd" title="修改密码">改密</button><button class="btn btn-danger btn-sm" data-act="logout" title="退出登录">退出</button></div></div>
    </aside>
    <div id="shade" data-act="shade"></div>
    <main>
      <div class="topbar">
        <div class="tb-left"><button id="hamburger" data-act="hamburger" aria-label="菜单"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button><h1 id="tb-title"></h1><span class="en" id="tb-en"></span></div>
        <span class="writetag ${S.readonly ? 'readonly' : ''}" data-act="toggle-readonly" title="点击切换（演示权限效果）">${S.readonly ? '只读模式' : '写操作已开启'}</span>
      </div>
      <div id="view" class="nft-page"></div>
    </main>
  </div>
  <div id="modal-root"></div>
  <div id="toast-wrap"></div>`;
  bindShell();
}
function bindShell() {
  $('#tb-title').textContent = (NAV.flatMap(g => g.items).find(i => i[0] === S.page) || ['', ''])[1];
  $('#tb-en').textContent = (NAV.flatMap(g => g.items).find(i => i[0] === S.page) || ['', ''])[1];
  $('#tb-en').textContent = { dashboard: 'Dashboard', user: 'Users', wallet: 'Wallet Logs', collection: 'Collections', category: 'Categories', order: 'Orders', market: 'Market', hold: 'Holdings', airdrop: 'Airdrop', activity: 'Activity Center', lottery: 'Lottery', synthesis: 'Synthesis', invite: 'Invite', checkin: 'Check-in', register: 'Register Gift', notice: 'Notices', banner: 'Banners', setting: 'Settings', cleanup: 'Cleanup', operlog: 'Audit Log' }[S.page] || '';
}
function go(page) {
  S.page = page;
  document.querySelectorAll('.nav-item').forEach(b => b.classList.toggle('active', b.dataset.arg === page));
  bindShell(); renderPage();
  closeMobile();
}
function closeMobile() { $('#sidebar')?.classList.remove('mobile-open'); $('#shade').style.display = 'none'; }
function renderPage() {
  /* 发售自动结束：到期 → 一律自动置为「已售罄」（C 端仍展示，仅停止购买）；售出数达发行量同样自动售罄；只有手动下架才隐藏 */
  S.db.collections.forEach(c => {
    if (c.status === 2 && ((c.stock > 0 && c.sold >= c.stock) || (c.sale_end_time > 0 && NOW > c.sale_end_time))) c.status = 4;
  });
  $('#view').innerHTML = PAGES[S.page]();
}
/* topbar 已展示页题，页内不再重复 */
const style = document.createElement('style');
style.textContent = '#view .page-head{display:none}';
document.head.appendChild(style);

/* ---------- 页面实现 ---------- */
const PAGES = {};

/* 1. 仪表盘 */
PAGES.dashboard = () => {
  const db = S.db;
  const today = NOW - NOW % 86400;
  const todayOrders = db.orders.filter(o => o.createtime >= today).length;
  const gmv = db.orders.filter(o => o.status === 1).reduce((a, o) => a + o.amount, 0);
  const trend = Array.from({ length: 7 }, (_, i) => {
    const st = today - (6 - i) * 86400;
    const day = db.orders.filter(o => o.createtime >= st && o.createtime < st + 86400);
    const cnt = 960 + i * 110 + (i % 2 ? 220 : -60);
    return { date: new Date(st * 1000).toISOString().slice(5, 10).replace('-', '-'), count: cnt, amount: cnt * 85 };
  });
  const maxC = Math.max(...trend.map(t => t.count));
  const bars = trend.map(t => `<div class="bar-col"><div class="bar" style="height:${Math.round(t.count / maxC * 100)}%"><span class="tip">${t.count} 单 · ￥${(t.amount / 100).toLocaleString()}</span></div><span class="bar-x">${t.date}</span></div>`).join('');
  const stats = [
    ['注册用户', 128462, '今日新增 +326', 'orb-blue'],
    ['藏品', db.collections.length * 11, `在售 ${db.collections.filter(c => c.status === 2).length} 个系列`, 'orb-green'],
    ['NFT 持仓', 1336057, `寄售中 ${db.market.filter(m => m.status === 0).length * 480}`, 'orb-orange'],
    ['订单总数', 486102, `今日 ${todayOrders} 单`, 'orb-blue'],
  ];
  const orders = db.orders.slice(0, 6).map(o => { const [st, cls] = orderStatusTag(o.status); return `<tr>
    <td class="cell-sub">${o.order_no}</td><td>${o.nickname}</td><td class="cell-main">${o.collection_name}</td>
    <td><span class="tag ${o.type === 1 ? 'tag-blue' : 'tag-orange'}">${o.type === 1 ? '一级购' : '市场购'}</span></td>
    <td class="serif-num">￥${fmtY(o.amount)}</td>
    <td><span class="tag ${cls}">${st}</span></td><td class="cell-sub">${fmtT(o.createtime)}</td></tr>`; }).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>数据仪表盘</h2><span class="en">Dashboard</span></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px">
    ${stats.map((s, i) => `<div class="glass stat-card rise" style="--i:${i + 1}"><span class="orb ${s[3]}" style="width:70px;height:70px;right:-10px;top:-16px"></span><div class="label">${s[0]}</div><div class="num serif-num">${s[1].toLocaleString()}</div><div class="sub">${s[2]}</div></div>`).join('')}
  </div>
  <div style="display:grid;grid-template-columns:1.4fr 1fr;gap:14px">
    <div class="glass rise" style="--i:5"><div class="card-head"><h3>近 7 日订单趋势</h3><span class="en">7-Day Trend</span></div><div class="bars">${bars}</div></div>
    <div class="glass rise" style="--i:6"><div class="card-head"><h3>市场累计成交额</h3><span class="en">Total GMV</span></div>
      <div class="card-pad"><div class="serif-num" style="font-size:38px">￥${(gmv / 100 + 86429880).toLocaleString(undefined, { maximumFractionDigits: 0 })}</div>
      <div style="font-size:12px;color:var(--ink-muted);margin-top:8px">本周环比 <span class="tag tag-green">+12.4%</span></div></div></div>
  </div>
  <div class="glass rise" style="--i:7"><div class="card-head"><h3>最新订单</h3><span class="en">Latest Orders</span></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>订单号</th><th>用户</th><th>藏品</th><th>类型</th><th>金额</th><th>状态</th><th>时间</th></tr></thead><tbody>${orders}</tbody></table></div></div>`;
};

/* 2. 用户管理 */
PAGES.user = () => {
  const q = S.q.user;
  let list = S.db.users.filter(u =>
    (!q.keyword || (u.nickname + u.username + u.mobile).includes(q.keyword)) &&
    (q.status < 0 || u.status === q.status));
  const total = list.length; list = list.slice((q.page - 1) * 10, q.page * 10);
  const rows = list.map(u => `<tr>
    <td><input type="checkbox" class="u-ck" value="${u.id}"></td>
    <td class="serif-num">${u.id}</td>
    <td><div class="cell-main">${u.nickname}</div><div class="cell-sub">${u.username}</div></td>
    <td>${u.mobile}</td><td>${u.real_name || '<span class="cell-sub">未实名</span>'}</td>
    <td class="cell-sub">${u.invite_code}</td><td class="serif-num">￥${fmtY(u.balance)}</td>
    <td class="cell-sub">${u.hold_count} / ${u.order_count}</td>
    <td><span class="tag ${u.status ? 'tag-green' : 'tag-red'}">${u.status ? '正常' : '已禁用'}</span></td>
    <td class="cell-sub">${fmtT(u.last_login_time)}</td><td class="cell-sub">${fmtT(u.createtime)}</td>
    <td style="white-space:nowrap">
      <button class="btn btn-ghost btn-sm" data-act="user-detail" data-arg="${u.id}">详情</button>
      ${W(`<button class="btn btn-ghost btn-sm" data-act="user-edit" data-arg="${u.id}">编辑</button>
      <button class="btn btn-ghost btn-sm" data-act="user-status" data-arg="${u.id}">${u.status ? '禁用' : '启用'}</button>
      <button class="btn btn-danger btn-sm" data-act="user-del" data-arg="${u.id}">删除</button>`)}</td></tr>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>用户管理</h2><span class="en">Users</span></div>
  <div class="glass filter-bar rise" style="--i:1">
    <input class="input" id="u-kw" placeholder="昵称 / 账号 / 手机号" value="${esc(q.keyword)}">
    <select class="select" id="u-status"><option value="-1">全部状态</option><option value="1" ${q.status === 1 ? 'selected' : ''}>启用</option><option value="0" ${q.status === 0 ? 'selected' : ''}>禁用</option></select>
    <button class="btn btn-primary" data-act="user-search">查询</button>
    <button class="btn btn-ghost" data-act="user-reset">重置</button><span style="flex:1"></span>
    ${W(`<button class="btn btn-ghost" data-act="user-add">新增会员</button><button class="btn btn-danger btn-sm" data-act="user-batchdel">批量删除</button>`)}
  </div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th style="width:36px"></th><th>ID</th><th>昵称 / 账号</th><th>手机号</th><th>实名</th><th>邀请码</th><th>钱包余额</th><th>持仓/订单</th><th>状态</th><th>最近登录</th><th>注册时间</th><th style="width:210px">操作</th></tr></thead>
    <tbody>${rows || ''}</tbody></table>${list.length ? '' : emptyBox()}</div>
    ${pager('user-page', q.page, total)}</div>`;
};

/* 3. 钱包流水 */
PAGES.wallet = () => {
  const q = S.q.wallet;
  let list = S.db.users.filter(u => !q.keyword || (u.nickname + u.username + u.mobile).includes(q.keyword));
  const total = list.length; list = list.slice((q.page - 1) * 10, q.page * 10);
  const typeTag = t => ({ recharge: ['充值', 'tag-blue'], income: ['收款', 'tag-green'], consume: ['消费', 'tag-orange'], expense: ['支出', 'tag-red'], refund: ['退款', 'tag-orange'], freeze: ['冻结', 'tag-gray'], unfreeze: ['解冻', 'tag-gray'], admin_adjust: ['后台调整', 'tag-gray'] }[t] || ['', 'tag-gray']);
  const rows = list.map(u => {
    const logs = u.id === S.expandedWallet ? S.db.walletLogs : [];
    const logRows = logs.map(l => { const [cn, cls] = typeTag(l.type); return `<tr>
      <td></td><td colspan="4" style="background:rgba(74,143,247,.04)">
      <span class="tag ${cls}">${cn}</span> <span class="cell-sub">${l.related_no || '-'}</span> ${l.remark}</td>
      <td class="serif-num ${l.amount >= 0 ? 'amount-in' : 'amount-out'}">${l.amount >= 0 ? '+' : ''}${fmtY(l.amount)}</td>
      <td colspan="2" class="cell-sub">余额 ${fmtY(l.after)}</td><td colspan="3" class="cell-sub">${fmtT(l.createtime)}</td></tr>`; }).join('');
    return `<tr>
      <td class="serif-num">${u.id}</td>
      <td><div class="cell-main">${u.nickname}${u.tags ? ` <span class="tag tag-gray" style="margin-left:4px">${u.tags}</span>` : ''}</div><div class="cell-sub">${u.username}</div></td><td>${u.mobile}</td>
      <td class="serif-num">￥${fmtY(u.balance)}</td><td class="serif-num">￥${fmtY(u.frozen)}</td>
      <td class="serif-num amount-in">￥${fmtY(u.total_income)}</td><td class="serif-num amount-out">￥${fmtY(u.total_expense)}</td>
      <td style="white-space:nowrap"><button class="btn btn-ghost btn-sm" data-act="wallet-expand" data-arg="${u.id}">${S.expandedWallet === u.id ? '收起明细 ▲' : '流水明细 ▼'}</button>
      ${W(`<button class="btn btn-ghost btn-sm" data-act="wallet-adjust" data-arg="${u.id}">余额调整</button>`)}</td></tr>${logRows}`;
  }).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>钱包流水</h2><span class="en">Wallet Logs</span></div>
  <div class="glass filter-bar rise" style="--i:1">
    <input class="input" id="w-kw" placeholder="昵称 / 账号 / 手机号" value="${esc(q.keyword)}">
    <button class="btn btn-primary" data-act="wallet-search">查询</button>
    <button class="btn btn-ghost" data-act="wallet-reset">重置</button>
  </div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>ID</th><th>昵称 / 账号</th><th>手机号</th><th>钱包余额</th><th>冻结</th><th>累计收入</th><th>累计支出</th><th style="width:190px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table></div>${pager('wallet-page', q.page, total)}</div>`;
};

/* 4. 藏品管理 */
/* 4.x 优先购标签 */
const toLocalInput = ts => { const d = new Date(ts * 1000); const p = n => String(n).padStart(2, '0'); return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`; };
const preTag = c => {
  if (!c.pre_rule_type) return '';
  const on = c.status === 2 && c.pre_sale_time && c.sale_time && NOW >= c.pre_sale_time && NOW < c.sale_time;
  const cn = c.pre_rule_type === 1 ? `持有「${COL_NAME(+c.pre_rule_value)}」` : '白名单用户';
  return `<span class="tag ${on ? 'tag-orange' : 'tag-blue'}" title="优先购：${cn} · 每人限购 ${c.pre_limit || 1} 份 · ${fmtT(c.pre_sale_time)} 起">优先购${on ? '中' : ''}</span> `;
};
PAGES.collection = () => {
  const q = S.q.collection;
  let list = S.db.collections.filter(c =>
    (!q.keyword || c.name.includes(q.keyword)) &&
    (q.status < 0 || c.status === q.status) && (!q.category_id || c.category_id === q.category_id));
  const stTag = s => [['未发售', 'tag-gray'], ['已排期', 'tag-orange'], ['发售中', 'tag-green'], ['已下架', 'tag-red'], ['已售罄', 'tag-orange']][s];
  const rows = list.map(c => {
    const [st, cls] = stTag(c.status);
    return `<tr>
    <td class="serif-num">${c.id}</td><td><div class="thumb" style="display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#56a3fc33,#7dd8ff55)"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><path d="M7 3h10l4 6-9 12L3 9z"/><path d="M3 9h18M12 21 8 9l4-6 4 6z"/></svg></div></td>
    <td><div class="cell-main">${c.name}</div><div class="cell-sub">${c.author}</div></td>
    <td>${CAT_NAME(c.category_id)}</td>
    <td><span class="serif-num">￥${fmtY(c.price)}</span><div class="cell-sub">市场价 ￥${fmtY(c.market_price)}</div></td>
    <td class="cell-sub">${c.sold.toLocaleString()} / ${c.stock.toLocaleString()}</td>
    <td><span class="tag ${c.resale ? 'tag-green' : 'tag-gray'}">${c.resale ? '开启' : '关闭'}</span>${c.resale ? `<div class="cell-sub">限价 ￥${fmtY(c.resale_price_limit || 0)}</div>` : ''}</td>
    <td><span class="tag ${cls}">${st}</span></td><td class="cell-sub">${preTag(c)}${fmtT(c.sale_time) || '未排期'}${c.sale_end_time ? '<br/>至 ' + fmtT(c.sale_end_time) : ''}</td>
    <td style="white-space:nowrap">${c.status === 2
      ? W(`<button class="btn btn-ghost btn-sm" data-act="col-soldout" data-arg="${c.id}">停止发售</button><button class="btn btn-ghost btn-sm" data-act="col-off" data-arg="${c.id}">下架</button>`)
      : W(`<button class="btn btn-primary btn-sm" data-act="col-sale" data-arg="${c.id}">${c.status === 4 ? '重新开售' : '开售'}</button>`)}
      ${W(`<button class="btn btn-ghost btn-sm" data-act="col-edit" data-arg="${c.id}">编辑</button><button class="btn btn-danger btn-sm" data-act="col-del" data-arg="${c.id}">删除</button>`)}</td></tr>`;
  }).join('');
  const catOpts = S.db.categories.map(c => `<option value="${c.id}" ${q.category_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>藏品管理</h2><span class="en">Collections</span></div>
  <div class="glass filter-bar rise" style="--i:1">
    <input class="input" id="c-kw" placeholder="藏品名称" value="${esc(q.keyword)}">
    <select class="select" id="c-cat"><option value="0">全部分类</option>${catOpts}</select>
    <select class="select" id="c-status"><option value="-1">全部状态</option><option value="0" ${q.status === 0 ? 'selected' : ''}>未发售</option><option value="1" ${q.status === 1 ? 'selected' : ''}>已排期</option><option value="2" ${q.status === 2 ? 'selected' : ''}>发售中</option><option value="3" ${q.status === 3 ? 'selected' : ''}>已下架</option><option value="4" ${q.status === 4 ? 'selected' : ''}>已售罄</option></select>
    <button class="btn btn-primary" data-act="col-search">查询</button><span style="flex:1"></span>
    ${W('<button class="btn btn-primary" data-act="col-add">新增藏品</button>')}
  </div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>ID</th><th>封面</th><th>名称 / 作者</th><th>分类</th><th>售价 / 市场价</th><th>已售 / 发行量</th><th>二级市场</th><th>状态</th><th>优先购 / 开售时间</th><th style="width:200px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table>${list.length ? '' : emptyBox()}</div></div>`;
};

/* 4.b 抽签发售 */
const RAF_PHASE = [['未开始', 'tag-gray'], ['报名中', 'tag-blue'], ['待抽签', 'tag-orange'], ['购买期', 'tag-green'], ['已结束', 'tag-gray']];
const phaseOf = r => {
  const now = NOW;
  if (r.status === 3) return now > r.buy_deadline ? 4 : 3;
  if (now < r.apply_start) return 0;
  if (now <= r.apply_end) return 1;
  return 2;
};
const rafQuota = r => Math.floor(r.total / r.per_limit);
PAGES.raffle = () => {
  const rows = S.db.raffles.map(r => {
    const ph = phaseOf(r);
    return `<tr><td class="serif-num">${r.id}</td><td class="cell-main">${COL_NAME(r.collection_id)}</td>
    <td><span class="serif-num">${r.total.toLocaleString()}</span> 份<div class="cell-sub">每人限购 ${r.per_limit} · 名额 ${rafQuota(r).toLocaleString()}</div></td>
    <td><span class="serif-num">${r.apply_count.toLocaleString()}</span> 人报名${r.status === 3 ? `<div class="cell-sub amount-in">中签 ${r.win_count} 人</div>` : ''}</td>
    <td><span class="tag ${RAF_PHASE[ph][1]}">${RAF_PHASE[ph][0]}</span></td>
    <td class="cell-sub">${fmtT(r.apply_start)}<br>~ ${fmtT(r.apply_end)}</td>
    <td class="cell-sub">${fmtT(r.buy_deadline)}</td>
    <td style="white-space:nowrap">${ph === 2 ? W(`<button class="btn btn-primary btn-sm" data-act="raf-draw" data-arg="${r.id}">执行抽签</button>`) : ''}
    <button class="btn btn-ghost btn-sm" data-act="raf-applys" data-arg="${r.id}">报名记录</button>
    ${W(r.status < 3 ? `<button class="btn btn-ghost btn-sm" data-act="raf-edit" data-arg="${r.id}">编辑</button><button class="btn btn-danger btn-sm" data-act="raf-del" data-arg="${r.id}">删除</button>` : '')}</td></tr>`;
  }).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>抽签发售</h2><span class="en">Raffle Sale</span></div>
  <div class="glass rise" style="--i:1;margin-bottom:14px"><div class="card-pad" style="padding:14px 20px"><span class="cell-sub">报名期 → 截止后「执行抽签」→ 中签者获得购买资格（至购买截止）。中签名额 = 发售数量 ÷ 每人限购，报名不足名额时全部中签。</span></div></div>
  <div class="glass filter-bar rise" style="--i:1"><span style="flex:1"></span>${W('<button class="btn btn-primary" data-act="raf-add">新建抽签</button>')}</div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>ID</th><th>发售藏品</th><th>发售量 / 限购</th><th>报名 / 中签</th><th>阶段</th><th>报名时间</th><th>购买截止</th><th style="width:260px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table></div></div>`;
};

/* 5. 分类管理 */
PAGES.category = () => {
  const rows = S.db.categories.map(c => `<tr>
    <td class="serif-num">${c.id}</td><td class="cell-main">${c.name}</td>
    <td class="serif-num">${c.count}</td><td class="serif-num">${c.weigh}</td>
    <td><span class="tag ${c.status ? 'tag-green' : 'tag-gray'}">${c.status ? '启用' : '停用'}</span></td>
    <td class="cell-sub">${fmtT(c.createtime)}</td>
    <td style="white-space:nowrap">${W(`<button class="btn btn-ghost btn-sm" data-act="cat-edit" data-arg="${c.id}">编辑</button>
    <button class="btn btn-ghost btn-sm" data-act="cat-status" data-arg="${c.id}">${c.status ? '停用' : '启用'}</button>
    <button class="btn btn-danger btn-sm" data-act="cat-del" data-arg="${c.id}">删除</button>`)}</td></tr>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>分类管理</h2><span class="en">Categories</span></div>
  <div class="glass filter-bar rise" style="--i:1"><span class="cell-sub">分类被藏品引用时不可删除</span><span style="flex:1"></span>${W('<button class="btn btn-primary" data-act="cat-add">新增分类</button>')}</div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>ID</th><th>分类名称</th><th>藏品数</th><th>排序</th><th>状态</th><th>创建时间</th><th style="width:190px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table></div></div>`;
};

/* 6. 订单管理 */
const orderStatusTag = s => [['待支付', 'tag-orange'], ['已支付', 'tag-green'], ['已取消', 'tag-gray'], ['已退款', 'tag-red']][s];
PAGES.order = () => {
  const q = S.q.order;
  let list = S.db.orders.filter(o =>
    (!q.keyword || o.order_no.includes(q.keyword) || o.pay_no.includes(q.keyword)) &&
    (!q.type || o.type === q.type) && (q.status < 0 || o.status === q.status));
  const total = list.length; list = list.slice((q.page - 1) * 10, q.page * 10);
  const payCn = { wechat: '微信支付', alipay: '支付宝', balance: '余额支付' };
  const rows = list.map(o => { const [st, cls] = orderStatusTag(o.status); return `<tr>
    <td><div class="cell-main">${o.order_no}</div><div class="cell-sub">${o.pay_no || '第三方单号 -'}</div></td>
    <td>${o.nickname}</td><td class="cell-main">${o.collection_name}</td>
    <td><span class="tag ${o.type === 1 ? 'tag-blue' : 'tag-orange'}">${o.type === 1 ? '一级购' : '市场购'}</span></td>
    <td class="serif-num">￥${fmtY(o.amount)}</td><td>${payCn[o.pay_type] || '-'}</td>
    <td><span class="tag ${cls}">${st}</span></td>
    <td class="cell-sub">${fmtT(o.createtime)}</td>
    <td><button class="btn btn-ghost btn-sm" data-act="order-detail" data-arg="${o.id}">详情</button></td></tr>`; }).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>订单管理</h2><span class="en">Orders</span></div>
  <div class="glass filter-bar rise" style="--i:1">
    <input class="input" id="o-kw" placeholder="订单号 / 支付单号" value="${esc(q.keyword)}">
    <select class="select" id="o-type"><option value="0">全部类型</option><option value="1" ${q.type === 1 ? 'selected' : ''}>一级购</option><option value="2" ${q.type === 2 ? 'selected' : ''}>市场购</option></select>
    <select class="select" id="o-status"><option value="-1">全部状态</option><option value="0">待支付</option><option value="1">已支付</option><option value="2">已取消</option><option value="3">已退款</option></select>
    <button class="btn btn-primary" data-act="order-search">查询</button><button class="btn btn-ghost" data-act="order-reset">重置</button>
  </div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>订单号</th><th>买家</th><th>藏品</th><th>类型</th><th>金额</th><th>支付方式</th><th>状态</th><th>下单时间</th><th>操作</th></tr></thead>
    <tbody>${rows}</tbody></table>${list.length ? '' : emptyBox()}</div>${pager('order-page', q.page, total)}</div>`;
};

/* 7. 市场寄售 */
PAGES.market = () => {
  const q = S.q.market;
  const tabBtn = (i, t) => `<button class="btn ${q.tab === i ? 'btn-primary' : 'btn-ghost'}" data-act="market-tab" data-arg="${i}">${t}</button>`;
  let body = '';
  if (q.tab === 0) {
    let list = q.status < 0 ? S.db.market : S.db.market.filter(m => m.status === q.status);
    const stTag = [['在售', 'tag-green'], ['已售', 'tag-blue'], ['已下架', 'tag-gray']];
    body = `<table class="tbl"><thead><tr><th>挂单号</th><th>卖家</th><th>藏品 / 编号</th><th>单价 / 限价</th><th>状态</th><th>挂单时间</th><th>售出时间</th><th>操作</th></tr></thead><tbody>
      ${list.map(m => { const [st, cls] = stTag[m.status]; const lim = (S.db.collections.find(c => c.id === m.collection_id) || {}).resale_price_limit || 0; const over = lim > 0 && m.price > lim; return `<tr>
      <td class="cell-sub">${m.order_no}</td><td>${m.seller}</td>
      <td><div class="cell-main">${m.collection_name}</div><div class="cell-sub">${m.sn}</div></td>
      <td><span class="serif-num" ${over ? 'style="color:var(--danger)"' : ''}>￥${fmtY(m.price)}</span><div class="cell-sub">${lim > 0 ? '限价 ￥' + fmtY(lim) : '不限价'}${over ? ' <span class="tag tag-red">超限</span>' : ''}</div></td><td><span class="tag ${cls}">${st}</span></td>
      <td class="cell-sub">${fmtT(m.createtime)}</td><td class="cell-sub">${fmtT(m.soldtime)}</td>
      <td>${m.status === 0 ? W(`<button class="btn btn-ghost btn-sm" data-act="market-off" data-arg="${m.id}">下架</button>`) : '-'}</td></tr>`; }).join('')}
      </tbody></table>${list.length ? '' : emptyBox()}`;
  } else {
    body = `<table class="tbl"><thead><tr><th>求购人</th><th>藏品</th><th>求购价</th><th>状态</th><th>时间</th></tr></thead><tbody>
      ${S.db.marketBuys.map(b => `<tr><td>${b.user}</td><td class="cell-main">${b.collection_name}</td>
      <td class="serif-num">￥${fmtY(b.price)}</td><td><span class="tag ${['tag-orange', 'tag-green', 'tag-gray'][b.status]}">${['求购中', '已完成', '已取消'][b.status]}</span></td>
      <td class="cell-sub">${fmtT(b.createtime)}</td></tr>`).join('')}</tbody></table>`;
  }
  return `
  <div class="page-head rise" style="--i:0"><h2>市场寄售</h2><span class="en">Market</span></div>
  <div class="glass filter-bar rise" style="--i:1">${tabBtn(0, '挂单列表')}${tabBtn(1, '求购单')}${q.tab === 0 ? `<span style="flex:1"></span>
    <select class="select" id="m-status"><option value="-1">全部状态</option><option value="0" ${q.status === 0 ? 'selected' : ''}>在售</option><option value="1" ${q.status === 1 ? 'selected' : ''}>已售</option><option value="2" ${q.status === 2 ? 'selected' : ''}>已下架</option></select>
    <button class="btn btn-primary" data-act="market-search">筛选</button>` : ''}</div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap">${body}</div></div>`;
};

/* 8. 持仓查询 */
PAGES.hold = () => {
  const q = S.q.hold;
  let list = S.db.holds.filter(h => (!q.keyword || h.sn.toLowerCase().includes(q.keyword.toLowerCase()) || h.user.includes(q.keyword)) && (q.status < 0 || h.status === q.status));
  const srcCn = ['一级购', '市场购', '定向发放', '抽奖', '合成', '邀请奖励'];
  const stTag = [['持有', 'tag-green'], ['寄售中', 'tag-orange'], ['已锁定', 'tag-gray']];
  const rows = list.map(h => { const [st, cls] = stTag[h.status]; return `<tr>
    <td class="serif-num">${h.sn}</td><td class="cell-main">${h.collection_name}</td><td>${h.user}</td>
    <td><span class="tag tag-blue">${srcCn[h.source - 1]}</span></td>
    <td><span class="tag ${cls}">${st}</span></td>
    <td class="cell-sub">${fmtT(h.mint_time)}</td><td class="cell-sub">${fmtT(h.createtime)}</td></tr>`; }).join('');
  const colOpts = S.db.collections.map(c => `<option value="${c.id}" ${q.collection_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>持仓查询</h2><span class="en">Holdings</span></div>
  <div class="glass filter-bar rise" style="--i:1">
    <input class="input" id="h-kw" placeholder="编号 / 持有人" value="${esc(q.keyword)}">
    <select class="select" id="h-col"><option value="0">全部藏品</option>${colOpts}</select>
    <select class="select" id="h-status"><option value="-1">全部状态</option><option value="0">持有</option><option value="1">寄售中</option><option value="2">已锁定</option></select>
    <button class="btn btn-primary" data-act="hold-search">查询</button><button class="btn btn-ghost" data-act="hold-reset">重置</button>
  </div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>编号</th><th>所属藏品</th><th>持有人</th><th>来源</th><th>状态</th><th>铸造时间</th><th>获得时间</th></tr></thead>
    <tbody>${rows}</tbody></table>${list.length ? '' : emptyBox()}</div></div>`;
};

/* 9. 定向发放 */
PAGES.airdrop = () => {
  const RULES = { 1: ['指定用户', 'tag-blue'], 2: ['全部用户', 'tag-green'], 3: ['按邀请数', 'tag-orange'], 4: ['持有藏品', 'tag-orange'], 5: ['持仓门槛', 'tag-blue'], 6: ['白名单', 'tag-green'], 7: ['按消费', 'tag-blue'], 8: ['老用户', 'tag-gray'], 9: ['随机抽样', 'tag-orange'], 10: ['按标签', 'tag-green'], 11: ['邀请排行', 'tag-orange'] };
  const brief = t => {
    const v = t.rule_value || '';
    if (t.rule_type === 4) return '凭证：' + COL_NAME(+v);
    if (t.rule_type === 7) return '消费 ≥ ' + v + ' 元';
    if (t.rule_type === 8) return '注册早于 ' + v;
    if (t.rule_type === 11) return '邀请 Top ' + v;
    if (t.rule_type === 3 || t.rule_type === 5) return '门槛：' + v;
    return v && v.length > 20 ? v.slice(0, 20) + '…' : (v || '-');
  };
  const rows = S.db.airdrops.map(t => {
    const [rt, rc] = RULES[t.rule_type] || ['-', 'tag-gray'];
    const remain = t.success_num - t.revoke_num;
    return `<tr>
    <td><div class="cell-main">${t.name}</div><div class="cell-sub">任务#${t.id}${t.reason ? ' · 凭证：' + t.reason : ''}</div></td>
    <td>${t.collection_name}</td><td class="serif-num">${t.num.toLocaleString()} 份</td>
    <td><span class="tag ${rc}">${rt}</span><div class="cell-sub">${brief(t)}</div></td>
    <td><span class="tag ${t.claim_mode === 1 ? 'tag-orange' : 'tag-green'}">${t.claim_mode === 1 ? '需领取' : '直接到账'}</span></td>
    <td><span class="serif-num amount-in">${t.success_num}</span>${t.fail_num ? ` <span class="serif-num amount-out">+${t.fail_num}跳过</span>` : ''}${t.revoke_num ? ` <span class="serif-num amount-out">−${t.revoke_num}撤销</span>` : ''}<span class="cell-sub"> / ${t.num}</span></td>
    <td><span class="tag ${['tag-orange', 'tag-blue', 'tag-green'][t.status]}">${['待发放', '发放中', '完成'][t.status]}</span></td>
    <td class="cell-sub">${t.plan_time ? '定时 ' + fmtT(t.plan_time) : fmtT(t.createtime)}</td>
    <td style="white-space:nowrap">${t.status !== 1 ? W(`<button class="btn btn-primary btn-sm" data-act="air-retry" data-arg="${t.id}">${t.status === 0 ? (t.plan_time ? '提前执行' : '执行发放') : '补发差额'}</button>`) : '<span class="cell-sub">发放中…</span>'}
    ${W(remain > 0 ? `<button class="btn btn-ghost btn-sm" data-act="air-revoke" data-arg="${t.id}">撤销批次</button>` : '')}
    ${W(`<button class="btn btn-danger btn-sm" data-act="air-del" data-arg="${t.id}">删除</button>`)}</td></tr>`;
  }).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>定向发放</h2><span class="en">Airdrop</span></div>
  <div class="glass filter-bar rise" style="--i:1"><span class="cell-sub">11 种定向规则 · 直接到账 / 领取式 · 定时空投 · 重复策略 · 批次撤销</span><span style="flex:1"></span>${W('<button class="btn btn-primary" data-act="air-add">创建任务</button>')}</div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>任务</th><th>藏品</th><th>份数</th><th>目标规则</th><th>到账方式</th><th>进度</th><th>状态</th><th>执行时间</th><th style="width:230px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table></div></div>`;
};

/* 9b. 活动中心 */
const RW_TYPE = { 1: ['抽奖次数', 'tag-green'], 2: ['藏品', 'tag-blue'], 3: ['优先购资格', 'tag-orange'] };
const rewardTxt = r => r.type === 2 ? `${COL_NAME(r.collection_id) || '藏品#' + r.collection_id} ×${r.num || 1}` : r.type === 3 ? `优先购 ${r.num || 1} 次 · ${r.pre_collection_id ? COL_NAME(r.pre_collection_id) : '未绑定藏品'}` : `${r.num || 0} 次抽奖`;
const rewardTag = r => { const t = RW_TYPE[r.type] || RW_TYPE[1]; return `<span class="tag ${t[1]}">${t[0]}</span> ${rewardTxt(r)}`; };
/* 奖励配置表单行（prefix 为元素 id 前缀，cfg 为 {type,num,collection_id,pre_collection_id}）
   类型 2=藏品 → 选藏品；类型 3=优先购资格 → 选目标藏品（未发售藏品可预配置） */
const CFG_ST_NAME = ['未发售', '已排期', '发售中', '已下架', '已售罄'];
const rewardCfgHtml = (prefix, cfg = { type: 1, num: 1, collection_id: 0, pre_collection_id: 0 }) => {
  const colOpts = S.db.collections.map(c => `<option value="${c.id}" ${+cfg.collection_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
  const preOpts = S.db.collections.map(c => `<option value="${c.id}" ${+cfg.pre_collection_id === c.id ? 'selected' : ''}>${c.name}（${CFG_ST_NAME[c.status]}${c.status !== 2 ? '，可预配置' : ''}）</option>`).join('');
  return `<div class="form-grid">
    <div class="form-row"><label>奖励类型</label><select class="select" id="${prefix}-type"><option value="1" ${+cfg.type === 1 ? 'selected' : ''}>抽奖次数</option><option value="2" ${+cfg.type === 2 ? 'selected' : ''}>藏品</option><option value="3" ${+cfg.type === 3 ? 'selected' : ''}>优先购资格</option></select></div>
    <div class="form-row"><label>数量（次数/份数）</label><input class="input" id="${prefix}-num" type="number" min="1" value="${cfg.num ?? 1}"></div>
    <div class="form-row" id="${prefix}-col-row" style="display:${+cfg.type === 2 ? '' : 'none'}"><label>选择藏品</label><select class="select" id="${prefix}-col"><option value="0">请选择藏品</option>${colOpts}</select></div>
    <div class="form-row" id="${prefix}-pre-row" style="display:${+cfg.type === 3 ? '' : 'none'}"><label>优先购目标藏品</label><select class="select" id="${prefix}-pre-col"><option value="0">请选择目标藏品</option>${preOpts}</select></div></div>`;
};
const rewardCfgRead = prefix => ({ type: +$(`#${prefix}-type`).value, num: +$(`#${prefix}-num`).value || 1, collection_id: +($(`#${prefix}-col`)?.value || 0), pre_collection_id: +($(`#${prefix}-pre-col`)?.value || 0) });
const rewardCfgCheck = cfg => !((cfg.type === 2 && !cfg.collection_id) || (cfg.type === 3 && !cfg.pre_collection_id));
const syncRewardCfg = prefix => { const t = $(`#${prefix}-type`); const colRow = $(`#${prefix}-col-row`); const preRow = $(`#${prefix}-pre-row`); if (!t) return; if (colRow) colRow.style.display = +t.value === 2 ? '' : 'none'; if (preRow) preRow.style.display = +t.value === 3 ? '' : 'none'; };
const settingVal = (name, dflt) => {
  for (const g of Object.values(S.db.settings)) {
    if (!Array.isArray(g)) continue;
    const it = g.find(x => x.name === name);
    if (it) return it.value;
  }
  return dflt;
};
const settingSet = (name, val) => {
  for (const g of Object.values(S.db.settings)) {
    if (!Array.isArray(g)) continue;
    const it = g.find(x => x.name === name);
    if (it) { it.value = String(val); return true; }
  }
  return false;
};
const ACT_TYPE = { invite: ['邀请有礼', 'tag-orange'], lottery: ['抽奖活动', 'tag-blue'], checkin: ['签到活动', 'tag-green'], register: ['注册活动', 'tag-purple'] };const activityRows = () => {
  const rows = [
    { key: 'invite', name: '邀请有礼', on: +(settingVal('invite_reward_enable', 1)), time: '长期有效', reward: `${S.db.inviteRules.filter(r => r.status).length} 档奖励规则`, page: 'invite' },
    { key: 'checkin', name: '每日签到', on: +S.db.checkin.enabled, time: '长期有效', reward: `每日 ${rewardTxt(S.db.checkin.daily)} · ${S.db.checkin.rules.length} 档阶梯`, page: 'checkin' },
    { key: 'register', name: '新人注册礼', on: +S.db.register.enabled, time: '长期有效', reward: `${rewardTxt({ type: 1, num: S.db.register.chance })}${S.db.register.lot_activity_id ? ' · 绑定「' + ((S.db.lotteries.find(l => l.id === S.db.register.lot_activity_id) || {}).title || '') + '」' : ' · 通用'}${+S.db.register.collection_id ? ' + 新人藏品' : ''}${+S.db.register.pre_num ? ' + 优先购 ' + S.db.register.pre_num + ' 次' : ''}`, page: 'register' },
  ];
  S.db.lotteries.forEach(a => rows.push({ key: 'lottery', id: a.id, name: a.title, on: +a.status, time: `${fmtT(a.start_time)} ~ ${fmtT(a.end_time)}`, reward: `${(a.prizes || []).length} 种奖品 · ${chanceTagHtml(a.chance_rules)}`, page: 'lottery' }));
  return rows;
};
PAGES.activity = () => {
  const rows = activityRows();
  const openN = rows.filter(r => r.on).length;
  const stat = (t, n, sub) => `<div class="glass stat-card rise" style="--i:1"><div class="label">${t}</div><div class="num serif-num">${n}</div><div class="sub">${sub}</div></div>`;
  const trs = rows.map((r, i) => {
    const arg = r.id ? `${r.key}:${r.id}` : r.key;
    const [tName, tCls] = ACT_TYPE[r.key];
    return `<tr>
    <td><span class="tag ${tCls}">${tName}</span></td>
    <td class="cell-main">${esc(r.name)}${r.id ? ` <span class="cell-sub">#${r.id}</span>` : ''}</td>
    <td class="cell-sub">${r.time}</td>
    <td>${r.reward}</td>
    <td><button class="btn btn-sm ${r.on ? 'btn-primary' : 'btn-ghost'}" data-act="act-toggle" data-arg="${arg}">${r.on ? '开启中' : '已关闭'}</button></td>
    <td style="white-space:nowrap">${W(`<button class="btn btn-ghost btn-sm" data-act="act-reward" data-arg="${arg}">配置奖励</button>${r.id ? `<button class="btn btn-danger btn-sm" data-act="lot-del" data-arg="${r.id}">删除</button>` : ''}`)}</td></tr>`;
  }).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>活动中心</h2><span class="en">Activity Center</span></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px">${stat('全部活动', rows.length, 'Total Activities')}${stat('进行中', openN, 'Active')}${stat('抽奖场次', S.db.lotteries.length, 'Lottery Rounds')}</div>
  <div class="glass filter-bar rise" style="--i:2"><span class="cell-sub">邀请 / 签到 / 注册为系统唯一活动，抽奖可创建多场次；开启前请先完成奖励配置。</span><span style="flex:1"></span>${W('<button class="btn btn-primary" data-act="act-new">新建活动</button>')}</div>
  <div class="glass rise" style="--i:3"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>类型</th><th>活动名称</th><th>活动时间</th><th>奖励概览</th><th>开关</th><th style="width:200px">操作</th></tr></thead>
    <tbody>${trs}</tbody></table></div><div class="card-pad"></div></div>`;
};

/* 10. 抽奖活动 */
const CHANCE_SRC = ['', '每日签到', '新人注册', '持有藏品', '邀请好友', '消耗藏品', '抽奖消耗', '后台发放'];
const CHANCE_CLS = ['', 'tag-blue', 'tag-green', 'tag-orange', 'tag-orange', 'tag-red', 'tag-gray', 'tag-blue'];
const chanceTagHtml = cr => {
  if (!cr) return '<span class="cell-sub">未配置</span>';
  const t = [];
  if (cr.daily_free?.enabled) t.push(`<span class="tag tag-blue">签到 +${cr.daily_free.num ?? 1}/天</span>`);
  if (cr.register?.enabled) t.push(`<span class="tag tag-green">注册 +${cr.register.num ?? 1}</span>`);
  if (cr.hold?.enabled) t.push(`<span class="tag tag-orange">持有藏品 +${cr.hold.items?.[0]?.num ?? 1}/件</span>`);
  if (cr.invite?.enabled) t.push(`<span class="tag tag-orange">邀请 +${cr.invite.num ?? 1}/人</span>`);
  if (cr.consume?.enabled) t.push(`<span class="tag tag-red">消耗藏品 +${cr.consume.items?.[0]?.num ?? 1}/件</span>`);
  return t.length ? t.join(' ') : '<span class="cell-sub">未配置</span>';
};
PAGES.lottery = () => {
  const rows = S.db.lotteries.map(a => {
    const stock = a.prizes.reduce((s, p) => s + (p.type === 3 ? 0 : p.stock - p.sent), 0);
    return `<tr><td class="serif-num">${a.id}</td><td class="cell-main">${a.title}</td><td>${chanceTagHtml(a.chance_rules)}</td>
    <td><span class="tag ${a.status ? 'tag-green' : 'tag-gray'}">${a.status ? '开启' : '关闭'}</span></td>
    <td class="cell-sub">${fmtT(a.start_time)} ~ ${fmtT(a.end_time)}</td><td class="cell-sub">${stock.toLocaleString()} 份</td>
    <td style="white-space:nowrap">${W(`<button class="btn btn-ghost btn-sm" data-act="lot-prizes" data-arg="${a.id}">奖池配置</button>
    <button class="btn btn-ghost btn-sm" data-act="lot-edit" data-arg="${a.id}">编辑</button><button class="btn btn-danger btn-sm" data-act="lot-del" data-arg="${a.id}">删除</button>`)}
    <button class="btn btn-ghost btn-sm" data-act="lot-chances" data-arg="${a.id}">次数流水</button>
    <button class="btn btn-ghost btn-sm" data-act="lot-records" data-arg="${a.id}">中奖记录</button></td></tr>`;
  }).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>抽奖活动</h2><span class="en">Lottery</span></div>
  <div class="glass rise" style="--i:1;margin-bottom:14px"><div class="card-pad" style="padding:14px 20px"><span class="cell-sub">用户每次抽奖消耗 <b>1 次</b>机会；次数通过「签到 / 新人注册 / 持有指定藏品 / 邀请好友 / 消耗指定藏品」获得</span></div></div>
  <div class="glass filter-bar rise" style="--i:1"><span style="flex:1"></span>${W('<button class="btn btn-primary" data-act="lot-add">新建活动</button>')}</div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>ID</th><th>活动名称</th><th>次数获取方式</th><th>状态</th><th>起止时间</th><th>剩余奖池</th><th style="width:330px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table></div></div>`;
};

/* 11. 合成活动 */
PAGES.synthesis = () => {
  const rows = S.db.syntheses.map(s => `<tr>
    <td class="serif-num">${s.id}</td><td class="cell-main">${s.name}</td><td>${s.target_name}</td>
    <td>${s.materials.map(m => `<span class="tag tag-blue">${COL_NAME(m.collection_id)} ×${m.num}</span>`).join(' ')}</td>
    <td class="serif-num">${s.limit_num}</td><td class="serif-num">${s.done_num}</td>
    <td><span class="tag ${s.status ? 'tag-green' : 'tag-gray'}">${s.status ? '开启' : '关闭'}</span></td>
    <td class="cell-sub">${fmtT(s.start_time)} ~ ${fmtT(s.end_time)}</td>
    <td style="white-space:nowrap">${W(`<button class="btn btn-ghost btn-sm" data-act="syn-edit" data-arg="${s.id}">编辑</button><button class="btn btn-danger btn-sm" data-act="syn-del" data-arg="${s.id}">删除</button>`)}
    <button class="btn btn-ghost btn-sm" data-act="syn-records" data-arg="${s.id}">记录</button></td></tr>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>合成活动</h2><span class="en">Synthesis</span></div>
  <div class="glass filter-bar rise" style="--i:1"><span style="flex:1"></span>${W('<button class="btn btn-primary" data-act="syn-add">新建合成</button>')}</div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>ID</th><th>活动名称</th><th>目标藏品</th><th>材料配方</th><th>限次</th><th>已完成</th><th>状态</th><th>起止时间</th><th style="width:220px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table></div></div>`;
};

/* 12. 邀请有礼 */
PAGES.invite = () => {
  const INV_TYPE = { 1: ['藏品', 'tag-blue'], 2: ['抽奖次数', 'tag-green'], 3: ['优先购资格', 'tag-orange'] };
  const rules = S.db.inviteRules.map(r => {
    const t = INV_TYPE[r.reward_type] || ['未知', 'tag-gray'];
    const content = r.reward_type === 1 ? r.collection_name : r.reward_type === 2 ? `<span class="serif-num">${r.reward_num} 次抽奖</span>` : `<span class="serif-num">优先购 ${r.reward_num} 次 · ${r.pre_collection_id ? COL_NAME(r.pre_collection_id) : '<span style="color:var(--danger)">未绑定藏品</span>'}</span>`;
    return `<tr>
    <td class="serif-num">${r.invite_num} 人</td>
    <td><span class="tag ${t[1]}">${t[0]}</span></td>
    <td>${content}</td>
    <td><span class="tag ${r.status ? 'tag-green' : 'tag-gray'}">${r.status ? '启用' : '停用'}</span></td>
    <td style="white-space:nowrap">${W(`<button class="btn btn-ghost btn-sm" data-act="inv-edit" data-arg="${r.id}">编辑</button><button class="btn btn-danger btn-sm" data-act="inv-del" data-arg="${r.id}">删除</button>`)}</td></tr>`;
  }).join('');
  const records = S.db.inviteRecords.map(r => `<tr>
    <td>${r.inviter}</td><td>${r.invitee}</td>
    <td><span class="tag ${r.reward_status ? 'tag-green' : 'tag-gray'}">${r.reward_status ? '已发放' : '未达标'}</span></td>
    <td class="cell-sub">${fmtT(r.createtime)}</td></tr>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>邀请有礼</h2><span class="en">Invite</span></div>
  <div class="glass rise" style="--i:1"><div class="card-pad" style="display:flex;align-items:center;gap:14px;padding:14px 20px">
    <span class="cell-main">活动状态</span>
    <select class="select" id="inv-enabled" style="width:110px"><option value="1" ${+(settingVal('invite_reward_enable', 1)) ? 'selected' : ''}>开启</option><option value="0" ${+(settingVal('invite_reward_enable', 1)) ? '' : 'selected'}>关闭</option></select>
    ${W('<button class="btn btn-primary btn-sm" data-act="inv-sw-save">保存</button>')}
    <span class="cell-sub">关闭后 C 端邀请奖励暂停发放，已配置的规则保留</span></div></div>
  <div class="glass rise" style="--i:2"><div class="card-head"><h3>奖励规则</h3><span class="en">Reward Rules</span></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>邀请人数门槛</th><th>奖励类型</th><th>奖励内容</th><th>状态</th><th style="width:130px">操作</th></tr></thead><tbody>${rules}</tbody></table></div>
    <div class="card-pad">${W('<button class="btn btn-primary" data-act="inv-add">添加规则</button>')}</div></div>
  <div class="glass rise" style="--i:3"><div class="card-head"><h3>邀请记录</h3><span class="en">Records</span></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>邀请人</th><th>被邀请人</th><th>奖励状态</th><th>时间</th></tr></thead><tbody>${records}</tbody></table></div><div class="card-pad"></div></div>`;
};

/* 12b. 签到活动 */
PAGES.checkin = () => {
  const c = S.db.checkin;
  const q = S.q.checkin;
  let recs = S.db.checkinRecords.filter(r => !q.keyword || r.user.includes(q.keyword));
  const total = recs.length; recs = recs.slice((q.page - 1) * 8, q.page * 8);
  const rules = c.rules.map((r, i) => `<tr>
    <td class="cell-main">连续签到 ${r.day_num} 天</td>
    <td>${rewardTag(r)}</td>
    <td style="white-space:nowrap">${W(`<button class="btn btn-ghost btn-sm" data-act="ck-rule-edit" data-arg="${i}">编辑</button><button class="btn btn-danger btn-sm" data-act="ck-rule-del" data-arg="${i}">删除</button>`)}</td></tr>`).join('');
  const rows = recs.map(r => `<tr>
    <td>${r.user}</td><td class="cell-main">${r.date}</td>
    <td><span class="tag tag-blue">连续 ${r.continuous} 天</span></td>
    <td>${r.reward}</td>
    <td><span class="tag ${r.status ? 'tag-green' : 'tag-orange'}">${r.status ? '已发放' : '待发放'}</span></td>
    <td class="cell-sub">${fmtT(r.createtime)}</td></tr>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>签到活动</h2><span class="en">Check-in</span></div>
  <div class="glass rise" style="--i:1"><div class="card-head"><h3>签到规则</h3><span class="en">Rules</span></div>
    <div class="card-pad">
      <div class="form-grid">
        <div class="form-row"><label>活动状态</label><select class="select" id="ck-enabled"><option value="1" ${c.enabled ? 'selected' : ''}>开启</option><option value="0">关闭</option></select></div>
        <div class="form-row"><label>每月补签次数上限</label><input class="input" id="ck-repair" type="number" value="${c.repair_limit}"></div>
      </div>
      <div class="form-row"><label>每日签到奖励</label>${rewardCfgHtml('ck-daily', c.daily)}</div>
      <div class="form-row"><label>连续签到阶梯奖励（达成天数 → 额外奖励，在每日奖励之上叠加）</label>
        <div class="tbl-wrap" style="border:1px solid rgba(126,139,155,.15);border-radius:10px"><table class="tbl">
          <thead><tr><th>阶梯</th><th>奖励内容</th><th style="width:150px">操作</th></tr></thead>
          <tbody>${rules || '<tr><td colspan="3" class="cell-sub" style="text-align:center;padding:18px">暂无阶梯，点击下方添加</td></tr>'}</tbody></table></div>
        ${W('<button class="btn btn-ghost btn-sm" style="margin-top:10px" data-act="ck-rule-add">＋ 添加阶梯</button>')}</div>
      ${W('<button class="btn btn-primary" data-act="ck-save">保存签到规则</button>')}
    </div></div>
  <div class="glass rise" style="--i:2"><div class="card-head"><h3>签到记录</h3><span class="en">Records</span></div>
    <div class="card-pad" style="display:flex;gap:10px;flex-wrap:wrap;padding-bottom:0">
      <input class="input" id="ck-kw" placeholder="用户昵称" value="${esc(q.keyword)}" style="width:180px">
      <button class="btn btn-primary" data-act="ck-search">查询</button><button class="btn btn-ghost" data-act="ck-reset">重置</button></div>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>用户</th><th>签到日期</th><th>连续天数</th><th>获得奖励</th><th>状态</th><th>时间</th></tr></thead>
      <tbody>${rows}</tbody></table>${recs.length ? '' : emptyBox()}</div>
    ${pager('ck-page', q.page, total, 8)}</div>`;
};

/* 12c. 注册活动 */
PAGES.register = () => {
  const c = S.db.register;
  const q = S.q.register;
  let logs = S.db.registerLogs.filter(l => (q.status < 0 || l.status === q.status) && (!q.keyword || l.user.includes(q.keyword)));
  const total = logs.length; logs = logs.slice((q.page - 1) * 8, q.page * 8);
  const ST_NAME = ['未发售', '已排期', '发售中', '已下架', '已售罄'];
  const colOpt = (sel, mark) => S.db.collections.map(x => `<option value="${x.id}" ${sel === x.id ? 'selected' : ''}>${x.name}（${ST_NAME[x.status]}${mark && x.status !== 2 ? '，可预配置' : ''}）</option>`).join('');
  const colOpts = colOpt(c.collection_id, false);
  const preColOpts = colOpt(c.pre_collection_id, true);
  const lotOpts = S.db.lotteries.map(l => `<option value="${l.id}" ${c.lot_activity_id === l.id ? 'selected' : ''}>${l.title}（${l.status ? '开启中' : '未开启，次数待生效'}）</option>`).join('');
  const invTag = rewardTag(c.inviter || { type: 1, num: 1 });
  const rows = logs.map(l => `<tr>
    <td class="cell-main">${l.user}</td><td>${l.reward}</td>
    <td>${l.inviter ? `${l.inviter} <span class="tag tag-blue">邀请人 ${invTag}</span>` : '<span class="cell-sub">自然注册</span>'}</td>
    <td><span class="tag ${l.status ? 'tag-green' : 'tag-orange'}">${l.status ? '已发放' : '待发放'}</span></td>
    <td class="cell-sub">${fmtT(l.createtime)}</td></tr>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>注册活动</h2><span class="en">Register Gift</span></div>
  <div class="glass rise" style="--i:1"><div class="card-head"><h3>新人礼包配置</h3><span class="en">Gift Config</span></div>
    <div class="card-pad">
      <div class="form-grid">
        <div class="form-row"><label>活动状态</label><select class="select" id="rg-enabled"><option value="1" ${c.enabled ? 'selected' : ''}>开启</option><option value="0">关闭</option></select></div>
        <div class="form-row"><label>注册送抽奖次数（0 = 不送）</label><input class="input" id="rg-score" type="number" min="0" value="${c.chance}"></div>
        <div class="form-row"><label>绑定抽奖活动（次数在该场次可用）</label><select class="select" id="rg-lot"><option value="0" ${!c.lot_activity_id ? 'selected' : ''}>全部开启中的场次（通用）</option>${lotOpts}</select></div>
        <div class="form-row"><label>赠送藏品（新人空投，可选）</label><select class="select" id="rg-col"><option value="0">不赠送</option>${colOpts}</select></div>
        <div class="form-row"><label>优先购资格 · 目标藏品</label><select class="select" id="rg-pre-col"><option value="0" ${!c.pre_collection_id ? 'selected' : ''}>不送优先购</option>${preColOpts}</select></div>
        <div class="form-row"><label>优先购资格次数</label><input class="input" id="rg-pre" type="number" min="0" value="${c.pre_num ?? 0}"></div>
      </div>
      <div class="cell-sub" style="margin-bottom:12px">新用户完成注册即自动发放礼包。抽奖次数绑定指定场次时，若该场次未开启，次数先入账为「待生效」，场次开启后自动可用；优先购资格可对未发售藏品预先配置，资格在藏品开售后的优先购期内生效。</div>
      <div class="form-row"><label>邀请人额外奖励（被邀请注册时发放，0 = 不发放）</label>${rewardCfgHtml('rg-inv', c.inviter || { type: 1, num: 1 })}</div>
      ${W('<button class="btn btn-primary" data-act="rg-save">保存配置</button>')}
    </div></div>
  <div class="glass rise" style="--i:2"><div class="card-head"><h3>发放记录</h3><span class="en">Gift Logs</span></div>
    <div class="card-pad" style="display:flex;gap:10px;flex-wrap:wrap;padding-bottom:0">
      <input class="input" id="rg-kw" placeholder="用户昵称" value="${esc(q.keyword)}" style="width:180px">
      <select class="select" id="rg-status" style="width:140px"><option value="-1">全部状态</option><option value="1">已发放</option><option value="0">待发放</option></select>
      <button class="btn btn-primary" data-act="rg-search">查询</button><button class="btn btn-ghost" data-act="rg-reset">重置</button></div>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>新用户</th><th>奖励内容</th><th>邀请来源</th><th>状态</th><th>注册时间</th></tr></thead>
      <tbody>${rows}</tbody></table>${logs.length ? '' : emptyBox()}</div>
    ${pager('rg-page', q.page, total, 8)}</div>`;
};

/* 13. 公告管理 */
PAGES.notice = () => {
  const q = S.q.notice;
  let list = S.db.notices.filter(n => (!q.keyword || n.title.includes(q.keyword)) && (q.status < 0 || n.status === q.status));
  const catTag = { 1: ['公告', 'tag-blue'], 2: ['活动', 'tag-orange'], 3: ['系统', 'tag-gray'] };
  const rows = list.map(n => { const [cn, cls] = catTag[n.category]; return `<tr>
    <td><div class="cell-main">${n.title}</div></td><td><span class="tag ${cls}">${cn}</span></td>
    <td>${n.is_top ? '<span class="tag tag-orange">置顶</span>' : '-'}</td>
    <td><span class="tag ${n.status ? 'tag-green' : 'tag-gray'}">${n.status ? '显示' : '隐藏'}</span></td>
    <td class="cell-sub">${fmtT(n.publish_time)}</td>
    <td style="white-space:nowrap">${W(`<button class="btn btn-ghost btn-sm" data-act="not-edit" data-arg="${n.id}">编辑</button><button class="btn btn-danger btn-sm" data-act="not-del" data-arg="${n.id}">删除</button>`)}</td></tr>`; }).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>公告管理</h2><span class="en">Notices</span></div>
  <div class="glass filter-bar rise" style="--i:1">
    <input class="input" id="n-kw" placeholder="公告标题" value="${esc(q.keyword)}">
    <select class="select" id="n-status"><option value="-1">全部状态</option><option value="1">显示</option><option value="0">隐藏</option></select>
    <button class="btn btn-primary" data-act="not-search">查询</button><span style="flex:1"></span>
    ${W('<button class="btn btn-primary" data-act="not-add">发布公告</button>')}</div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>标题</th><th>分类</th><th>置顶</th><th>状态</th><th>发布时间</th><th style="width:130px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table>${list.length ? '' : emptyBox()}</div></div>`;
};

/* 14. 轮播管理 */
PAGES.banner = () => {
  const rows = S.db.banners.map(b => `<tr>
    <td><div class="thumb" style="width:80px;height:44px;background:linear-gradient(135deg,#56a3fc33,#7dd8ff44)"></div></td>
    <td class="cell-main">${b.title}</td><td class="cell-sub">${b.link}</td><td class="serif-num">${b.weigh}</td>
    <td><span class="tag ${b.status ? 'tag-green' : 'tag-gray'}">${b.status ? '启用' : '停用'}</span></td>
    <td style="white-space:nowrap">${W(`<button class="btn btn-ghost btn-sm" data-act="ban-edit" data-arg="${b.id}">编辑</button>
    <button class="btn btn-ghost btn-sm" data-act="ban-status" data-arg="${b.id}">${b.status ? '停用' : '启用'}</button>
    <button class="btn btn-danger btn-sm" data-act="ban-del" data-arg="${b.id}">删除</button>`)}</td></tr>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>轮播管理</h2><span class="en">Banners</span></div>
  <div class="glass filter-bar rise" style="--i:1"><span class="cell-sub">按排序值从大到小展示在 C 端首页</span><span style="flex:1"></span>${W('<button class="btn btn-primary" data-act="ban-add">添加轮播</button>')}</div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>图片</th><th>标题</th><th>跳转链接</th><th>排序</th><th>状态</th><th style="width:210px">操作</th></tr></thead>
    <tbody>${rows}</tbody></table></div></div>`;
};

/* 15. 运营参数 */
PAGES.setting = () => {
  const s = S.db.settings;
  const groupCard = (key, title, en) => `<div class="glass rise"><div class="card-head"><h3>${title}</h3><span class="en">${en}</span></div>
    <div class="card-pad">${s[key].map((it, i) => `<div class="form-row"><label>${it.title}</label>
      <input class="input set-input" data-group="${key}" data-idx="${i}" value="${esc(it.value)}"></div>`).join('')}
    ${W(`<button class="btn btn-primary" data-act="set-save" data-arg="${key}">保存本组</button>`)}</div></div>`;
  return `
  <div class="page-head rise" style="--i:0"><h2>运营参数</h2><span class="en">Settings · 超级管理员</span></div>
  <div class="glass rise" style="--i:1"><div class="card-head"><h3>平台品牌</h3><span class="en">Brand</span></div>
    <div class="card-pad" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
      <div class="upload-box" data-act="set-logo">${s.brand.site_logo ? '<img src="' + s.brand.site_logo + '">' : '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m5 19 5.5-5.5L14 17l3-3 4 4"/></svg><span>上传 Logo</span>'}</div>
      <div style="flex:1;min-width:200px"><div class="form-row"><label>平台名称（保存后登录页、侧栏、C 端即时同步）</label>
        <input class="input" id="brand-name" value="${esc(s.brand.site_name)}"></div>
        ${W('<button class="btn btn-primary" data-act="set-brand">保存品牌</button>')}</div></div></div>
  ${groupCard('trade', '交易参数', 'Trade')}
  ${groupCard('activity', '活动参数', 'Activity')}
  ${groupCard('reward', '奖励参数', 'Reward')}`;
};

/* 16. 数据清理 */
PAGES.cleanup = () => {
  const db = S.db;
  const groups = [
    ['notice', '公告', [['公告', db.notices.length]]],
    ['content', '内容', [['公告', db.notices.length], ['轮播', db.banners.length]]],
    ['trade', '交易资产', [['订单', db.orders.length], ['挂单', db.market.length], ['流水', db.walletLogs.length], ['持仓', db.holds.length]]],
    ['activity', '活动记录', [['抽奖记录', db.lotteryRecords.length], ['合成记录', db.synthesisRecords.length], ['邀请记录', db.inviteRecords.length], ['发放任务', db.airdrops.length]]],
  ];
  return `
  <div class="page-head rise" style="--i:0"><h2>数据清理</h2><span class="en">Cleanup · 超级管理员</span></div>
  <div class="glass rise" style="--i:1"><div class="card-pad" style="border-left:3px solid var(--danger)">
    <b style="color:var(--danger);display:inline-flex;align-items:center;gap:6px"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.5 20h19z"/><path d="M12 10v4.5"/><circle cx="12" cy="17.2" r="0.6" fill="currentColor"/></svg>硬删除，不可恢复</b>
    <div class="cell-sub" style="margin-top:6px">勾选要清理的数据组 → 在底部输入「清空」确认 → 执行后回显删除结果。所有操作将记入日志审计。</div></div></div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
    ${groups.map((g, i) => `<div class="glass rise" style="--i:${i + 2}"><div class="card-head"><h3>${g[1]}</h3><span class="en">${g[0]}</span></div>
      <div class="card-pad">${g[2].map(t => `<label style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;cursor:pointer;font-size:13px">
        <span><input type="checkbox" class="clean-ck" value="${g[0]}"> ${t[0]}</span>
        <span class="serif-num">${t[1]} 条</span></label>`).join('')}</div></div>`).join('')}
    <div class="glass rise" style="--i:6"><div class="card-head"><h3>全体用户</h3><span class="en">Users</span></div>
      <div class="card-pad"><label style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;cursor:pointer;font-size:13px">
        <span><input type="checkbox" class="clean-ck" value="user"> 平台用户（连带资产）</span><span class="serif-num">${db.users.length} 人</span></label></div></div>
  </div>
  <div class="glass rise" style="--i:7"><div class="card-pad" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    <span>请输入「<b style="color:var(--danger)">清空</b>」以确认：</span>
    <input class="input" id="clean-confirm" style="width:120px" placeholder="清空">
    ${W('<button class="btn btn-danger" id="clean-btn" data-act="clean-run" disabled>执行清理</button>')}
    <span id="clean-result" class="cell-sub"></span></div></div>`;
};

/* 17. 日志审计 */
PAGES.operlog = () => {
  const q = S.q.operlog;
  let list = S.db.operlogs.filter(l => !q.keyword || (l.admin + l.module + l.action).includes(q.keyword));
  const total = list.length; list = list.slice((q.page - 1) * 10, q.page * 10);
  const rows = list.map(l => `<tr>
    <td class="serif-num">${l.id}</td><td>${l.admin}</td><td><span class="tag tag-blue">${l.module}</span></td>
    <td>${l.action}</td><td class="cell-main">${l.target}</td>
    <td class="cell-sub" title="${esc(l.detail)}" style="max-width:220px;overflow:hidden;text-overflow:ellipsis">${l.detail}</td>
    <td class="cell-sub">${l.ip}</td><td class="cell-sub">${fmtT(l.createtime)}</td></tr>`).join('');
  return `
  <div class="page-head rise" style="--i:0"><h2>日志审计</h2><span class="en">Audit Log</span></div>
  <div class="glass filter-bar rise" style="--i:1">
    <input class="input" id="ol-kw" placeholder="操作人 / 模块 / 动作" value="${esc(q.keyword)}">
    <button class="btn btn-primary" data-act="ol-search">查询</button><button class="btn btn-ghost" data-act="ol-reset">重置</button></div>
  <div class="glass rise" style="--i:2"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>ID</th><th>操作人</th><th>模块</th><th>动作</th><th>目标</th><th>详情</th><th>IP</th><th>时间</th></tr></thead>
    <tbody>${rows}</tbody></table></div>${pager('ol-page', q.page, total)}</div>`;
};

/* ---------- 弹窗与交互动作 ---------- */
const ACT = {
  login() {
    const u = $('#lg-user').value.trim(), p = $('#lg-pass').value.trim();
    if (!u || !p) return toast('请输入账号和密码', 'err');
    renderShell(); renderPage(); toast('登录成功，欢迎回来', 'ok');
  },
  logout() { confirmBox('确定退出登录？', 'logout-ok', ''); },
  'logout-ok'() { closeModal(); renderLogin(); },
  chpwd() { openModal(`<div class="modal-head"><h3>修改密码</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body"><div class="form-row"><label>原密码</label><input class="input" type="password"></div>
    <div class="form-row"><label>新密码</label><input class="input" type="password"></div>
    <div class="form-row"><label>确认新密码</label><input class="input" type="password"></div></div>
    <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="modal-close-ok">保存</button></div>`); },
  'modal-close-ok'() { closeModal(); toast('保存成功', 'ok'); },
  'modal-close'() { closeModal(); },
  'modal-mask-click'(el, e) { if (e && e.target === el) closeModal(); },
  collapse() { S.collapsed = !S.collapsed; localStorage.setItem('nft_sidebar', S.collapsed ? '1' : '0'); $('#sidebar').classList.toggle('collapsed', S.collapsed); },
  hamburger() { $('#sidebar').classList.toggle('mobile-open'); $('#shade').style.display = $('#sidebar').classList.contains('mobile-open') ? 'block' : 'none'; },
  shade() { closeMobile(); },
  'toggle-readonly'() { S.readonly = !S.readonly; renderShell(); renderPage(); toast(S.readonly ? '已切换为只读模式（写按钮全部隐藏）' : '写操作已开启', 'ok'); },
  nav(el) { go(el.dataset.arg); },

  /* 用户 */
  'user-search'() { S.q.user.keyword = $('#u-kw').value; S.q.user.status = +$('#u-status').value; S.q.user.page = 1; renderPage(); },
  'user-reset'() { S.q.user = { keyword: '', status: -1, page: 1 }; renderPage(); },
  'user-page'(el) { S.q.user.page = +el.dataset.arg; renderPage(); },
  'user-detail'(el) {
    const u = S.db.users.find(x => x.id == el.dataset.arg);
    openModal(`<div class="modal-head"><h3>用户详情</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body">
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px 20px;margin-bottom:16px">
        ${[['昵称', u.nickname], ['账号', u.username], ['手机', u.mobile], ['邮箱', u.email || '-'], ['实名', u.real_name || '未实名'], ['邀请码', u.invite_code]].map(x => `<div><label style="display:block;font-size:12px;color:var(--ink-muted)">${x[0]}</label><b>${esc(x[1])}</b></div>`).join('')}
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px">
        <div class="stat-card glass"><span class="orb orb-blue" style="width:60px;height:60px;right:-8px;top:-12px"></span><div class="label">钱包余额</div><div class="num serif-num" style="font-size:22px">￥${fmtY(u.balance)}</div><div class="sub">冻结 ￥${fmtY(u.frozen)}</div></div>
        <div class="stat-card glass"><div class="label">累计收入</div><div class="num serif-num" style="font-size:22px">￥${fmtY(u.total_income)}</div><div class="sub">支出 ￥${fmtY(u.total_expense)}</div></div>
        <div class="stat-card glass"><div class="label">持仓数</div><div class="num serif-num" style="font-size:22px">${u.hold_count}</div><div class="sub">订单 ${u.order_count}</div></div>
      </div></div>`);
  },
  'user-edit'(el) { openUserForm(S.db.users.find(x => x.id == el.dataset.arg)); },
  'user-add'() { openUserForm(null); },
  'user-status'(el) {
    const u = S.db.users.find(x => x.id == el.dataset.arg); u.status = u.status ? 0 : 1;
    toast(`用户「${u.nickname}」已${u.status ? '启用' : '禁用'}`, 'ok'); renderPage();
    S.db.operlogs.unshift({ id: 400, admin: '管理员', module: '用户管理', action: u.status ? '启用' : '禁用', target: '用户#' + u.id, detail: '', ip: '113.88.20.66', createtime: NOW });
  },
  'user-del'(el) { const u = S.db.users.find(x => x.id == el.dataset.arg); confirmBox(`确认删除用户「${u.nickname}」？删除后不可恢复。`, 'user-del-ok', u.id); },
  'user-del-ok'(el) { S.db.users = S.db.users.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },
  'user-batchdel'() {
    const ids = [...document.querySelectorAll('.u-ck:checked')].map(c => +c.value);
    if (!ids.length) return toast('请先勾选要删除的用户', 'err');
    confirmBox(`确认批量删除选中的 ${ids.length} 个用户？删除后不可恢复。`, 'user-batchdel-ok', ids.join(','));
  },
  'user-batchdel-ok'(el) { const ids = el.dataset.arg.split(',').map(Number); S.db.users = S.db.users.filter(u => !ids.includes(u.id)); closeModal(); toast(`已删除 ${ids.length} 个用户`, 'ok'); renderPage(); },

  /* 钱包 */
  'wallet-search'() { S.q.wallet.keyword = $('#w-kw').value; S.q.wallet.page = 1; renderPage(); },
  'wallet-reset'() { S.q.wallet = { keyword: '', page: 1 }; renderPage(); },
  'wallet-page'(el) { S.q.wallet.page = +el.dataset.arg; renderPage(); },
  'wallet-expand'(el) { S.expandedWallet = S.expandedWallet == el.dataset.arg ? 0 : +el.dataset.arg; renderPage(); },
  'wallet-adjust'(el) {
    const u = S.db.users.find(x => x.id == el.dataset.arg);
    openModal(`<div class="modal-head"><h3>余额调整 · ${u.nickname}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body"><div class="form-row"><label>调整金额（元，负数为扣减，当前余额 ￥${fmtY(u.balance)}）</label><input class="input" id="adj-amt" type="number" placeholder="如 100 或 -50"></div>
    <div class="form-row"><label>备注</label><input class="input" id="adj-rem" placeholder="调整原因"></div></div>
    <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="wallet-adjust-ok" data-arg="${u.id}">确认调整</button></div>`);
  },
  'wallet-adjust-ok'(el) {
    const u = S.db.users.find(x => x.id == el.dataset.arg);
    const amt = Math.round(+$('#adj-amt').value * 100);
    if (!amt) return toast('请输入有效金额', 'err');
    if (amt < 0 && u.balance + amt < 0) return toast('余额不足', 'err');
    u.balance += amt; if (amt > 0) u.total_income += amt; else u.total_expense += -amt;
    S.db.walletLogs.unshift({ id: 7000, user_id: u.id, type: 'admin_adjust', related_no: '', amount: amt, before: u.balance - amt, after: u.balance, remark: $('#adj-rem').value || '管理员调整', createtime: NOW });
    closeModal(); toast('调整成功', 'ok'); renderPage();
  },

  /* 藏品 */
  'col-search'() { S.q.collection.keyword = $('#c-kw').value; S.q.collection.category_id = +$('#c-cat').value; S.q.collection.status = +$('#c-status').value; renderPage(); },
  'col-sale'(el) {
    const c = S.db.collections.find(x => x.id == el.dataset.arg);
    openModal(`<div class="modal-head"><h3>开售 · ${c.name}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body"><div class="form-row"><label>发售开始时间（到时 C 端自动开抢）*</label><input class="input" id="sale-time" type="datetime-local" value="2026-11-01T10:00"></div>
    <div class="form-row"><label>发售结束时间（可选；到期自动置为「已售罄」，C 端仍展示、仅停止购买）</label><input class="input" id="sale-end-time" type="datetime-local" value="${c.sale_end_time ? toLocalInput(c.sale_end_time) : ''}"></div>
    <div class="cell-sub">发行量 ${c.stock.toLocaleString()} 份 · 售价 ￥${fmtY(c.price)} · 二级市场${c.resale ? '已开启（限价 ￥' + fmtY(c.resale_price_limit || 0) + '）' : '关闭'}</div></div>
    <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="col-sale-ok" data-arg="${c.id}">确认开售</button></div>`);
  },
  'col-sale-ok'(el) {
    const c = S.db.collections.find(x => x.id == el.dataset.arg);
    const start = new Date($('#sale-time').value).getTime() / 1000 || NOW;
    const end = $('#sale-end-time').value ? new Date($('#sale-end-time').value).getTime() / 1000 : 0;
    if (end && end <= start) return toast('发售结束时间必须晚于开始时间', 'err');
    if (c.pre_rule_type && c.pre_sale_time && c.pre_sale_time >= start) return toast('优先购开始时间需早于发售开始时间，请先在「编辑」中调整优先购配置', 'err');
    c.status = 2; c.sale_time = start; c.sale_end_time = end;
    closeModal(); toast(`「${c.name}」已开售`, 'ok'); renderPage();
    logOp('藏品管理', '开售', '藏品#' + c.id, end ? '开售至 ' + fmtT(end) : '不限时');
  },
  'col-off'(el) { const c = S.db.collections.find(x => x.id == el.dataset.arg); confirmBox(`确认下架「${c.name}」？C 端将立即停止购买入口。`, 'col-off-ok', c.id); },
  'col-off-ok'(el) { const c = S.db.collections.find(x => x.id == el.dataset.arg); c.status = 3; closeModal(); toast('已下架', 'ok'); renderPage(); logOp('藏品管理', '下架', '藏品#' + c.id, ''); },
  'col-soldout'(el) { const c = S.db.collections.find(x => x.id == el.dataset.arg); confirmBox(`确认停止发售「${c.name}」？C 端将显示「已售罄」，未售库存不再开放购买。`, 'col-soldout-ok', c.id); },
  'col-soldout-ok'(el) {
    const c = S.db.collections.find(x => x.id == el.dataset.arg);
    c.status = 4; closeModal(); toast(`「${c.name}」已停止发售，C 端显示已售罄`, 'ok'); renderPage();
    logOp('藏品管理', '停止发售', '藏品#' + c.id, `已售 ${c.sold}/${c.stock}，置为售罄`);
  },
  'col-add'() { openColForm(null); },
  'col-edit'(el) { openColForm(S.db.collections.find(x => x.id == el.dataset.arg)); },
  'col-del'(el) {
    const c = S.db.collections.find(x => x.id == el.dataset.arg);
    // 引用检查：被业务数据引用的藏品禁止硬删（与分类管理同一防脏数据策略）
    const refs = [];
    if (S.db.orders.some(o => o.collection_id == c.id)) refs.push('订单');
    if (S.db.holds.some(h => h.collection_id == c.id)) refs.push('持仓');
    if (S.db.market.some(m => m.collection_id == c.id)) refs.push('市场挂单');
    if (S.db.raffles.some(r => r.collection_id == c.id)) refs.push('抽签发售');
    if (S.db.airdrops.some(t => t.collection_id == c.id)) refs.push('定向发放');
    if (S.db.lotteries.some(l => (l.prizes || []).some(p => +p.collection_id === c.id) || ['hold', 'consume'].some(k => l.chance_rules?.[k]?.items?.some(it => +it.collection_id === c.id)))) refs.push('抽奖活动');
    if (S.db.syntheses.some(s => s.target_collection_id == c.id || (s.materials || []).some(m => m.collection_id == c.id))) refs.push('合成活动');
    if (S.db.register.collection_id == c.id || S.db.register.pre_collection_id == c.id) refs.push('注册活动');
    if (S.db.inviteRules.some(r => r.collection_id == c.id || r.pre_collection_id == c.id)) refs.push('邀请有礼');
    if (S.db.checkin.daily?.collection_id == c.id || S.db.checkin.daily?.pre_collection_id == c.id || S.db.checkin.rules.some(r => r.collection_id == c.id || r.pre_collection_id == c.id)) refs.push('签到活动');
    if (refs.length) return toast(`藏品「${c.name}」被 ${refs.join('、')} 引用，请先解除引用再删除`, 'err');
    confirmBox(`确认删除藏品「${c.name}」？`, 'col-del-ok', c.id);
  },
  'col-del-ok'(el) { S.db.collections = S.db.collections.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },

  /* 分类 */
  'cat-add'() { openCatForm(null); },
  'cat-edit'(el) { openCatForm(S.db.categories.find(x => x.id == el.dataset.arg)); },
  'cat-status'(el) { const c = S.db.categories.find(x => x.id == el.dataset.arg); c.status = c.status ? 0 : 1; toast('已' + (c.status ? '启用' : '停用'), 'ok'); renderPage(); },
  'cat-del'(el) {
    const c = S.db.categories.find(x => x.id == el.dataset.arg);
    if (c.count > 0) return toast(`该分类下有 ${c.count} 个藏品，不能删除`, 'err');
    confirmBox(`确认删除分类「${c.name}」？`, 'cat-del-ok', c.id);
  },
  'cat-del-ok'(el) { S.db.categories = S.db.categories.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },

  /* 订单 */
  'order-search'() { S.q.order.keyword = $('#o-kw').value; S.q.order.type = +$('#o-type').value; S.q.order.status = +$('#o-status').value; S.q.order.page = 1; renderPage(); },
  'order-reset'() { S.q.order = { keyword: '', type: 0, status: -1, page: 1 }; renderPage(); },
  'order-page'(el) { S.q.order.page = +el.dataset.arg; renderPage(); },
  'order-detail'(el) {
    const o = S.db.orders.find(x => x.id == el.dataset.arg); const [st, cls] = orderStatusTag(o.status);
    const payCn = { wechat: '微信支付', alipay: '支付宝', balance: '余额支付' };
    openModal(`<div class="modal-head"><h3>订单详情</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body"><table class="tbl"><tbody>
      ${[['订单号', o.order_no], ['买家', `${o.nickname}（#${o.user_id}）`], ['藏品', o.collection_name], ['类型', o.type === 1 ? '一级购' : '市场购'], ['数量', o.num + ' 份'], ['实付金额', `<span class="serif-num">￥${fmtY(o.amount)}</span>`], ['支付方式', payCn[o.pay_type] || '-'], ['支付单号', o.pay_no || '-'], ['状态', `<span class="tag ${cls}">${st}</span>`], ['下单时间', fmtT(o.createtime)], ['支付时间', fmtT(o.paytime)]].map(x => `<tr><td style="width:90px;color:var(--ink-muted)">${x[0]}</td><td class="cell-main">${x[1]}</td></tr>`).join('')}
    </tbody></table></div>
    <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">关闭</button></div>`);
  },

  /* 市场 */
  'market-tab'(el) { S.q.market.tab = +el.dataset.arg; renderPage(); },
  'market-search'() { S.q.market.status = +$('#m-status').value; renderPage(); },
  'market-off'(el) { const m = S.db.market.find(x => x.id == el.dataset.arg); confirmBox(`确认强制下架「${m.collection_name}」（${m.sn}）的挂单？`, 'market-off-ok', m.id); },
  'market-off-ok'(el) { const m = S.db.market.find(x => x.id == el.dataset.arg); m.status = 2; closeModal(); toast('已下架，持仓已回到卖家账户', 'ok'); renderPage(); logOp('市场寄售', '下架', '挂单#' + m.id, ''); },

  /* 持仓 */
  'hold-search'() { S.q.hold.keyword = $('#h-kw').value; S.q.hold.collection_id = +$('#h-col').value; S.q.hold.status = +$('#h-status').value; renderPage(); },
  'hold-reset'() { S.q.hold = { keyword: '', status: -1, collection_id: 0 }; renderPage(); },

  /* 定向发放 */
  'air-add'() { openAirForm(); },
  'air-retry'(el) {
    const t = S.db.airdrops.find(x => x.id == el.dataset.arg);
    t.status = 1; renderPage(); toast(`任务「${t.name}」开始发放…`, 'ok');
    setTimeout(() => {
      const fail = t.status === 0 ? Math.floor(t.num * 0.02) : Math.min(t.fail_num, 5);
      t.success_num = t.num - fail; t.fail_num = fail; t.status = 2; renderPage();
      toast(`发放完成：入仓 ${t.success_num}，跳过 ${t.fail_num}`, 'ok');
      logOp('定向发放', '执行发放', '任务#' + t.id, `${t.num} 份`);
    }, 1400);
  },
  'air-revoke'(el) {
    const t = S.db.airdrops.find(x => x.id == el.dataset.arg);
    confirmBox(`确认撤销任务「${t.name}」的发放批次？仍处于持有状态的持仓将被回收（已寄售/锁定的不可回收），撤销后可补发`, 'air-revoke-ok', t.id);
  },
  'air-revoke-ok'(el) {
    const t = S.db.airdrops.find(x => x.id == el.dataset.arg);
    const revoked = t.success_num - t.revoke_num;
    t.revoke_num = t.success_num;
    closeModal(); toast(`撤销完成：回收 ${revoked} 份，可点「补发差额」重新发放`, 'ok'); renderPage();
    logOp('定向发放', '撤销批次', '任务#' + t.id, `回收 ${revoked} 份`);
  },
  'air-del'(el) { const t = S.db.airdrops.find(x => x.id == el.dataset.arg); confirmBox(`确认删除任务「${t.name}」？`, 'air-del-ok', t.id); },
  'air-del-ok'(el) { S.db.airdrops = S.db.airdrops.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },

  /* 活动中心 */
  'act-toggle'(el) {
    const [type, idStr] = String(el.dataset.arg).split(':');
    let msg = '状态已更新';
    if (type === 'invite') { const v = +(settingVal('invite_reward_enable', 1)) ? 0 : 1; settingSet('invite_reward_enable', v); msg = v ? '邀请有礼已开启' : '邀请有礼已关闭'; }
    else if (type === 'checkin') { S.db.checkin.enabled = +!S.db.checkin.enabled; msg = S.db.checkin.enabled ? '签到活动已开启' : '签到活动已关闭'; }
    else if (type === 'register') { S.db.register.enabled = +!S.db.register.enabled; msg = S.db.register.enabled ? '注册活动已开启' : '注册活动已关闭'; }
    else { const a = S.db.lotteries.find(x => x.id == idStr); a.status = +!a.status; msg = a.status ? '活动已开启' : '活动已关闭'; }
    const name = (el.closest && el.closest('tr')?.querySelector('.cell-main')?.innerText.trim()) || type;
    toast(msg, 'ok');
    renderPage(); logOp('活动中心', '切换开关', name, '');
  },
  'act-reward'(el) {
    const [type, idStr] = String(el.dataset.arg).split(':');
    if (type === 'lottery') { openLotForm(S.db.lotteries.find(x => x.id == idStr)); return; }
    go(type);
  },
  'act-new'() {
    openModal(`<div class="modal-head"><h3>新建活动</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body">
      <div class="form-row"><label>活动类型 *</label><select class="select" id="act-new-type">
        <option value="lottery">抽奖活动（可创建多场次）</option>
        <option value="invite">邀请有礼（系统唯一，去配置）</option>
        <option value="checkin">签到活动（系统唯一，去配置）</option>
        <option value="register">注册活动（系统唯一，去配置）</option></select></div>
      <div class="form-row"><label>活动名称 *</label><input class="input" id="act-new-name" placeholder="如：双十一 · 天天抽"></div>
      <div class="cell-sub">抽奖活动创建后默认关闭，请在「配置奖励」中完成奖池与次数获取方式配置后再开启。</div>
    </div>
    <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="act-new-ok">创建</button></div>`);
  },
  'act-new-ok'() {
    const type = $('#act-new-type').value;
    const name = $('#act-new-name').value.trim();
    if (type !== 'lottery') {
      closeModal(); toast('该类型为系统唯一活动，已为你打开配置页', 'ok');
      go(type);
      return;
    }
    if (!name) return toast('请输入活动名称', 'err');
    const a = { id: Date.now() % 10000, title: name, chance_rules: { daily_free: { enabled: false, num: 1 }, register: { enabled: false, num: 2 }, hold: { enabled: false, items: [] }, invite: { enabled: false, num: 1, daily_cap: 5 }, consume: { enabled: false, items: [] } }, status: 0, start_time: NOW, end_time: NOW + 10 * 86400, prizes: [] };
    S.db.lotteries.push(a);
    closeModal(); renderPage(); logOp('活动中心', '新建活动', name, '抽奖场次');
    toast('活动已创建（默认关闭），请配置奖励后再开启', 'ok');
    openLotForm(a);
  },

  /* 抽奖 */
  'lot-add'() { openLotForm(null); },
  'lot-edit'(el) { openLotForm(S.db.lotteries.find(x => x.id == el.dataset.arg)); },
  'lot-del'(el) { const a = S.db.lotteries.find(x => x.id == el.dataset.arg); confirmBox(`确认删除活动「${a.title}」及奖池？`, 'lot-del-ok', a.id); },
  'lot-del-ok'(el) { S.db.lotteries = S.db.lotteries.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },
  'lot-prizes'(el) { openPrizeModal(S.db.lotteries.find(x => x.id == el.dataset.arg)); },
  'lot-records'(el) {
    const a = S.db.lotteries.find(x => x.id == el.dataset.arg);
    openModal(`<div class="modal-head"><h3>中奖记录 · ${a.title}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body"><table class="tbl"><thead><tr><th>用户</th><th>奖品</th><th>时间</th></tr></thead><tbody>
    ${S.db.lotteryRecords.map(r => `<tr><td>${r.user}</td><td class="cell-main">${r.prize_name}</td><td class="cell-sub">${fmtT(r.createtime)}</td></tr>`).join('')}
    </tbody></table></div><div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">关闭</button></div>`, );
  },
  'lot-save'() {
    const id = +$('#lot-id').value || 0;
    const old = id ? S.db.lotteries.find(x => x.id === id) : null;
    if (!$('#lot-title').value.trim()) return toast('请输入活动名称', 'err');
    const cr = readChanceRules();
    if (cr.hold.enabled && !cr.hold.items.length) return toast('「持有指定藏品」请选择藏品', 'err');
    if (cr.consume.enabled && !cr.consume.items.length) return toast('「消耗指定藏品」请选择藏品', 'err');
    const start = $('#lot-start').value ? Math.floor(new Date($('#lot-start').value).getTime() / 1000) : (old?.start_time || NOW);
    const end = $('#lot-end').value ? Math.floor(new Date($('#lot-end').value).getTime() / 1000) : (old?.end_time || NOW + 10 * 86400);
    if (end <= start) return toast('结束时间必须晚于开始时间', 'err');
    const status = +$('#lot-status').value;
    // 开启校验：奖池非空且概率合计 > 0（奖池在「奖池配置」中维护，编辑本表单不改动奖池）
    const prizes = old ? old.prizes : [];
    if (status === 1 && (!prizes.length || prizes.reduce((s, p) => s + (p.probability || 0), 0) <= 0)) return toast('开启活动前请先在「奖池配置」中添加奖品并设置概率', 'err');
    const a = { id, title: $('#lot-title').value.trim(), chance_rules: cr, status, start_time: start, end_time: end, prizes };
    if (old) Object.assign(old, a); else { a.id = Date.now() % 10000; S.db.lotteries.push(a); }
    closeModal(); toast('保存成功', 'ok'); renderPage(); logOp('抽奖活动', '保存活动', '活动#' + a.id, a.title);
  },
  'lot-chances'(el) {
    const a = S.db.lotteries.find(x => x.id == el.dataset.arg);
    const logs = S.db.chanceLogs;
    openModal(`<div class="modal-head"><h3>抽奖次数流水${a ? ' · ' + a.title : ''}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body"><table class="tbl"><thead><tr><th>ID</th><th>用户</th><th>次数变动</th><th>来源</th><th>关联说明</th><th>时间</th></tr></thead><tbody>
    ${logs.map(l => `<tr><td class="serif-num">${l.id}</td><td>${l.user}</td><td><span class="serif-num ${l.num > 0 ? 'amount-in' : 'amount-out'}">${l.num > 0 ? '+' : ''}${l.num}</span></td>
    <td><span class="tag ${CHANCE_CLS[l.source] || 'tag-gray'}">${CHANCE_SRC[l.source] || '未知'}</span></td><td class="cell-sub">${l.ref || '-'}</td><td class="cell-sub">${fmtT(l.createtime)}</td></tr>`).join('')}
    </tbody></table>
    ${W(`<div class="grant-box" style="margin-top:12px;padding-top:10px;border-top:1px dashed #3d7ff040">
      <div class="form-row"><label>手动补发次数（客服补偿等）</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap"><input class="input" id="g-user" type="number" placeholder="用户ID" style="width:110px">
      <input class="input" id="g-num" type="number" placeholder="次数" style="width:80px" value="1">
      <input class="input" id="g-remark" placeholder="备注">
      <button class="btn btn-primary" data-act="grant-ok">补发</button></div></div></div>`)}
    </div><div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">关闭</button></div>`);
  },
  'grant-ok'() {
    const uid = +$('#g-user').value, num = +$('#g-num').value || 0;
    if (!uid) return toast('请填写用户ID', 'err');
    if (num <= 0) return toast('补发次数必须大于 0', 'err');
    S.db.chanceLogs.unshift({ id: Date.now() % 100000, user_id: uid, user: '用户#' + uid, num, source: 7, ref: $('#g-remark').value.trim() || '后台手动补发', createtime: NOW });
    closeModal(); toast(`已向用户#${uid} 补发 ${num} 次抽奖机会`, 'ok'); logOp('抽奖活动', '补发次数', '用户#' + uid, num + ' 次');
  },

  'raf-add'() { openRafForm(null); },
  'raf-edit'(el) { openRafForm(S.db.raffles.find(x => x.id == el.dataset.arg)); },
  'raf-del'(el) { const r = S.db.raffles.find(x => x.id == el.dataset.arg); confirmBox(`确认删除抽签活动「${COL_NAME(r.collection_id)}」？报名记录将一并删除`, 'raf-del-ok', r.id); },
  'raf-del-ok'(el) { S.db.raffles = S.db.raffles.filter(x => x.id != el.dataset.arg); S.db.raffleApplys = S.db.raffleApplys.filter(a => a.raffle_id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },
  'raf-draw'(el) {
    const r = S.db.raffles.find(x => x.id == el.dataset.arg);
    const quota = rafQuota(r);
    confirmBox(`确认对「${COL_NAME(r.collection_id)}」执行抽签？共 ${r.apply_count.toLocaleString()} 人报名，中签名额 ${quota.toLocaleString()} 个，结果不可撤销`, 'raf-draw-ok', r.id);
  },
  'raf-draw-ok'(el) {
    const r = S.db.raffles.find(x => x.id == el.dataset.arg);
    const applies = S.db.raffleApplys.filter(a => a.raffle_id == r.id);
    // 随机中签：名额 = floor(total/per_limit)，报名不足全中
    const quota = Math.min(rafQuota(r), applies.length);
    const shuffled = [...applies].sort(() => Math.random() - 0.5);
    const winIds = new Set(shuffled.slice(0, quota).map(a => a.id));
    applies.forEach(a => { a.status = winIds.has(a.id) ? 1 : 2; a.win_num = winIds.has(a.id) ? r.per_limit : 0; });
    r.status = 3; r.win_count = quota;
    closeModal(); toast(`抽签完成：${applies.length} 人报名，${quota} 人中签`, 'ok'); renderPage();
    logOp('抽签发售', '执行抽签', '活动#' + r.id, `报名 ${applies.length} 人，中签 ${quota} 人`);
  },
  'raf-applys'(el) {
    const r = S.db.raffles.find(x => x.id == el.dataset.arg);
    const list = S.db.raffleApplys.filter(a => a.raffle_id == r.id);
    const stTag = s => [['待抽签', 'tag-gray'], ['已中签', 'tag-green'], ['未中签', 'tag-red']][s];
    openModal(`<div class="modal-head"><h3>报名记录 · ${COL_NAME(r.collection_id)}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body"><table class="tbl"><thead><tr><th>ID</th><th>用户</th><th>抽签状态</th><th>可购数量</th><th>已购买</th><th>报名时间</th></tr></thead><tbody>
    ${list.map(a => { const [t, cls] = a.status ? stTag(a.status) : stTag(0); return `<tr><td class="serif-num">${a.id}</td><td>${a.user}</td>
      <td><span class="tag ${cls}">${t}</span></td><td class="serif-num">${a.win_num || '-'}</td>
      <td><span class="tag ${a.paid ? 'tag-green' : 'tag-gray'}">${a.paid ? '已购' : '未购'}</span></td><td class="cell-sub">${fmtT(a.createtime)}</td></tr>`; }).join('')}
    </tbody></table></div><div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">关闭</button></div>`);
  },

  /* 合成 */
  'syn-add'() { openSynForm(null); },
  'syn-edit'(el) { openSynForm(S.db.syntheses.find(x => x.id == el.dataset.arg)); },
  'syn-del'(el) { const s = S.db.syntheses.find(x => x.id == el.dataset.arg); confirmBox(`确认删除合成活动「${s.name}」？`, 'syn-del-ok', s.id); },
  'syn-del-ok'(el) { S.db.syntheses = S.db.syntheses.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },
  'syn-records'() {
    openModal(`<div class="modal-head"><h3>合成记录</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
    <div class="modal-body"><table class="tbl"><thead><tr><th>用户</th><th>结果</th><th>时间</th></tr></thead><tbody>
    ${S.db.synthesisRecords.map(r => `<tr><td>${r.user}</td><td><span class="tag ${r.status === 1 ? 'tag-green' : 'tag-red'}">${r.status === 1 ? '成功' : '失败'}</span></td><td class="cell-sub">${fmtT(r.createtime)}</td></tr>`).join('')}
    </tbody></table></div><div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">关闭</button></div>`);
  },
  'syn-save'() {
    const rows = [...document.querySelectorAll('.mat-row')].map(r => ({ collection_id: +r.querySelector('.mat-col').value, num: +r.querySelector('.mat-num').value || 1 })).filter(m => m.collection_id);
    const s = { id: $('#syn-id').value ? +$('#syn-id').value : 0, name: $('#syn-name').value, target_collection_id: +$('#syn-target').value, materials: rows, limit_num: +$('#syn-limit').value || 0, status: +$('#syn-status').value, start_time: NOW - 86400, end_time: NOW + 20 * 86400, done_num: 0, target_name: COL_NAME(+$('#syn-target').value) };
    if (!s.name || !s.target_collection_id || !rows.length) return toast('请完整填写活动信息与材料配方', 'err');
    if (s.id) { Object.assign(S.db.syntheses.find(x => x.id === s.id), s); } else { s.id = Date.now() % 10000; S.db.syntheses.push(s); }
    closeModal(); toast('保存成功', 'ok'); renderPage(); logOp('合成活动', '保存活动', '活动#' + s.id, s.name);
  },

  /* 邀请 */
  'inv-add'() { openInvForm(null); },
  'inv-edit'(el) { openInvForm(S.db.inviteRules.find(x => x.id == el.dataset.arg)); },
  'inv-del'(el) { confirmBox('确认删除该奖励规则？', 'inv-del-ok', el.dataset.arg); },
  'inv-sw-save'() {
    const v = +$('#inv-enabled').value;
    settingSet('invite_reward_enable', v);
    toast(v ? '邀请有礼已开启，C 端即时生效' : '邀请有礼已关闭，C 端暂停发放', 'ok'); renderPage();
    logOp('邀请有礼', v ? '开启活动' : '关闭活动', 'invite', '');
  },
  'inv-del-ok'(el) { S.db.inviteRules = S.db.inviteRules.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },
  'inv-save'() {
    const r = { id: $('#inv-id').value ? +$('#inv-id').value : 0, invite_num: +$('#inv-num').value || 0, reward_type: +$('#inv-type').value, collection_id: 0, pre_collection_id: 0, reward_num: 0, status: 1 };
    if (!r.invite_num) return toast('请输入邀请人数门槛', 'err');
    if (r.reward_type === 1) {
      r.collection_id = +$('#inv-col').value || 0;
      if (!r.collection_id) return toast('请选择奖励藏品', 'err');
    } else {
      r.reward_num = +$('#inv-num2').value || 0;
      if (!r.reward_num) return toast(r.reward_type === 3 ? '请输入优先购可用次数' : '请输入奖励抽奖次数', 'err');
      if (r.reward_type === 3) {
        r.pre_collection_id = +$('#inv-pre-col').value || 0;
        if (!r.pre_collection_id) return toast('优先购资格需选择目标藏品（未发售藏品可预配置）', 'err');
      }
    }
    r.collection_name = COL_NAME(r.collection_id);
    if (r.id) Object.assign(S.db.inviteRules.find(x => x.id === r.id), r); else S.db.inviteRules.push(r);
    closeModal(); toast('保存成功', 'ok'); renderPage(); logOp('邀请有礼', '保存规则', '规则#' + (r.id || 'new'), '');
  },

  /* 公告 */
  'not-search'() { S.q.notice.keyword = $('#n-kw').value; S.q.notice.status = +$('#n-status').value; renderPage(); },
  'not-add'() { openNotForm(null); },
  'not-edit'(el) { openNotForm(S.db.notices.find(x => x.id == el.dataset.arg)); },
  'not-del'(el) { confirmBox('确认删除该公告？', 'not-del-ok', el.dataset.arg); },
  'not-del-ok'(el) { S.db.notices = S.db.notices.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },

  /* 轮播 */
  'ban-add'() { openBanForm(null); },
  'ban-edit'(el) { openBanForm(S.db.banners.find(x => x.id == el.dataset.arg)); },
  'ban-status'(el) { const b = S.db.banners.find(x => x.id == el.dataset.arg); b.status = b.status ? 0 : 1; toast('已' + (b.status ? '启用' : '停用'), 'ok'); renderPage(); },
  'ban-del'(el) { confirmBox('确认删除该轮播？', 'ban-del-ok', el.dataset.arg); },
  'ban-del-ok'(el) { S.db.banners = S.db.banners.filter(x => x.id != el.dataset.arg); closeModal(); toast('删除成功', 'ok'); renderPage(); },

  /* 运营参数 */
  'set-logo'() { toast('原型演示：真实系统此处为图片上传组件（BaInput image）'); },
  'set-brand'() {
    S.db.settings.brand.site_name = $('#brand-name').value || S.db.settings.brand.site_name;
    document.title = S.db.settings.brand.site_name + ' · 管理后台';
    toast('品牌已保存，全站即时生效', 'ok'); renderShell(); renderPage(); logOp('运营参数', '修改品牌', 'brand', S.db.settings.brand.site_name);
  },
  'set-save'(el) {
    const key = el.dataset.arg;
    document.querySelectorAll(`.set-input[data-group="${key}"]`).forEach(inp => { S.db.settings[key][+inp.dataset.idx].value = inp.value; });
    toast('参数已保存', 'ok'); logOp('运营参数', '保存参数', key + ' 组', '');
  },

  /* 数据清理 */
  'clean-run'(el) {
    const groups = [...new Set([...document.querySelectorAll('.clean-ck:checked')].map(c => c.value))];
    if (!groups.length) return toast('请先勾选要清理的数据', 'err');
    if ($('#clean-confirm').value !== '清空') return toast('请输入「清空」确认', 'err');
    confirmBox(`确认清空所选 ${groups.length} 组数据？<b style="color:var(--danger)">硬删除不可恢复！</b>`, 'clean-run-ok', groups.join(','));
  },
  'clean-run-ok'(el) {
    const groups = el.dataset.arg.split(',');
    let msg = [];
    if (groups.includes('notice')) { msg.push('公告 ' + S.db.notices.length + ' 条'); S.db.notices = []; }
    if (groups.includes('content')) { msg.push('轮播 ' + S.db.banners.length + ' 条'); S.db.banners = []; }
    if (groups.includes('trade')) { msg.push('订单 ' + S.db.orders.length + ' / 挂单 ' + S.db.market.length + ' / 流水 ' + S.db.walletLogs.length + ' / 持仓 ' + S.db.holds.length); S.db.orders = []; S.db.market = []; S.db.walletLogs = []; S.db.holds = []; }
    if (groups.includes('activity')) { msg.push('活动记录已清空'); S.db.lotteryRecords = []; S.db.synthesisRecords = []; S.db.inviteRecords = []; S.db.airdrops = []; }
    if (groups.includes('user')) { msg.push('用户 ' + S.db.users.length + ' 人'); S.db.users = []; }
    closeModal(); toast('清理完成', 'ok');
    const r = $('#clean-result'); if (r) r.innerHTML = `<span class="tag tag-green">已清理：${msg.join('，')}</span>`;
    logOp('数据清理', '清空', groups.join(','), '硬删除');
  },

  /* 签到活动 */
  'ck-save'() {
    const c = S.db.checkin;
    c.enabled = +$('#ck-enabled').value; c.repair_limit = +$('#ck-repair').value || 0;
    c.daily = rewardCfgRead('ck-daily');
    if (!rewardCfgCheck(c.daily)) return toast(c.daily.type === 3 ? '每日签到优先购奖励需选择目标藏品' : '每日签到藏品奖励需选择藏品', 'err');
    toast('签到规则已保存，C 端即时生效', 'ok'); renderPage(); logOp('签到活动', '保存规则', 'checkin', `每日 ${rewardTxt(c.daily)}`);
  },
  'ck-search'() { S.q.checkin.keyword = $('#ck-kw').value; S.q.checkin.page = 1; renderPage(); },
  'ck-reset'() { S.q.checkin = { keyword: '', page: 1 }; renderPage(); },
  'ck-page'(el) { S.q.checkin.page = +el.dataset.arg; renderPage(); },
  'ck-rule-add'() { openCkRuleForm(null); },
  'ck-rule-edit'(el) { openCkRuleForm(S.db.checkin.rules[+el.dataset.arg], +el.dataset.arg); },
  'ck-rule-del'(el) { confirmBox('确认删除该签到阶梯？', 'ck-rule-del-ok', el.dataset.arg); },
  'ck-rule-del-ok'(el) { S.db.checkin.rules.splice(+el.dataset.arg, 1); closeModal(); toast('删除成功', 'ok'); renderPage(); },
  'ck-rule-save'() {
    const idx = $('#ckr-idx').value;
    const rule = { day_num: +$('#ckr-day').value || 0, ...rewardCfgRead('ckr') };
    if (!rule.day_num) return toast('请输入连续天数', 'err');
    if (rule.num < 1) return toast('奖励数量不能小于 1', 'err');
    if (!rewardCfgCheck(rule)) return toast(rule.type === 3 ? '优先购奖励需选择目标藏品' : '藏品奖励需选择藏品', 'err');
    if (idx === '') S.db.checkin.rules.push(rule); else S.db.checkin.rules[+idx] = rule;
    S.db.checkin.rules.sort((a, b) => a.day_num - b.day_num);
    closeModal(); toast('保存成功', 'ok'); renderPage(); logOp('签到活动', '阶梯配置', `连续${rule.day_num}天`, rewardTxt(rule));
  },

  /* 注册活动 */
  'rg-save'() {
    const c = S.db.register;
    c.enabled = +$('#rg-enabled').value; c.chance = +$('#rg-score').value || 0;
    c.lot_activity_id = +$('#rg-lot').value || 0;
    c.collection_id = +$('#rg-col').value;
    c.pre_collection_id = +$('#rg-pre-col').value || 0;
    c.pre_num = +$('#rg-pre').value || 0;
    c.inviter = rewardCfgRead('rg-inv');
    /* 抽奖次数绑定说明：0 = 通用（所有开启中的场次均可用），绑定指定场次则次数在该场次内使用（场次未开启时待生效） */
    if (c.pre_num > 0 && !c.pre_collection_id) return toast('优先购资格需选择目标藏品（未发售藏品可预配置）', 'err');
    if (c.pre_num > 0 && c.pre_collection_id && ![0, 1, 2].includes(S.db.collections.find(x => x.id === c.pre_collection_id).status)) return toast('该藏品已下架或售罄，不能再配置优先购资格', 'err');
    if (!rewardCfgCheck(c.inviter)) return toast('邀请人藏品奖励需选择藏品', 'err');
    const lotName = c.lot_activity_id ? (S.db.lotteries.find(l => l.id === c.lot_activity_id) || {}).title || '' : '通用';
    toast('新人礼包配置已保存，新注册用户即时生效', 'ok'); renderPage();
    logOp('注册活动', '保存配置', 'register', `${c.chance} 次抽奖（${lotName}）` + (c.pre_num ? ` + 优先购 ${c.pre_num} 次` : ''));
  },
  'rg-search'() { S.q.register.keyword = $('#rg-kw').value; S.q.register.status = +$('#rg-status').value; S.q.register.page = 1; renderPage(); },
  'rg-reset'() { S.q.register = { keyword: '', status: -1, page: 1 }; renderPage(); },
  'rg-page'(el) { S.q.register.page = +el.dataset.arg; renderPage(); },

  /* 弹窗内部复用动作 */
  'lot-prize-row-add'() { addPrizeRow(); refreshProbability(); },
  'lot-prize-row-del'(el) { el.closest('.prize-row').remove(); refreshProbability(); },
  'lot-save-prizes'() {
    const id = +$('#pz-act-id').value;
    const a = S.db.lotteries.find(x => x.id === id);
    // 保留已发放数：按奖品名匹配旧奖池（重存奖池不清空已抽出发放记录）
    const oldSent = {};
    (a.prizes || []).forEach(p => { if (!(p.name in oldSent)) oldSent[p.name] = p.sent || 0; });
    const prizes = [...document.querySelectorAll('.prize-row')].map((r, i) => {
      const name = r.querySelector('.pz-name').value;
      const p = {
        id: i + 1, name, type: +r.querySelector('.pz-type').value,
        collection_id: r.querySelector('.pz-col') ? (+r.querySelector('.pz-col').value || 0) : 0,
        num: r.querySelector('.pz-num') ? (+r.querySelector('.pz-num').value || 0) : 0,
        probability: +r.querySelector('.pz-prob').value || 0, stock: +r.querySelector('.pz-stock').value || 0,
        sent: oldSent[name] ?? 0,
      };
      return p;
    }).filter(p => p.name);
    const totalP = prizes.reduce((s, p) => s + p.probability, 0);
    if (totalP > 10000) return toast('概率合计超过 10000（100%），请调整', 'err');
    a.prizes = prizes; closeModal(); toast(`奖池已保存（概率合计 ${(totalP / 100).toFixed(1)}%，已发放数已保留）`, 'ok'); logOp('抽奖活动', '奖池配置', '活动#' + id, '');
  },
  'mat-row-add'() {
    const rows = document.getElementById('mat-rows');
    rows.insertAdjacentHTML('beforeend', matRowHtml());
  },
  'mat-row-del'(el) { el.closest('.mat-row').remove(); },
};

function logOp(module, action, target, detail) {
  S.db.operlogs.unshift({ id: 400 + Math.floor(Math.random() * 999), admin: '管理员', module, action, target, detail, ip: '113.88.20.66', createtime: NOW });
}

/* ---------- 各类表单弹窗 ---------- */
function openUserForm(u) {
  const isEdit = !!u;
  openModal(`<div class="modal-head"><h3>${isEdit ? '编辑会员' : '新增会员'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <div class="form-grid">
    <div class="form-row"><label>昵称 *</label><input class="input" id="uf-nick" value="${esc(u?.nickname || '')}"></div>
    ${isEdit ? '' : '<div class="form-row"><label>账号 *</label><input class="input" id="uf-user" placeholder="登录账号"></div>'}
    <div class="form-row"><label>手机号</label><input class="input" id="uf-mobile" value="${esc(u?.mobile || '')}"></div>
    <div class="form-row"><label>邮箱</label><input class="input" id="uf-email" value="${esc(u?.email || '')}"></div>
    <div class="form-row"><label>实名姓名</label><input class="input" id="uf-real" value="${esc(u?.real_name || '')}"></div>
    <div class="form-row"><label>状态</label><select class="select" id="uf-status"><option value="1">启用</option><option value="0">禁用</option></select></div>
    </div></div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="user-form-ok" data-arg="${u?.id || 0}">保存</button></div>`);
  if (u) $('#uf-status').value = u.status;
}
ACT['user-form-ok'] = el => {
  const id = +el.dataset.arg;
  const nick = $('#uf-nick').value.trim();
  if (!nick) return toast('昵称不能为空', 'err');
  if (id) {
    const u = S.db.users.find(x => x.id === id);
    Object.assign(u, { nickname: nick, mobile: $('#uf-mobile').value, email: $('#uf-email').value, real_name: $('#uf-real').value, status: +$('#uf-status').value });
  } else {
    const username = $('#uf-user').value.trim();
    if (!username) return toast('账号不能为空', 'err');
    if (S.db.users.some(x => x.username === username)) return toast('账号已存在', 'err');
    S.db.users.unshift({ id: 10001 + S.db.users.length + 20, nickname: nick, username, mobile: $('#uf-mobile').value, email: $('#uf-email').value, real_name: $('#uf-real').value, invite_code: 'IC' + Math.floor(Math.random() * 90000 + 10000), balance: 0, frozen: 0, total_income: 0, total_expense: 0, order_count: 0, hold_count: 0, status: +$('#uf-status').value, last_login_time: 0, createtime: NOW });
    logOp('用户管理', '添加', username, '');
  }
  closeModal(); toast('保存成功', 'ok'); renderPage();
};

/* ========== 富文本编辑器（contenteditable + execCommand，工具栏纯文本/SVG，无 emoji 图标） ========== */
function editorToolbarHtml() {
  const btn = (cmd, arg, label, title, style) => `<button type="button" class="rte-btn" data-cmd="${cmd}" data-arg="${arg || ''}" title="${title}" ${style ? `style="${style}"` : ''}>${label}</button>`;
  const sep = '<span class="rte-sep"></span>';
  return [
    btn('bold', '', 'B', '加粗', 'font-weight:700;min-width:26px'),
    btn('italic', '', 'I', '斜体', 'font-style:italic;min-width:26px'),
    btn('underline', '', 'U', '下划线', 'text-decoration:underline;min-width:26px'),
    btn('strikeThrough', '', 'S', '删除线', 'text-decoration:line-through;min-width:26px'),
    sep,
    btn('formatBlock', '<p>', '正文', '正文段落'),
    btn('formatBlock', '<h3>', '标题', '标题（H3）'),
    btn('formatBlock', '<blockquote>', '引用', '引用块'),
    sep,
    btn('insertUnorderedList', '', '• 列表', '无序列表'),
    btn('insertOrderedList', '', '1. 列表', '有序列表'),
    sep,
    btn('justifyLeft', '', '居左', '左对齐'),
    btn('justifyCenter', '', '居中', '居中对齐'),
    btn('justifyRight', '', '居右', '右对齐'),
    sep,
    `<button type="button" class="rte-btn" data-url="createLink" title="插入链接">链接</button>`,
    `<button type="button" class="rte-btn" data-url="insertImage" title="插入图片（URL）">图片</button>`,
    `<button type="button" class="rte-btn" data-color="1" title="文字颜色">颜色<input type="color" class="rte-color" value="#333333"></button>`,
    sep,
    btn('removeFormat', '', '清除格式', '清除选中格式'),
  ].join('');
}
function editorHtml(id, content) {
  return `<div class="rte" id="rte-${id}">
    <div class="rte-bar">${editorToolbarHtml()}</div>
    <div class="rte-url" style="display:none"><input class="input" placeholder="请输入 URL 后回车确认" /><button type="button" class="btn btn-primary btn-sm">确定</button><span class="cell-sub" style="cursor:pointer" data-act="ed-url-cancel">取消</span></div>
    <div class="rte-area editor-mock" contenteditable="true">${content || '<p>请输入内容…</p>'}</div>
  </div>`;
}
function editorVal(id) {
  const el = document.querySelector(`#rte-${id} .rte-area`);
  return el ? el.innerHTML : '';
}
function bindEditor(id) {
  const root = document.getElementById('rte-' + id);
  if (!root) return;
  const area = root.querySelector('.rte-area');
  const urlRow = root.querySelector('.rte-url');
  const urlInput = urlRow.querySelector('input');
  let pendingCmd = '';
  root.querySelectorAll('.rte-btn').forEach(b => {
    b.addEventListener('mousedown', e => e.preventDefault()); // 保持编辑区选区
    b.addEventListener('click', () => {
      if (b.dataset.url) {
        pendingCmd = b.dataset.url;
        urlInput.placeholder = pendingCmd === 'createLink' ? '请输入链接 URL 后回车' : '请输入图片 URL 后回车';
        urlRow.style.display = 'flex'; urlInput.focus(); return;
      }
      if (b.dataset.color) return; // 颜色由 input[type=color] 处理
      document.execCommand(b.dataset.cmd, false, b.dataset.arg || null);
      area.focus();
    });
  });
  const colorInput = root.querySelector('.rte-color');
  colorInput.addEventListener('mousedown', e => e.stopPropagation());
  colorInput.addEventListener('click', e => e.preventDefault());
  colorInput.addEventListener('input', () => {
    document.execCommand('foreColor', false, colorInput.value);
    area.focus();
  });
  const apply = () => {
    const url = urlInput.value.trim();
    if (!url) return toast('请输入 URL', 'err');
    document.execCommand(pendingCmd, false, url);
    urlInput.value = ''; urlRow.style.display = 'none'; area.focus();
  };
  urlRow.querySelector('button').addEventListener('click', apply);
  urlInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); apply(); } });
}
ACT['ed-url-cancel'] = el => { const row = el.closest('.rte-url'); row.querySelector('input').value = ''; row.style.display = 'none'; };

function openColForm(c) {
  const isEdit = !!c;
  const catOpts = S.db.categories.map(x => `<option value="${x.id}" ${c?.category_id === x.id ? 'selected' : ''}>${x.name}</option>`).join('');
  const colOptsPre = S.db.collections.filter(x => !c || x.id !== c.id).map(x => `<option value="${x.id}" ${c?.pre_rule_type === 1 && +c.pre_rule_value === x.id ? 'selected' : ''}>${x.name}</option>`).join('');
  openModal(`<div class="modal-head"><h3>${isEdit ? '编辑藏品' : '新增藏品'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <div class="form-grid">
      <div class="form-row"><label>藏品名称 *</label><input class="input" id="cf-name" value="${esc(c?.name || '')}"></div>
      <div class="form-row"><label>作者</label><input class="input" id="cf-author" value="${esc(c?.author || '')}"></div>
      <div class="form-row"><label>分类 *</label><select class="select" id="cf-cat">${catOpts}</select></div>
      <div class="form-row"><label>发行量 *</label><input class="input" id="cf-stock" type="number" value="${c?.stock || ''}"></div>
      <div class="form-row"><label>售价（元）*</label><input class="input" id="cf-price" type="number" step="0.01" value="${c ? fmtY(c.price) : ''}"></div>
      <div class="form-row"><label>市场参考价（元）</label><input class="input" id="cf-mkt" type="number" step="0.01" value="${c ? fmtY(c.market_price) : ''}"></div>
      <div class="form-row"><label>二级市场</label><select class="select" id="cf-resale"><option value="1">开启</option><option value="0">关闭</option></select></div>
      <div class="form-row" id="cf-rpl-row"><label>寄售限价（元）*</label><input class="input" id="cf-rpl" type="number" step="0.01" min="0" placeholder="用户挂单价格不得超过此价" value="${c && c.resale ? fmtY(c.resale_price_limit || 0) : ''}"></div>
      <div class="form-row"><label>封面图（上传组件演示）</label><div class="upload-box" data-act="set-logo"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg><span>上传封面</span></div></div>
    </div>
    <div class="form-row"><label>图文详情（富文本）</label>
      ${editorHtml('col', c?.detail || '<p>请输入藏品介绍…</p>')}</div>
    <div class="form-row"><label>优先购（窗口内仅资格用户可购，至开售时间自动对全员开放）</label>
      <div class="chance-box" style="gap:10px">
        <div class="chance-item"><label class="ck">资格方式</label>
          <select class="select" id="cf-pre-type" style="width:200px"><option value="0">不启用</option><option value="1">持有指定藏品（优先购凭证）</option><option value="2">白名单用户</option></select></div>
        <div class="chance-item"><label class="ck">优先购开始时间</label><input class="input" id="cf-pre-time" type="datetime-local" style="width:220px"></div>
        <div class="chance-item" id="cf-pre-col-row" style="display:none"><label class="ck">凭证藏品</label>
          <select class="select" id="cf-pre-col" style="width:200px"><option value="">请选择藏品</option>${colOptsPre}</select></div>
        <div class="chance-item" id="cf-pre-list-row" style="display:none"><label class="ck">白名单用户ID</label>
          <input class="input" id="cf-pre-list" placeholder="10001,10002,10003" style="flex:1" value="${c?.pre_rule_type === 2 ? esc(c.pre_rule_value) : ''}"></div>
        <div class="chance-item" id="cf-pre-limit-row" style="display:none"><label class="ck">优先购每人限购</label>
          <input class="input" id="cf-pre-limit" type="number" min="1" value="${c?.pre_limit || 1}" style="width:90px"></div>
      </div></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="col-form-ok" data-arg="${c?.id || 0}">保存</button></div>`);
  if (c) $('#cf-resale').value = c.resale;
  const syncResale = () => { $('#cf-rpl-row').style.display = +$('#cf-resale').value === 1 ? '' : 'none'; };
  $('#cf-resale').onchange = syncResale;
  syncResale();
  bindEditor('col');
  const preType = $('#cf-pre-type');
  const syncPreRows = () => {
    const t = +preType.value;
    $('#cf-pre-col-row').style.display = t === 1 ? 'flex' : 'none';
    $('#cf-pre-list-row').style.display = t === 2 ? 'flex' : 'none';
    $('#cf-pre-limit-row').style.display = t > 0 ? 'flex' : 'none';
  };
  preType.onchange = syncPreRows;
  preType.value = String(c?.pre_rule_type || 0);
  $('#cf-pre-time').value = c?.pre_sale_time ? toLocalInput(c.pre_sale_time) : '';
  syncPreRows();
}
ACT['col-form-ok'] = el => {
  const id = +el.dataset.arg;
  const name = $('#cf-name').value.trim();
  const price = Math.round((+$('#cf-price').value || 0) * 100);
  if (!name) return toast('请输入藏品名称', 'err');
  if (!price) return toast('请输入售价', 'err');
  const resale = +$('#cf-resale').value;
  const rpl = Math.round((+$('#cf-rpl').value || 0) * 100);
  if (resale === 1 && rpl <= 0) return toast('开启二级市场必须设置寄售限价', 'err');
  const data = { name, author: $('#cf-author').value, category_id: +$('#cf-cat').value, price, market_price: Math.round((+$('#cf-mkt').value || 0) * 100), stock: +$('#cf-stock').value || 0, resale, resale_price_limit: resale === 1 ? rpl : 0, detail: editorVal('col') };
  if (data.stock <= 0) return toast('发行量必须大于 0', 'err');
  // 优先购配置
  const preType = +$('#cf-pre-type').value;
  if (preType > 0) {
    const tstr = $('#cf-pre-time').value;
    if (!tstr) return toast('请选择优先购开始时间', 'err');
    const val = preType === 1 ? $('#cf-pre-col').value : $('#cf-pre-list').value.trim();
    if (!val) return toast(preType === 1 ? '请选择优先购凭证藏品' : '请填写白名单用户ID', 'err');
    if (preType === 1 && id && +val === id) return toast('凭证藏品不能选择本品自身', 'err');
    Object.assign(data, { pre_rule_type: preType, pre_rule_value: val, pre_sale_time: Math.floor(new Date(tstr).getTime() / 1000), pre_limit: +$('#cf-pre-limit').value || 1 });
  } else {
    Object.assign(data, { pre_rule_type: 0, pre_rule_value: '', pre_sale_time: 0, pre_limit: 1 });
  }
  if (id) {
    const old = S.db.collections.find(x => x.id === id);
    if (data.stock < old.sold) return toast(`发行量（${data.stock.toLocaleString()}）不能小于已售数量（${old.sold.toLocaleString()}）`, 'err');
    Object.assign(old, data);
  } else { data.id = Date.now() % 10000; data.sold = 0; data.status = 0; data.sale_time = 0; data.sale_end_time = 0; data.createtime = NOW; S.db.collections.unshift(data); }
  closeModal(); toast('保存成功', 'ok'); renderPage(); logOp('藏品管理', id ? '编辑' : '添加', name, id ? (preType ? '含优先购配置' : '') : '');
};

function openCatForm(c) {
  const isEdit = !!c;
  openModal(`<div class="modal-head"><h3>${isEdit ? '编辑分类' : '新增分类'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body"><div class="form-row"><label>分类名称 *</label><input class="input" id="ct-name" value="${esc(c?.name || '')}"></div>
  <div class="form-row"><label>排序（越大越前）</label><input class="input" id="ct-weigh" type="number" value="${c?.weigh ?? 0}"></div>
  <div class="form-row"><label>状态</label><select class="select" id="ct-status"><option value="1">启用</option><option value="0">停用</option></select></div></div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="cat-form-ok" data-arg="${c?.id || 0}">保存</button></div>`);
  if (c) $('#ct-status').value = c.status;
}
ACT['cat-form-ok'] = el => {
  const id = +el.dataset.arg; const name = $('#ct-name').value.trim();
  if (!name) return toast('请输入分类名称', 'err');
  const data = { name, weigh: +$('#ct-weigh').value || 0, status: +$('#ct-status').value };
  if (id) Object.assign(S.db.categories.find(x => x.id === id), data);
  else S.db.categories.push({ id: Date.now() % 10000, count: 0, createtime: NOW, ...data });
  closeModal(); toast('保存成功', 'ok'); renderPage(); logOp('分类管理', id ? '编辑' : '添加', name, '');
};

function openAirForm() {
  const colOpts = S.db.collections.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
  const RULE_OPTS = { 1: '指定用户（输入用户 ID）', 2: '全部用户', 3: '按邀请数（邀请 ≥ N 人）', 4: '持有指定藏品（凭证空投，持有 N 份投 N 份）', 5: '持仓门槛（持仓 ≥ N 份）', 6: '白名单导入（用户 ID / 手机号）', 7: '按消费金额（累计消费 ≥ N 元）', 8: '按注册时间（某日期前注册）', 9: '随机抽样（全体用户随机抽）', 10: '按用户标签（含指定标签）', 11: '邀请排行（邀请数 Top N）' };
  const ruleOpts = Object.entries(RULE_OPTS).map(([k, v]) => `<option value="${k}">${v}</option>`).join('');
  openModal(`<div class="modal-head"><h3>创建空投任务</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <div class="form-row"><label>任务名称 *</label><input class="input" id="air-name" placeholder="如：九色鹿持有者空投"></div>
    <div class="form-grid">
      <div class="form-row"><label>发放藏品 *</label><select class="select" id="air-col">${colOpts}</select></div>
      <div class="form-row"><label>发放份数 *</label><input class="input" id="air-num" type="number" placeholder="1000"></div>
    </div>
    <div class="form-row"><label>目标规则 *</label><select class="select" id="air-rule">${ruleOpts}</select></div>
    <div class="form-row" id="air-val-row"><label>用户 ID / 白名单（每行一个，支持手机号）*</label><textarea class="input" id="air-val" rows="5" placeholder="每行一个，如：&#10;10001&#10;10002&#10;13800138000"></textarea></div>
    <div class="form-row" id="air-col-row" style="display:none"><label>凭证藏品 *（持有即可空投，持有 N 份投 N 份）</label><select class="select" id="air-val-col">${colOpts}</select></div>
    <div class="form-row" id="air-num-row" style="display:none"><label class="air-num-label">门槛数量 *</label><input class="input" id="air-val-num" type="number" min="0"></div>
    <div class="form-row" id="air-date-row" style="display:none"><label>注册早于日期 *</label><input class="input" id="air-val-date" type="date"></div>
    <div class="form-row" id="air-tag-row" style="display:none"><label>标签名 *（用户管理中维护）</label><input class="input" id="air-val-tag" placeholder="如：vip"></div>
    <div class="form-row" id="air-none-row" style="display:none"><span class="cell-sub">该规则无需参数</span></div>
    <div class="form-grid">
      <div class="form-row"><label>到账方式</label><select class="select" id="air-claim"><option value="0">直接到账</option><option value="1">需用户领取（C 端确认）</option></select></div>
      <div class="form-row"><label>重复拥有策略</label><select class="select" id="air-dup"><option value="1">跳过（已拥有计跳过）</option><option value="2">换发抽奖次数</option><option value="3">强制叠加</option></select></div>
      <div class="form-row" id="air-reward-row" style="display:none"><label>换发抽奖次数 *</label><input class="input" id="air-reward" type="number" min="1" placeholder="如：1"></div>
      <div class="form-row"><label>定时空投（可选，到点自动执行）</label><input class="input" id="air-plan" type="datetime-local"></div>
    </div>
    <div class="form-row"><label>空投凭证文案（C 端展示发放原因，可选）</label><input class="input" id="air-reason" placeholder="如：因持有「敦煌九色鹿」获得"></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="air-form-ok">创建任务</button></div>`);
  const NUM_LABELS = { 3: '邀请数门槛 *', 5: '持仓数量门槛 *', 7: '累计消费门槛（元）*', 11: '排行取前 N 名 *' };
  const sync = () => {
    const t = +$('#air-rule').value;
    $('#air-val-row').style.display = [1, 6].includes(t) ? '' : 'none';
    $('#air-col-row').style.display = t === 4 ? '' : 'none';
    $('#air-num-row').style.display = NUM_LABELS[t] ? '' : 'none';
    if (NUM_LABELS[t]) $('.air-num-label').textContent = NUM_LABELS[t];
    $('#air-date-row').style.display = t === 8 ? '' : 'none';
    $('#air-tag-row').style.display = t === 10 ? '' : 'none';
    $('#air-none-row').style.display = [2, 9].includes(t) ? '' : 'none';
  };
  $('#air-rule').onchange = sync;
  $('#air-dup').onchange = () => { $('#air-reward-row').style.display = +$('#air-dup').value === 2 ? '' : 'none'; };
  sync();
}
ACT['air-form-ok'] = () => {
  const name = $('#air-name').value.trim();
  const num = +$('#air-num').value || 0;
  const ruleType = +$('#air-rule').value;
  if (!name || !num) return toast('请填写任务名称与份数', 'err');
  let ruleValue = '';
  if ([1, 6].includes(ruleType)) ruleValue = $('#air-val').value.trim().split(/[\n,，]+/).map(x => x.trim()).filter(Boolean).join(',');
  else if (ruleType === 4) ruleValue = $('#air-val-col').value;
  else if ([3, 5, 7, 11].includes(ruleType)) ruleValue = $('#air-val-num').value;
  else if (ruleType === 8) ruleValue = $('#air-val-date').value;
  else if (ruleType === 10) ruleValue = $('#air-val-tag').value.trim();
  if ([1, 3, 4, 5, 6, 7, 8, 10, 11].includes(ruleType) && !ruleValue) return toast('请填写规则参数', 'err');
  const dup = +$('#air-dup').value;
  const reward = +$('#air-reward').value || 0;
  if (dup === 2 && !reward) return toast('换发抽奖次数策略需填写换发次数', 'err');
  S.db.airdrops.unshift({ id: Date.now() % 10000, name, collection_id: +$('#air-col').value, collection_name: COL_NAME(+$('#air-col').value), num, rule_type: ruleType, rule_value: ruleValue, success_num: 0, fail_num: 0, revoke_num: 0, claim_mode: +$('#air-claim').value, dup_policy: dup, dup_reward: reward, plan_time: $('#air-plan').value ? new Date($('#air-plan').value).getTime() / 1000 : null, reason: $('#air-reason').value.trim(), claim_pending: 0, status: 0, createtime: NOW });
  closeModal(); toast('任务已创建，可点击「执行发放」或等待定时执行', 'ok'); renderPage(); logOp('定向发放', '创建任务', name, num + ' 份');
};

function openLotForm(a) {
  const isEdit = !!a;
  const cr = a?.chance_rules || {};
  const ck = (id, label, on, right) => `<div class="chance-item"><label class="ck"><input type="checkbox" id="${id}" ${on ? 'checked' : ''}> ${label}</label><span class="cell-sub">${right}</span></div>`;
  const colOpts = sel => S.db.collections.map(c => `<option value="${c.id}" ${sel === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
  openModal(`<div class="modal-head"><h3>${isEdit ? '编辑活动' : '新建活动'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <input type="hidden" id="lot-id" value="${a?.id || 0}">
    <div class="form-grid">
    <div class="form-row"><label>活动名称 *</label><input class="input" id="lot-title" value="${esc(a?.title || '')}"></div>
    <div class="form-row"><label>状态</label><select class="select" id="lot-status"><option value="1">开启</option><option value="0">关闭</option></select></div>
    <div class="form-row"><label>开始时间</label><input class="input" id="lot-start" type="datetime-local" value="${a?.start_time ? toLocalInput(a.start_time) : ''}"></div>
    <div class="form-row"><label>结束时间</label><input class="input" id="lot-end" type="datetime-local" value="${a?.end_time ? toLocalInput(a.end_time) : ''}"></div></div>
    <div class="form-row"><label>抽奖次数获取方式（每次抽奖消耗 1 次）</label>
      <div class="chance-box">
        ${ck('c-df', '每日签到赠送', cr.daily_free?.enabled, `+ <input class="input" id="c-df-num" type="number" min="0" style="width:70px" value="${cr.daily_free?.num ?? 1}"> 次/天`)}
        ${ck('c-rg', '新人注册赠送', cr.register?.enabled, `+ <input class="input" id="c-rg-num" type="number" min="0" style="width:70px" value="${cr.register?.num ?? 2}"> 次（仅一次）`)}
        ${ck('c-inv', '邀请好友', cr.invite?.enabled, `+ <input class="input" id="c-inv-num" type="number" min="0" style="width:60px" value="${cr.invite?.num ?? 1}"> 次/人 · 每日上限 <input class="input" id="c-inv-cap" type="number" min="0" style="width:60px" value="${cr.invite?.daily_cap ?? 5}" title="0 = 不限制">`)}
        ${ck('c-hold', '持有指定藏品', cr.hold?.enabled, `<select class="select" id="c-hold-col" style="width:150px"><option value="">选择藏品</option>${colOpts(cr.hold?.items?.[0]?.collection_id)}</select> 每件 + <input class="input" id="c-hold-num" type="number" min="0" style="width:60px" value="${cr.hold?.items?.[0]?.num ?? 2}"> 次`)}
        ${ck('c-cons', '消耗(销毁)指定藏品', cr.consume?.enabled, `<select class="select" id="c-cons-col" style="width:150px"><option value="">选择藏品</option>${colOpts(cr.consume?.items?.[0]?.collection_id)}</select> 每件换 <input class="input" id="c-cons-num" type="number" min="0" style="width:60px" value="${cr.consume?.items?.[0]?.num ?? 1}"> 次`)}
      </div></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="lot-save">保存</button></div>`);
  if (a) $('#lot-status').value = a.status; else $('#lot-status').value = 0;
}
const readChanceRules = () => ({
  daily_free: { enabled: $('#c-df').checked, num: +$('#c-df-num').value || 0 },
  register: { enabled: $('#c-rg').checked, num: +$('#c-rg-num').value || 0 },
  invite: { enabled: $('#c-inv').checked, num: +$('#c-inv-num').value || 0, daily_cap: +$('#c-inv-cap').value || 0 },
  hold: { enabled: $('#c-hold').checked, items: $('#c-hold').checked && $('#c-hold-col').value ? [{ collection_id: +$('#c-hold-col').value, num: +$('#c-hold-num').value || 1 }] : [] },
  consume: { enabled: $('#c-cons').checked, items: $('#c-cons').checked && $('#c-cons-col').value ? [{ collection_id: +$('#c-cons-col').value, num: +$('#c-cons-num').value || 1 }] : [] },
});

function openRafForm(r) {
  const colOpts = S.db.collections.map(c => `<option value="${c.id}" ${r?.collection_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
  openModal(`<div class="modal-head"><h3>${r ? '编辑抽签活动' : '新建抽签活动'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <div class="form-row"><label>发售藏品 *</label><select class="select" id="raf-col"><option value="">请选择藏品</option>${colOpts}</select></div>
    <div class="form-grid">
    <div class="form-row"><label>发售数量 *</label><input class="input" id="raf-total" type="number" min="1" value="${r?.total || ''}"></div>
    <div class="form-row"><label>中签者每人限购 *</label><input class="input" id="raf-limit" type="number" min="1" value="${r?.per_limit ?? 1}"></div></div>
    <div class="form-row"><label>报名开始时间 *</label><input class="input" id="raf-as" type="datetime-local"></div>
    <div class="form-row"><label>报名截止时间 *</label><input class="input" id="raf-ae" type="datetime-local"></div>
    <div class="form-row"><label>中签购买截止时间 *</label><input class="input" id="raf-bd" type="datetime-local"></div>
    <div class="form-row"><span class="cell-sub">中签名额 = 发售数量 ÷ 每人限购（向下取整）；报名人数不足名额时全部中签。</span></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="raf-form-ok" data-arg="${r?.id || 0}">保存</button></div>`);
  if (r) {
    $('#raf-as').value = toLocalInput(r.apply_start);
    $('#raf-ae').value = toLocalInput(r.apply_end);
    $('#raf-bd').value = toLocalInput(r.buy_deadline);
  }
}
ACT['raf-form-ok'] = el => {
  const id = +el.dataset.arg;
  const cid = +$('#raf-col').value, total = +$('#raf-total').value || 0;
  const as = $('#raf-as').value, ae = $('#raf-ae').value, bd = $('#raf-bd').value;
  if (!cid) return toast('请选择发售藏品', 'err');
  if (total <= 0) return toast('请填写发售数量', 'err');
  if (!as || !ae || !bd) return toast('请填写完整的报名与购买时间', 'err');
  if (new Date(as) >= new Date(ae)) return toast('报名开始必须早于报名截止', 'err');
  if (new Date(ae) >= new Date(bd)) return toast('报名截止必须早于购买截止', 'err');
  const now = NOW, aS = new Date(as).getTime() / 1000 | 0, aE = new Date(ae).getTime() / 1000 | 0;
  const data = { collection_id: cid, total, per_limit: +$('#raf-limit').value || 1, apply_start: aS, apply_end: aE, buy_deadline: new Date(bd).getTime() / 1000 | 0, status: now < aS ? 0 : (now <= aE ? 1 : 2) };
  if (id) {
    const old = S.db.raffles.find(x => x.id === id);
    if (old.status >= 3) return toast('已抽签的活动不可修改', 'err');
    Object.assign(old, data);
  } else {
    data.id = Date.now() % 10000; data.apply_count = 0; data.win_count = 0; data.createtime = NOW;
    S.db.raffles.unshift(data);
  }
  closeModal(); toast('保存成功', 'ok'); renderPage(); logOp('抽签发售', id ? '编辑' : '添加', COL_NAME(cid), total.toLocaleString() + ' 份');
};

const matRowHtml = (m = { collection_id: 1, num: 1 }) => `<div class="mat-row" style="display:flex;gap:8px;margin-bottom:8px">
  <select class="select mat-col" style="flex:2">${S.db.collections.map(c => `<option value="${c.id}" ${m.collection_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('')}</select>
  <input class="input mat-num" type="number" style="flex:1" value="${m.num}" placeholder="份数">
  <button class="btn btn-danger btn-sm" data-act="mat-row-del">删除</button></div>`;
function openSynForm(s) {
  const colOpts = S.db.collections.map(c => `<option value="${c.id}" ${s?.target_collection_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
  openModal(`<div class="modal-head"><h3>${s ? '编辑合成活动' : '新建合成活动'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <input type="hidden" id="syn-id" value="${s?.id || 0}">
    <div class="form-grid">
    <div class="form-row"><label>活动名称 *</label><input class="input" id="syn-name" value="${esc(s?.name || '')}"></div>
    <div class="form-row"><label>目标藏品 *</label><select class="select" id="syn-target">${colOpts}</select></div>
    <div class="form-row"><label>每人限合成次数（0 不限）</label><input class="input" id="syn-limit" type="number" value="${s?.limit_num ?? 1}"></div>
    <div class="form-row"><label>状态</label><select class="select" id="syn-status"><option value="1">开启</option><option value="0">关闭</option></select></div></div>
    <div class="form-row"><label>材料配方（材料 + 份数 → 目标藏品）</label>
      <div id="mat-rows">${(s?.materials || [{ collection_id: 1, num: 2 }]).map(m => matRowHtml(m)).join('')}</div>
      <button class="btn btn-ghost btn-sm" data-act="mat-row-add">＋ 添加材料</button></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="syn-save">保存</button></div>`);
  if (s) $('#syn-status').value = s.status;
}

const prizeRowHtml = (p = { name: '', type: 1, collection_id: 0, num: 1, probability: 0, stock: 0 }) => {
  const colOpts = S.db.collections.map(c => `<option value="${c.id}" ${p.collection_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
  return `<div class="prize-row" style="display:grid;grid-template-columns:1.3fr .8fr 1fr .7fr .7fr auto;gap:6px;margin-bottom:8px;align-items:center" data-type="${p.type || 1}">
  <input class="input pz-name" placeholder="奖品名" value="${esc(p.name)}">
  <select class="select pz-type"><option value="1" ${p.type === 1 ? 'selected' : ''}>藏品</option><option value="2" ${p.type === 2 ? 'selected' : ''}>抽奖次数</option><option value="3" ${p.type === 3 ? 'selected' : ''}>谢谢参与</option></select>
  ${p.type === 2
    ? `<input class="input pz-num" type="number" min="1" placeholder="抽奖次数" value="${p.num || 1}">`
    : `<select class="select pz-col" ${p.type === 3 ? 'disabled' : ''}>${p.type === 3 ? '<option value="0">-</option>' : colOpts}</select>`}
  <input class="input pz-prob" type="number" placeholder="概率‱" value="${p.probability || ''}">
  <input class="input pz-stock" type="number" placeholder="库存" value="${p.stock || ''}">
  <button class="btn btn-danger btn-sm" data-act="lot-prize-row-del"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></button></div>`;
};
function refreshProbability() {
  const total = [...document.querySelectorAll('.pz-prob')].reduce((s, i) => s + (+i.value || 0), 0);
  const el = $('#pz-total'); if (el) el.textContent = (total / 100).toFixed(1) + '%';
}
function openPrizeModal(a) {
  openModal(`<div class="modal-head"><h3>奖池配置 · ${a.title}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <input type="hidden" id="pz-act-id" value="${a.id}">
    <div style="display:grid;grid-template-columns:1.3fr .8fr 1fr .7fr .7fr auto;gap:6px;font-size:11px;color:var(--ink-muted);margin-bottom:6px">
      <span>奖品名称</span><span>类型</span><span>藏品 / 抽奖次数</span><span>概率(万分比)</span><span>库存</span><span></span></div>
    <div id="prize-rows">${a.prizes.map(p => prizeRowHtml(p)).join('')}</div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
      <button class="btn btn-ghost btn-sm" data-act="lot-prize-row-add">＋ 添加奖品</button>
      <span class="cell-sub">概率合计：<b id="pz-total" class="serif-num">${(a.prizes.reduce((s, p) => s + p.probability, 0) / 100).toFixed(1)}%</b>（万分比，≤100%）</span></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="lot-save-prizes">保存奖池</button></div>`);
  document.querySelectorAll('.pz-prob').forEach(i => i.addEventListener('input', refreshProbability));
  // 奖品类型切换：整行重渲染（藏品↔抽奖次数↔谢谢参与）
  $('#prize-rows').addEventListener('change', e => {
    if (!e.target.classList.contains('pz-type')) return;
    const row = e.target.closest('.prize-row');
    const cur = {
      name: row.querySelector('.pz-name').value,
      type: +e.target.value,
      collection_id: +(row.querySelector('.pz-col')?.value || 0),
      num: +(row.querySelector('.pz-num')?.value || 1),
      probability: +(row.querySelector('.pz-prob')?.value || 0),
      stock: +(row.querySelector('.pz-stock')?.value || 0),
    };
    row.outerHTML = prizeRowHtml(cur);
    document.querySelectorAll('.pz-prob').forEach(i => i.removeEventListener('input', refreshProbability));
    document.querySelectorAll('.pz-prob').forEach(i => i.addEventListener('input', refreshProbability));
  });
}

function openInvForm(r) {
  const isEdit = !!r;
  const ST_NAME = CFG_ST_NAME;
  const colOpts = S.db.collections.map(c => `<option value="${c.id}" ${r?.collection_id === c.id ? 'selected' : ''}>${c.name}</option>`).join('');
  const preOpts = S.db.collections.map(c => `<option value="${c.id}" ${+r?.pre_collection_id === c.id ? 'selected' : ''}>${c.name}（${ST_NAME[c.status]}${c.status !== 2 ? '，可预配置' : ''}）</option>`).join('');
  openModal(`<div class="modal-head"><h3>${isEdit ? '编辑奖励规则' : '添加奖励规则'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <input type="hidden" id="inv-id" value="${r?.id || 0}">
    <div class="form-grid">
    <div class="form-row"><label>邀请人数门槛 *</label><input class="input" id="inv-num" type="number" value="${r?.invite_num || ''}"></div>
    <div class="form-row"><label>奖励类型</label><select class="select" id="inv-type"><option value="1">藏品</option><option value="2">抽奖次数</option><option value="3">优先购资格</option></select></div></div>
    <div class="form-row" id="inv-col-row"><label>奖励藏品</label><select class="select" id="inv-col">${colOpts}</select></div>
    <div class="form-row" id="inv-pre-row" style="display:none"><label>优先购目标藏品（未发售藏品可预配置）</label><select class="select" id="inv-pre-col"><option value="0">请选择目标藏品</option>${preOpts}</select></div>
    <div class="form-row" id="inv-num-row" style="display:none"><label id="inv-num-label">奖励抽奖次数</label><input class="input" id="inv-num2" type="number" min="1" value="${r && r.reward_type !== 1 ? r.reward_num : 1}"></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="inv-save">保存</button></div>`);
  const sync = () => {
    const t = +$('#inv-type').value;
    $('#inv-col-row').style.display = t === 1 ? '' : 'none';
    $('#inv-pre-row').style.display = t === 3 ? '' : 'none';
    $('#inv-num-row').style.display = t === 1 ? 'none' : '';
    $('#inv-num-label').textContent = t === 3 ? '优先购可用次数' : '奖励抽奖次数';
  };
  $('#inv-type').onchange = sync;
  if (r) $('#inv-type').value = r.reward_type;
  sync();
}

function openNotForm(n) {
  const isEdit = !!n;
  openModal(`<div class="modal-head"><h3>${isEdit ? '编辑公告' : '发布公告'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body" style="max-height:60vh">
    <input type="hidden" id="not-id" value="${n?.id || 0}">
    <div class="form-row"><label>标题 *</label><input class="input" id="not-title" value="${esc(n?.title || '')}"></div>
    <div class="form-grid">
      <div class="form-row"><label>分类</label><select class="select" id="not-cat"><option value="1">公告</option><option value="2">活动</option><option value="3">系统</option></select></div>
      <div class="form-row"><label>置顶</label><select class="select" id="not-top"><option value="0">否</option><option value="1">是</option></select></div></div>
    <div class="form-row"><label>正文（富文本）</label>
      ${editorHtml('notice', n?.content || '<p>请输入公告正文…</p>')}</div>
    <div class="form-row"><label>发布时间</label><input class="input" type="datetime-local" value="2026-10-28T10:00"></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="not-form-ok">保存</button></div>`);
  if (n) { $('#not-cat').value = n.category; $('#not-top').value = n.is_top; }
  bindEditor('notice');
}
ACT['not-form-ok'] = () => {
  const id = +$('#not-id').value; const title = $('#not-title').value.trim();
  if (!title) return toast('请输入公告标题', 'err');
  const data = { title, category: +$('#not-cat').value, is_top: +$('#not-top').value, status: 1, content: editorVal('notice'), publish_time: NOW };
  if (id) Object.assign(S.db.notices.find(x => x.id === id), data);
  else S.db.notices.unshift({ id: Date.now() % 10000, ...data });
  closeModal(); toast('保存成功，C 端已同步', 'ok'); renderPage(); logOp('公告管理', id ? '编辑' : '发布公告', title, '');
};

function openBanForm(b) {
  const isEdit = !!b;
  openModal(`<div class="modal-head"><h3>${isEdit ? '编辑轮播' : '添加轮播'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <input type="hidden" id="ban-id" value="${b?.id || 0}">
    <div class="form-row"><label>图片（上传组件演示）</label><div class="upload-box" data-act="set-logo"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="1.6"/><path d="m5 19 5.5-5.5L14 17l3-3 4 4"/></svg><span>上传图片</span></div></div>
    <div class="form-grid">
    <div class="form-row"><label>标题</label><input class="input" id="ban-title" value="${esc(b?.title || '')}"></div>
    <div class="form-row"><label>跳转链接</label><input class="input" id="ban-link" value="${esc(b?.link || '')}"></div>
    <div class="form-row"><label>排序（越大越前）</label><input class="input" id="ban-weigh" type="number" value="${b?.weigh ?? 50}"></div>
    <div class="form-row"><label>状态</label><select class="select" id="ban-status"><option value="1">启用</option><option value="0">停用</option></select></div></div>
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="ban-form-ok">保存</button></div>`);
  if (b) $('#ban-status').value = b.status;
}
ACT['ban-form-ok'] = () => {
  const id = +$('#ban-id').value; const title = $('#ban-title').value.trim();
  if (!title) return toast('请输入标题', 'err');
  const data = { title, link: $('#ban-link').value, weigh: +$('#ban-weigh').value || 0, status: +$('#ban-status').value };
  if (id) Object.assign(S.db.banners.find(x => x.id === id), data);
  else S.db.banners.push({ id: Date.now() % 10000, ...data });
  closeModal(); toast('保存成功', 'ok'); renderPage(); logOp('轮播管理', id ? '编辑' : '添加', title, '');
};

function openCkRuleForm(rule, idx) {
  const isEdit = rule !== null;
  openModal(`<div class="modal-head"><h3>${isEdit ? '编辑阶梯' : '添加阶梯'}</h3><span class="close" data-act="modal-close"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg></span></div>
  <div class="modal-body">
    <input type="hidden" id="ckr-idx" value="${idx ?? ''}">
    <div class="form-row"><label>连续签到天数 *</label><input class="input" id="ckr-day" type="number" value="${rule?.day_num || ''}" placeholder="如 7"></div>
    ${rewardCfgHtml('ckr', rule || { type: 1, num: 1, collection_id: 0 })}
  </div>
  <div class="modal-foot"><button class="btn btn-ghost" data-act="modal-close">取消</button><button class="btn btn-primary" data-act="ck-rule-save">保存</button></div>`);
}

/* ---------- 事件绑定 ---------- */
document.addEventListener('click', e => {
  const el = e.target.closest('[data-act]');
  if (!el) return;
  const act = el.dataset.act;
  if (act === 'modal-mask-click' && e && e.target !== el) return;
  if (el.tagName === 'BUTTON' && el.disabled) return;
  ACT[act] && ACT[act](el, e);
});
$('#clean-confirm') && 0;
document.addEventListener('input', e => {
  if (e.target.id === 'clean-confirm') { const b = $('#clean-btn'); if (b) b.disabled = e.target.value !== '清空'; }
  if (e.target.id === 'u-kw' && e.key === 'Enter') ACT['user-search']();
});
document.addEventListener('change', e => {
  const id = e.target?.id || '';
  if (id.endsWith('-type') && $(`#${id.slice(0, -5)}-col-row`)) syncRewardCfg(id.slice(0, -5));
});
document.addEventListener('keyup', e => {
  if (e.key !== 'Enter') return;
  const map = { 'u-kw': 'user-search', 'w-kw': 'wallet-search', 'c-kw': 'col-search', 'o-kw': 'order-search', 'h-kw': 'hold-search', 'n-kw': 'not-search', 'ol-kw': 'ol-search' };
  if (map[e.target.id]) ACT[map[e.target.id]]();
});
ACT['ol-search'] = () => { S.q.operlog.keyword = $('#ol-kw').value; S.q.operlog.page = 1; renderPage(); };
ACT['ol-reset'] = () => { S.q.operlog = { keyword: '', page: 1 }; renderPage(); };
ACT['ol-page'] = el => { S.q.operlog.page = +el.dataset.arg; renderPage(); };
ACT['not-reset'] = () => { S.q.notice = { keyword: '', status: -1 }; renderPage(); };

/* 启动 */
document.title = S.db.settings.brand.site_name + ' · 管理后台';
renderLogin();
