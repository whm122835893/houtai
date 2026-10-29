<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>数据清理</h2>
            <span class="en">CLEANUP</span>
        </div>

        <!-- 红色警示卡 -->
        <div class="glass danger-card rise" :style="{ '--i': 1 }">
            <svg class="danger-icon" viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.5 20h19z"/><path d="M12 10v4.5"/><circle cx="12" cy="17.2" r="0.6" fill="currentColor"/></svg>
            <div>
                <div class="danger-title">高危操作 · 硬删除不可恢复</div>
                <div class="danger-sub">清理将直接 TRUNCATE 数据表（重置自增 ID），数据无法找回，请务必先备份。仅超级管理员可操作。</div>
            </div>
        </div>

        <!-- 分组勾选卡 -->
        <div class="group-grid">
            <div v-for="(group, gi) in groups" :key="group.key" class="glass rise" :style="{ '--i': gi + 2 }">
                <div class="card-head">
                    <h3>
                        <input type="checkbox" :checked="selected === group.key" @change="selectGroup(group.key)" />
                        {{ group.title }}
                    </h3>
                    <span class="en">{{ group.key }}</span>
                </div>
                <div class="tbl-wrap" style="padding: 10px 20px">
                    <table class="tbl">
                        <thead>
                            <tr>
                                <th>数据表</th>
                                <th>行数</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in group.tables" :key="t.name">
                                <td class="cell-sub">{{ t.name }}</td>
                                <td class="serif-num">{{ t.count }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 底部确认区 -->
        <div class="glass rise" style="margin-top: 16px; padding: 16px 20px 20px">
            <div class="card-head" style="padding: 0 0 4px">
                <h3>执行清理</h3>
                <span class="en">CONFIRM</span>
            </div>
            <div class="confirm-row">
                <span class="cell-sub">已选分组：<b>{{ selectedTitle || '（未选择）' }}</b></span>
                <input v-model="confirmText" class="input confirm-input" placeholder='请输入「清空」以启用按钮' />
                <button v-if="auth('nft/cleanup/clean')" class="btn btn-danger" :disabled="!canClean || cleaning" @click="doClean(false)">
                    {{ cleaning ? '清理中…' : '执行清理' }}
                </button>
                <span v-else class="cell-sub">仅超级管理员可执行</span>
            </div>
        </div>

        <!-- 清理结果回显 -->
        <div v-if="lastResult.length" class="glass rise" style="margin-top: 16px; padding: 16px 20px 20px">
            <div class="card-head" style="padding: 0 0 8px">
                <h3>最近一次清理结果</h3>
                <span class="en">RESULT</span>
            </div>
            <div class="result-row">
                <span v-for="r in lastResult" :key="r.name" class="tag tag-green">{{ r.name }}：删除 {{ r.count }} 行</span>
            </div>
        </div>

        <!-- 全体用户清除 -->
        <div class="glass rise" style="margin-top: 16px; padding: 16px 20px 20px; border: 1px solid rgba(255, 77, 79, 0.35)">
            <div class="card-head" style="padding: 0 0 4px">
                <h3 style="color: var(--danger)">全体用户清除</h3>
                <span class="en">DANGER ZONE</span>
            </div>
            <div class="user-row">
                <span>当前用户数 <b class="serif-num">{{ userCount }}</b> 人；清空将删除全部会员，其钱包流水 / 持仓 / 订单（属于交易资产组）需另行清理，此处仅清用户表。</span>
            </div>
            <div class="confirm-row">
                <input v-model="userConfirmText" class="input confirm-input" placeholder='请输入「清空」确认' />
                <button v-if="auth('nft/cleanup/clean')" class="btn btn-danger" :disabled="userConfirmText !== '清空' || cleaning" @click="doClean(true)">
                    {{ cleaning ? '清理中…' : '清空全体用户' }}
                </button>
                <span v-else class="cell-sub">仅超级管理员可执行</span>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import * as api from '/@/api/backend/nft/cleanup'

defineOptions({ name: 'nft/cleanup' })

const groups = ref<any[]>([])
const userCount = ref(0)
const selected = ref('')
const confirmText = ref('')
const userConfirmText = ref('')
const cleaning = ref(false)
const lastResult = ref<{ name: string; count: number }[]>([])

const selectedTitle = computed(() => groups.value.find((g) => g.key === selected.value)?.title || '')
const canClean = computed(() => !!selected.value && confirmText.value === '清空')

const load = async () => {
    const res = await api.index()
    groups.value = res.data.groups || []
    userCount.value = res.data.users || 0
}

const selectGroup = (key: string) => {
    selected.value = selected.value === key ? '' : key
}

const runClean = async (group: string) => {
    cleaning.value = true
    try {
        await ElMessageBox.confirm(
            group === 'user'
                ? '即将清空全体用户数据（ba_nft_user），硬删除不可恢复，确定继续？'
                : `即将清空「${selectedTitle.value}」分组全部数据表，硬删除不可恢复，确定继续？`,
            '二次确认 · 危险操作',
            { type: 'error', confirmButtonText: '确认清空', cancelButtonText: '取消' }
        )
    } catch {
        cleaning.value = false
        return
    }
    try {
        const res = await api.clean(group, '清空')
        const deleted = res.data.deleted || {}
        lastResult.value = Object.keys(deleted).map((name) => ({ name, count: deleted[name] }))
        ElMessage.success('清理完成')
        load()
    } finally {
        cleaning.value = false
    }
}

const doClean = (isUser: boolean) => {
    if (isUser) {
        runClean('user')
    } else if (canClean.value) {
        runClean(selected.value)
    }
}

onMounted(() => load())
</script>

<style scoped>
.danger-card {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    margin-bottom: 16px;
    border: 1px solid rgba(255, 77, 79, 0.35);
}
.danger-icon {
    font-size: 26px;
    line-height: 1;
}
.danger-title {
    font-weight: 700;
    color: var(--danger);
    margin-bottom: 4px;
}
.danger-sub {
    font-size: 12px;
    color: var(--ink-3);
}
.group-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 14px;
}
.group-grid .card-head h3 {
    display: flex;
    align-items: center;
    gap: 8px;
}
.confirm-row {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 0 2px;
}
.confirm-input {
    width: 220px;
}
.user-row {
    font-size: 12px;
    color: var(--ink-3);
    padding: 6px 0 2px;
}
.result-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
</style>
