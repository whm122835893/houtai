<template>
    <aside id="sidebar" class="glass" :class="{ collapsed: shell.collapsed, 'mobile-open': shell.mobileOpen }">
        <button class="collapse-btn" title="折叠/展开" @click="toggleCollapse()">‹</button>
        <div class="brand">
            <div class="logo"><img v-if="brandLogo" :src="brandLogo" alt="logo" /><template v-else>灵</template></div>
            <div class="brand-text"><b>灵境数藏</b><span>ADMIN CONSOLE</span></div>
        </div>
        <template v-for="group in groups" :key="group.label">
            <div class="group-label">{{ group.label }}</div>
            <button
                v-for="item in group.items"
                :key="item.name"
                class="nav-item"
                :class="{ active: item.name === route.name }"
                @click="onNav(item.name)"
            >
                <svg viewBox="0 0 24 24" v-html="item.icon"></svg>
                <span class="nav-text">{{ item.title }}</span>
            </button>
        </template>
        <div class="user-chip">
            <div class="avatar">{{ (adminInfo.nickname || '管').slice(0, 1) }}</div>
            <div class="user-info">
                <b>{{ adminInfo.nickname || '管理员' }}</b>
                <span>{{ adminInfo.super ? '超级管理员' : '管理员' }}</span>
            </div>
            <div class="chip-btns">
                <button class="btn btn-ghost btn-sm" title="修改密码" @click="routePush({ name: 'routine/adminInfo' })">改密</button>
                <button class="btn btn-danger btn-sm" title="退出登录" @click="onLogout">退出</button>
            </div>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter, useRoute, type RouteRecordRaw } from 'vue-router'
import { Local } from '/@/utils/storage'
import { ADMIN_INFO } from '/@/stores/constant/cacheKey'
import { useAdminInfo } from '/@/stores/adminInfo'
import { useNavTabs } from '/@/stores/navTabs'
import { logout } from '/@/api/backend/index'
import { prototypeShell as shell, toggleCollapse, closeMobile } from './prototypeShell'

defineOptions({ name: 'layout/prototypeAside' })

const router = useRouter()
const route = useRoute()
const navTabs = useNavTabs()
const adminInfo = useAdminInfo()

const brandLogo = ''

/* 原型 NAV 图标（svg path 片段，来自 preview/app.js 的 NAV 定义） */
const ICONS: Record<string, string> = {
    dashboard: '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    user: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
    wallet: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
    collection: '<path d="M6 3h12l4 6-10 12L2 9z"/>',
    blindbox: '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5" rx="1"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
    whitelist: '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m8 12 3 3 5-6"/>',
    category: '<path d="M20 12l-8 8-9-9 9-8h8z"/>',
    order: '<path d="M14 2H6v20h12V6z"/><path d="M14 2v4h4"/>',
    market: '<path d="M4 7h13m0 0-3-3m3 3-3 3M20 17H7m0 0 3 3m-3-3 3-3"/>',
    transfer: '<path d="M17 3l4 4-4 4M21 7H9M7 21l-4-4 4-4M3 17h12"/>',
    hold: '<path d="M12 2 2 7l10 5 10-5zM2 12l10 5 10-5M2 17l10 5 10-5"/>',
    raffle: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 3.5"/><path d="M12 3a9 9 0 0 1 9 9"/>',
    airdrop: '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
    swap: '<path d="M17 2l4 4-4 4"/><path d="M3 11v-1a4 4 0 0 1 4-4h14"/><path d="M7 22l-4-4 4-4"/><path d="M21 13v1a4 4 0 0 1-4 4H3"/>',
    roster: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 10h8M8 14h5"/>',
    activity: '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
    lottery: '<rect x="3" y="8" width="18" height="13" rx="2"/><path d="M12 8v13M3 8c0-2 2-3 4-3 3 0 5 3 5 3s2-3 5-3c2 0 4 1 4 3"/>',
    synthesis: '<path d="M9 3h6v6l4 9a2 2 0 0 1-2 3H7a2 2 0 0 1-2-3l4-9z"/>',
    invite: '<path d="M3 9l9-6 9 6-9 6zM6 12v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
    checkin: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 15.5l2 2 4-4"/>',
    register: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
    notice: '<path d="M3 11l18-8-7 18-2.5-7z"/>',
    banner: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
    agreement: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m8.5 15.5 2 2 5-5"/>',
    setting: '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.06-.4.1-.8.1-1.2z"/>',
    thirdparty: '<path d="M5 12a7 7 0 0 1 14 0M2.5 8.5v7h4v-7zM17.5 8.5v7h4v-7z"/><path d="M19 15.5a4 4 0 0 1-4 4h-2"/>',
    cleanup: '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>',
    operlog: '<path d="M12 2 4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5z"/>',
    trash: '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
    /* 框架自带菜单的图标映射 */
    group: '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.5 3-5.5 6.5-5.5s6.5 2 6.5 5.5"/><circle cx="17" cy="9" r="3"/><path d="M15.5 14.7c3 .3 6 2 6 5.3"/>',
    rule: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h10M7 17h6"/>',
    adminLog: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
    moneyLog: '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',
    scoreLog: '<path d="M12 2l3 6.5 7 .8-5 4.8 1.3 6.9L12 17.5 5.7 21l1.3-6.9-5-4.8 7-.8z"/>',
    config: '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.06-.4.1-.8.1-1.2z"/>',
    attachment: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/>',
    adminInfo: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/>',
    dataRecycle: '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
    dataRecycleLog: '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
    sensitiveData: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
    sensitiveDataLog: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
    security: '<path d="M12 2 4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5z"/>',
    auth: '<path d="M12 2 4 5v6c0 5 3.5 9.5 8 11 4.5-1.5 8-6 8-11V5z"/>',
    routine: '<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.06-.4.1-.8.1-1.2z"/>',
    moduleStore: '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v9"/>',
    crud: '<path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/>',
}
const FALLBACK_ICON = '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12h8"/>'

interface NavItem {
    name: string
    title: string
    icon: string
}
interface NavGroup {
    label: string
    items: NavItem[]
}

const titleOverride: Record<string, string> = {
    dashboard: '数据仪表盘',
}

const pickIcon = (name: string) => {
    const key = (name || '').split('/').pop() || ''
    return ICONS[key] || ICONS[name] || FALLBACK_ICON
}

const toItems = (children: RouteRecordRaw[]): NavItem[] =>
    (children || [])
        .filter((c) => c.name !== 'nft/dashboard')
        .map((c) => ({
            name: String(c.name),
            title: titleOverride[String(c.name)] || String(c.meta?.title || c.name),
            icon: pickIcon(String(c.name)),
        }))

const groups = computed<NavGroup[]>(() => {
    const list: NavGroup[] = []
    const routes = navTabs.state.tabsViewRoutes || []
    for (const r of routes) {
        const name = String(r.name || '')
        if (name === 'dashboard') {
            /* 框架顶层「控制台」并入原型「概览」分组，置顶 */
            list.push({
                label: '概览',
                items: [{ name, title: '数据仪表盘', icon: ICONS.dashboard }],
            })
            continue
        }
        if (r.children && r.children.length) {
            list.push({ label: String(r.meta?.title || name), items: toItems(r.children) })
        } else {
            /* 无子级的顶层菜单（模块市场/CRUD 等）直接成项 */
            list.push({
                label: String(r.meta?.title || name),
                items: [{ name, title: String(r.meta?.title || name), icon: pickIcon(name) }],
            })
        }
    }
    /* 过滤空分组（如概览组子项并入顶层后） */
    const valid = list.filter((g) => g.items.length > 0)
    /* 业务分组前置（对齐原型菜单顺序），其余框架组按原顺序跟在后面 */
    const priority = ['概览', '业务管理', '活动运营', '内容运营', '系统']
    const head = valid.filter((g) => priority.includes(g.label))
    const tail = valid.filter((g) => !priority.includes(g.label))
    return [...head, ...tail]
})

const onNav = (name: string) => {
    router.push({ name })
    closeMobile()
}

const routePush = (opt: { name: string }) => {
    router.push(opt)
    closeMobile()
}

const onLogout = () => {
    logout().then(() => {
        Local.remove(ADMIN_INFO)
        router.go(0)
    })
}
</script>
