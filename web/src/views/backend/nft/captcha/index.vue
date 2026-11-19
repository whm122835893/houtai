<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>验证码设置</h2>
            <span class="en">CAPTCHA</span>
        </div>

        <!-- 说明 -->
        <div class="glass rise" :style="{ '--i': 1 }" style="margin-bottom: 14px">
            <div class="cap-tip">
                每个需要验证码的场景都配有独立开关，可按需单独开启/关闭，保存后即时生效。验证形式两种可互换：点选式（点击文字验证，复用 BuildAdmin 自带点选验证码，后台登录页与前端组件均现成）与字符图形（4 位数字图，C 端通过接口获取 base64 图后随表单提交）。所有开关关闭时对应场景不校验验证码。
            </div>
        </div>

        <!-- 验证形式 -->
        <div class="glass rise" :style="{ '--i': 2 }" style="margin-bottom: 14px">
            <div class="card-head"><h3>验证形式</h3><span class="en">MODE</span></div>
            <div class="cap-cfg">
                <div class="cap-mode-row">
                    <button class="cap-mode" :class="{ active: mode === 'click' }" @click="mode = 'click'">
                        <b>点选式验证码</b>
                        <span>展示背景图，按提示点选对应文字；后台登录页与 C 端组件均已支持</span>
                    </button>
                    <button class="cap-mode" :class="{ active: mode === 'graphic' }" @click="mode = 'graphic'">
                        <b>字符图形验证码</b>
                        <span>4 位数字图片，C 端接口返回 base64 图，表单填码提交</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 分场景开关 -->
        <div class="glass rise" :style="{ '--i': 3 }" style="margin-bottom: 14px">
            <div class="card-head"><h3>分场景开关</h3><span class="en">SCENES</span></div>
            <div class="cap-list">
                <div v-for="item in switches" :key="item.name" class="cap-item">
                    <div class="cap-item-info">
                        <div class="cell-main">{{ item.title }}</div>
                        <div class="cell-sub">{{ sceneDesc[item.name] || '' }}</div>
                    </div>
                    <label class="cap-switch">
                        <input v-model="item.value" type="checkbox" />
                        <span class="cap-slider"></span>
                    </label>
                </div>
            </div>
            <div class="group-foot" style="padding: 12px 0 0">
                <span class="cell-sub">保存后即时生效（仅超级管理员可修改）</span>
                <button v-if="auth('cfg')" class="btn btn-primary btn-sm" :disabled="saving" @click="onSave">{{ saving ? '保存中…' : '保存配置' }}</button>
            </div>
        </div>

        <!-- C 端接入说明 -->
        <div class="glass rise" :style="{ '--i': 4 }">
            <div class="card-head"><h3>C 端接入说明</h3><span class="en">API</span></div>
            <div class="cap-api">
                <table class="tbl">
                    <thead><tr><th>接口</th><th>说明</th></tr></thead>
                    <tbody>
                        <tr><td class="serif-num">GET /api/nftcaptcha/enabled?scene=login|register</td><td>探测场景开关，返回 {enabled, mode}，C 端据此决定是否展示验证码</td></tr>
                        <tr><td class="serif-num">GET /api/nftcaptcha/image</td><td>字符图形模式：返回 {key, image(base64)}，提交时携带 captchaKey + captcha</td></tr>
                        <tr><td class="serif-num">GET /api/common/clickCaptcha?id=xxx</td><td>点选式模式：取点选图（BuildAdmin 公开接口）</td></tr>
                        <tr><td class="serif-num">POST /api/common/checkClickCaptcha</td><td>点选式模式：预校验，提交时携带 captchaId + captchaInfo</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { auth } from '/@/utils/common'
import * as api from '/@/api/backend/nft/captcha'

defineOptions({ name: 'nft/captcha' })

const mode = ref('click')
const saving = ref(false)
const switches = ref<{ name: string; title: string; value: boolean }[]>([])
const sceneDesc: Record<string, string> = {
    captcha_admin_login: '管理员登录后台时先通过验证码，防止后台被暴力破解',
    captcha_user_login: 'C 端会员调用登录接口时先通过验证码，防止撞库刷接口',
    captcha_user_register: 'C 端会员注册接口需携带验证码，防止机器批量注册',
    captcha_ems_send: '发送短信/邮件验证码前需先通过验证码，防止短信轰炸',
}

const load = async () => {
    const res = await api.getConfig()
    mode.value = res.data.mode || 'click'
    switches.value = (res.data.switches || []).map((s: any) => ({ ...s, value: !!s.value }))
}

const onSave = async () => {
    saving.value = true
    try {
        const data: Record<string, string> = { captcha_mode: mode.value }
        for (const s of switches.value) data[s.name] = s.value ? '1' : '0'
        await api.saveCfg({ data })
        ElMessage.success('验证码配置已保存')
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.cap-tip {
    font-size: 13px;
    line-height: 1.8;
    color: var(--nft-text-2, #64748b);
}
.cap-mode-row {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.cap-mode {
    flex: 1;
    min-width: 240px;
    text-align: left;
    border: 1px solid var(--nft-border, #e2e8f0);
    border-radius: 8px;
    background: transparent;
    padding: 14px 16px;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: all 0.2s;
}
.cap-mode b {
    font-size: 14px;
    color: var(--nft-text, #1e293b);
}
.cap-mode span {
    font-size: 12px;
    color: var(--nft-text-2, #64748b);
    line-height: 1.6;
}
.cap-mode.active {
    border-color: var(--el-color-primary, #4a8ff7);
    background: rgba(74, 143, 247, 0.06);
    box-shadow: 0 0 0 1px var(--el-color-primary, #4a8ff7) inset;
}
.cap-list {
    display: flex;
    flex-direction: column;
}
.cap-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 13px 0;
    border-bottom: 1px dashed var(--nft-border, #e2e8f0);
}
.cap-item:last-child {
    border-bottom: none;
}
.cap-item-info .cell-main {
    font-size: 14px;
}
.cap-item-info .cell-sub {
    margin-top: 3px;
}
.cap-switch {
    position: relative;
    display: inline-block;
    width: 44px;
    height: 24px;
    flex-shrink: 0;
}
.cap-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.cap-slider {
    position: absolute;
    inset: 0;
    border-radius: 24px;
    background: #cbd5e1;
    transition: 0.25s;
    cursor: pointer;
}
.cap-slider::before {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    left: 3px;
    top: 3px;
    border-radius: 50%;
    background: #fff;
    transition: 0.25s;
}
.cap-switch input:checked + .cap-slider {
    background: var(--el-color-primary, #4a8ff7);
}
.cap-switch input:checked + .cap-slider::before {
    transform: translateX(20px);
}
.cap-api {
    overflow-x: auto;
}
.cap-api .tbl td {
    font-size: 12.5px;
}
</style>
