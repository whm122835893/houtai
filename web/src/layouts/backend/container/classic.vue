<template>
    <!-- 原型壳层 1:1 移植：.layout / #sidebar / .topbar 结构与 preview 完全一致 -->
    <div class="layout layout-container">
        <PrototypeAside />
        <div id="shade" v-show="shell.mobileOpen" @click="closeMobile()"></div>
        <main>
            <PrototypeHeader />
            <Main />
        </main>
        <CloseFullScreen v-if="navTabs.state.tabFullScreen" />
    </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import PrototypeAside from '/@/layouts/backend/components/prototype-aside.vue'
import PrototypeHeader from '/@/layouts/backend/components/prototype-header.vue'
import CloseFullScreen from '/@/layouts/backend/components/closeFullScreen.vue'
import Main from '/@/layouts/backend/router-view/main.vue'
import { useNavTabs } from '/@/stores/navTabs'
import { prototypeShell as shell, closeMobile } from '/@/layouts/backend/components/prototypeShell'
import { initNftTheme } from '/@/utils/nft-theme'

defineOptions({
    name: 'layout/container/classic',
})

const navTabs = useNavTabs()

onMounted(() => {
    // C 端主题色引擎：缓存秒生效 + 服务端值异步校正（后台全站颜色跟随 C 端主色）
    initNftTheme()
})
</script>
