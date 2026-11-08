<template>
    <div class="topbar">
        <div class="tb-left">
            <button id="hamburger" aria-label="菜单" @click="shell.mobileOpen = true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M4 7h16M4 12h16M4 17h16" />
                </svg>
            </button>
            <h1>{{ title }}</h1>
            <span class="en">{{ en }}</span>
        </div>
        <span class="writetag">写操作已开启</span>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { prototypeShell as shell } from './prototypeShell'

defineOptions({ name: 'layout/prototypeHeader' })

const route = useRoute()

const EN_MAP: Record<string, string> = {
    dashboard: 'Dashboard',
    'nft/dashboard': 'Dashboard',
    'nft/user': 'Users',
    'nft/wallet': 'Wallet Logs',
    'nft/collection': 'Collections',
    'nft/blindbox': 'Blindbox',
    'nft/whitelist': 'Whitelist',
    'nft/category': 'Categories',
    'nft/order': 'Orders',
    'nft/market': 'Market',
    'nft/transfer': 'Transfer',
    'nft/hold': 'Holdings',
    'nft/raffle': 'Raffle Sale',
    'nft/airdrop': 'Airdrop',
    'nft/swap': 'Swap',
    'nft/roster': 'Roster',
    'nft/activity': 'Activity Center',
    'nft/lottery': 'Lottery',
    'nft/synthesis': 'Synthesis',
    'nft/invite': 'Invite',
    'nft/checkin': 'Check-in',
    'nft/register': 'Register Gift',
    'nft/notice': 'Notices',
    'nft/banner': 'Banners',
    'nft/agreement': 'Agreements',
    'nft/setting': 'Settings',
    'nft/thirdparty': 'Third-party',
    'nft/cleanup': 'Cleanup',
    'nft/operlog': 'Audit Log',
    'auth/group': 'Roles',
    'auth/admin': 'Admins',
    'auth/rule': 'Menus',
    'auth/adminLog': 'Admin Log',
    'user/user': 'Members',
    'user/group': 'Member Groups',
    'user/rule': 'Member Rules',
    'user/moneyLog': 'Member Balance',
    'user/scoreLog': 'Member Score',
    'routine/config': 'Config',
    'routine/attachment': 'Attachments',
    'routine/adminInfo': 'Profile',
    'security/dataRecycleLog': 'Recycle Log',
    'security/dataRecycle': 'Recycle Rules',
    'security/sensitiveData': 'Sensitive Fields',
    'security/sensitiveDataLog': 'Sensitive Log',
    'moduleStore/moduleStore': 'Modules',
    'crud/crud': 'CRUD Builder',
}

const title = computed(() => {
    const name = String(route.name || '')
    if (TITLE_OVERRIDE[name]) return TITLE_OVERRIDE[name]
    return String(route.meta?.title || '')
})
const en = computed(() => EN_MAP[String(route.name || '')] || '')

/** 与原型 topbar 一致的标题（顶栏已展示页题，页内 page-head 隐藏） */
const TITLE_OVERRIDE: Record<string, string> = {
    dashboard: '数据仪表盘',
}
</script>
