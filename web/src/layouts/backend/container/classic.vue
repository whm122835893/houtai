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
import PrototypeAside from '/@/layouts/backend/components/prototype-aside.vue'
import PrototypeHeader from '/@/layouts/backend/components/prototype-header.vue'
import CloseFullScreen from '/@/layouts/backend/components/closeFullScreen.vue'
import Main from '/@/layouts/backend/router-view/main.vue'
import { useNavTabs } from '/@/stores/navTabs'
import { prototypeShell as shell, closeMobile } from '/@/layouts/backend/components/prototypeShell'

defineOptions({
    name: 'layout/container/classic',
})

const navTabs = useNavTabs()
</script>
