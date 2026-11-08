/* 原型壳层共享状态（侧边栏折叠 / 手机抽屉） */
import { reactive } from 'vue'

const stored = localStorage.getItem('nft-sidebar-collapsed')
export const prototypeShell = reactive({
    collapsed: stored === '1',
    mobileOpen: false,
})

export function toggleCollapse() {
    prototypeShell.collapsed = !prototypeShell.collapsed
    localStorage.setItem('nft-sidebar-collapsed', prototypeShell.collapsed ? '1' : '0')
}

export function closeMobile() {
    prototypeShell.mobileOpen = false
}
