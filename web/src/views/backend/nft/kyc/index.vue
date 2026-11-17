<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>实名审核</h2>
            <span class="en">KYC</span>
        </div>

        <!-- 统计 + 说明 -->
        <div class="glass rise" :style="{ '--i': 1 }" style="margin-bottom: 14px">
            <div class="kyc-top">
                <div class="kyc-stats">
                    <button class="stat-pill" :class="{ active: query.status === -1 }" @click="setStatus(-1)">全部 <b class="serif-num">{{ stat.pending + stat.approved + stat.rejected }}</b></button>
                    <button class="stat-pill warn" :class="{ active: query.status === 0 }" @click="setStatus(0)">待审核 <b class="serif-num">{{ stat.pending }}</b></button>
                    <button class="stat-pill ok" :class="{ active: query.status === 1 }" @click="setStatus(1)">已通过 <b class="serif-num">{{ stat.approved }}</b></button>
                    <button class="stat-pill bad" :class="{ active: query.status === 2 }" @click="setStatus(2)">已驳回 <b class="serif-num">{{ stat.rejected }}</b></button>
                </div>
                <span class="cell-sub">当前审核模式：<span class="tag tag-blue">{{ modeCn }}</span></span>
            </div>
            <div class="kyc-tip">
                审核模式在下方「审核设置」中调整：人工审核（提交后进入本页审核）/ 自动通过（本地校验通过即生效）/ 第三方实名库（调用外部接口校验，接口不可用自动转人工）。开启「未实名禁交易」后，下单/寄售/转赠均要求先实名。
            </div>
        </div>

        <!-- 审核设置 -->
        <div class="glass rise" :style="{ '--i': 2 }" style="margin-bottom: 14px">
            <div class="card-head"><h3>审核设置</h3><span class="en">SETTINGS</span></div>
            <div class="kyc-cfg">
                <div class="form-row">
                    <label>审核模式</label>
                    <select v-model="cfg.kyc_mode" class="select">
                        <option value="manual">人工审核（提交后进入待审列表）</option>
                        <option value="auto">自动通过（本地校验通过即生效）</option>
                        <option value="third">第三方实名库（外部接口校验）</option>
                    </select>
                </div>
                <div class="kyc-cfg-row">
                    <span class="ck"><input v-model="cfg.kyc_check_dup" type="checkbox" true-value="1" false-value="0" /> 身份证占用查重（同一证件仅可绑定一个账号）</span>
                    <span class="ck"><input v-model="cfg.kyc_block_trade" type="checkbox" true-value="1" false-value="0" /> 未实名禁交易（下单/寄售/转赠需先实名）</span>
                </div>
                <template v-if="cfg.kyc_mode === 'third'">
                    <div class="form-row" style="margin-top: 10px">
                        <label>第三方实名接口地址（POST JSON：{key, name, idcard}，响应 {code:1=一致, 0=不一致}）</label>
                        <input v-model="cfg.kyc_third_url" class="input" placeholder="https://kyc.example.com/verify" />
                    </div>
                    <div class="form-row">
                        <label>接口密钥</label>
                        <input v-model="cfg.kyc_third_key" class="input" placeholder="接口密钥" />
                    </div>
                    <div class="brand-tip">接口不可用/超时将自动降级为人工审核，不会阻断用户提交</div>
                </template>
                <div class="group-foot" style="padding: 10px 0 0">
                    <span class="cell-sub">保存后即时生效（仅超级管理员可修改）</span>
                    <button v-if="auth('cfg')" class="btn btn-primary btn-sm" :disabled="savingCfg" @click="saveCfg">{{ savingCfg ? '保存中…' : '保存设置' }}</button>
                </div>
            </div>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 3 }">
            <input v-model="query.keyword" class="input" style="width: 200px" placeholder="搜索昵称/手机号/姓名/证件号" @keyup.enter="load(1)" />
            <button class="btn btn-ghost" @click="load(1)">搜索</button>
            <span style="flex: 1"></span>
            <button v-if="auth('export')" class="btn btn-ghost" @click="onExport">导出</button>
            <button v-if="auth('add')" class="btn btn-primary" @click="openAdd">后台补录</button>
        </div>

        <!-- 列表 -->
        <div class="glass rise" :style="{ '--i': 4 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th><th>用户</th><th>真实姓名</th><th>身份证号</th><th>照片</th><th>来源</th><th>审核方式</th><th>状态</th><th>提交时间</th><th style="width: 150px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td>
                                <div class="cell-main">{{ row.user_nickname }}</div>
                                <div class="cell-sub">{{ row.mobile || row.username }}</div>
                            </td>
                            <td class="cell-main">{{ row.real_name }}</td>
                            <td class="serif-num">{{ row.idcard }}</td>
                            <td>
                                <span v-if="!row.idcard_front && !row.idcard_back" class="cell-sub">未上传</span>
                                <span v-else class="tag tag-gray">已上传</span>
                            </td>
                            <td><span class="tag" :class="row.source == 2 ? 'tag-blue' : 'tag-gray'">{{ row.source_cn }}</span></td>
                            <td class="cell-sub">{{ row.mode_cn }}</td>
                            <td>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : (row.status == 2 ? 'tag-red' : 'tag-orange')">{{ row.status_cn }}</span>
                                <div v-if="row.status == 2 && row.audit_remark" class="cell-sub">{{ row.audit_remark }}</div>
                            </td>
                            <td class="cell-sub">{{ fmtTime(row.createtime) }}</td>
                            <td style="white-space: nowrap">
                                <template v-if="row.status == 0 && auth('audit')">
                                    <button class="btn btn-primary btn-sm" @click="onAudit(row, 1)">通过</button>
                                    <button class="btn btn-danger btn-sm" @click="onAudit(row, 2)">驳回</button>
                                </template>
                                <span v-else class="cell-sub">{{ row.audit_time ? fmtTime(row.audit_time) : '-' }}</span>
                            </td>
                        </tr>
                        <tr v-if="!list.length">
                            <td colspan="10"><div class="empty">暂无实名记录</div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="pager">
                <span class="cell-sub">共 {{ total }} 条</span>
                <button :disabled="query.page <= 1" @click="load(query.page - 1)">上一页</button>
                <span class="serif-num">{{ query.page }} / {{ Math.max(1, Math.ceil(total / query.limit)) }}</span>
                <button :disabled="query.page >= Math.ceil(total / query.limit)" @click="load(query.page + 1)">下一页</button>
            </div>
        </div>

        <!-- 补录弹窗 -->
        <div v-if="addOpen" class="modal-mask" @click.self="addOpen = false">
            <div class="glass modal modal-w480">
                <div class="modal-head"><h3>后台补录实名</h3><span class="close" @click="addOpen = false">×</span></div>
                <div class="modal-body">
                    <div class="form-row"><label>用户 ID</label><input v-model="addForm.user_id" class="input serif-num" placeholder="用户ID" /></div>
                    <div class="form-row"><label>真实姓名</label><input v-model="addForm.real_name" class="input" placeholder="与证件一致" /></div>
                    <div class="form-row"><label>身份证号</label><input v-model="addForm.idcard" class="input serif-num" placeholder="18 位证件号" /></div>
                    <div class="form-row"><label>备注（可选）</label><input v-model="addForm.remark" class="input" placeholder="补录原因" /></div>
                    <div class="brand-tip">补录立即生效并回写用户实名字段；受「身份证占用查重」约束</div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="addOpen = false">取消</button>
                    <button class="btn btn-primary" :disabled="savingAdd" @click="submitAdd">{{ savingAdd ? '提交中…' : '确认补录' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/kyc'

defineOptions({ name: 'nft/kyc' })

const list = ref<any[]>([])
const total = ref(0)
const stat = reactive({ pending: 0, approved: 0, rejected: 0 })
const query = reactive({ status: -1, keyword: '', page: 1, limit: 10 })
const cfg = reactive<Record<string, string>>({ kyc_mode: 'manual', kyc_check_dup: '1', kyc_block_trade: '0', kyc_third_url: '', kyc_third_key: '' })
const savingCfg = ref(false)
const addOpen = ref(false)
const savingAdd = ref(false)
const addForm = reactive({ user_id: '', real_name: '', idcard: '', remark: '' })

const modeCn = computed(() => ({ manual: '人工审核', auto: '自动通过', third: '第三方实名库' })[cfg.kyc_mode] || '人工审核')

const load = async (page = 1) => {
    query.page = page
    const res = await api.list({ status: query.status, keyword: query.keyword, page: query.page, limit: query.limit })
    list.value = res.data.list || []
    total.value = res.data.total || 0
    Object.assign(stat, res.data.stat || {})
    Object.assign(cfg, res.data.cfg || {})
}

const setStatus = (s: number) => {
    query.status = s
    load(1)
}

const saveCfg = async () => {
    savingCfg.value = true
    try {
        await api.saveCfg({ data: { ...cfg } })
        ElMessage.success('审核设置已保存')
    } finally {
        savingCfg.value = false
    }
}

const onAudit = async (row: any, status: number) => {
    if (status === 2) {
        const { value } = await ElMessageBox.prompt('请填写驳回理由（用户可见）', '驳回实名申请', { confirmButtonText: '确认驳回', cancelButtonText: '取消', inputPlaceholder: '如：证件号与姓名不一致' })
        await api.audit(row.id, 2, String(value || '').trim())
        ElMessage.success('已驳回')
    } else {
        await ElMessageBox.confirm(`确认通过「${row.real_name} / ${row.idcard}」的实名申请？通过后用户实名字段同步回写。`, '审核通过', { confirmButtonText: '确认通过', cancelButtonText: '取消' })
        await api.audit(row.id, 1)
        ElMessage.success('已通过')
    }
    load(query.page)
}

const openAdd = () => {
    Object.assign(addForm, { user_id: '', real_name: '', idcard: '', remark: '' })
    addOpen.value = true
}

const submitAdd = async () => {
    savingAdd.value = true
    try {
        await api.add({ ...addForm, user_id: Number(addForm.user_id) || 0 })
        ElMessage.success('补录成功')
        addOpen.value = false
        load(1)
    } finally {
        savingAdd.value = false
    }
}

const onExport = async () => {
    const res = await api.exportList({ status: query.status, keyword: query.keyword })
    const rows: string[][] = res.data.rows || []
    const csv = '\ufeff' + rows.map((r) => r.map((c) => '"' + String(c ?? '').replace(/"/g, '""') + '"').join(',')).join('\n')
    const a = document.createElement('a')
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }))
    a.download = `实名记录_${new Date().toISOString().slice(0, 10)}.csv`
    a.click()
    URL.revokeObjectURL(a.href)
}

onMounted(() => load(1))
</script>

<style scoped>
.kyc-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 14px 20px 0;
}
.kyc-stats {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.stat-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 30px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1.5px solid rgba(126, 139, 155, 0.3);
    background: rgba(255, 255, 255, 0.65);
    font-size: 13px;
    color: var(--ink-2);
    cursor: pointer;
    transition: all 0.2s ease;
}
.stat-pill b {
    font-size: 13px;
}
.stat-pill.active {
    border-color: var(--blue);
    background: rgba(var(--acc-rgb), 0.1);
    box-shadow: 0 0 0 3px rgba(var(--acc-rgb), 0.12);
}
.stat-pill.warn.active {
    border-color: var(--warn);
}
.stat-pill.ok.active {
    border-color: var(--ok);
}
.stat-pill.bad.active {
    border-color: var(--danger);
}
.kyc-tip {
    font-size: 12px;
    color: var(--ink-muted);
    padding: 10px 20px 14px;
    line-height: 1.7;
}
.kyc-cfg {
    padding: 4px 20px 16px;
}
.kyc-cfg-row {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.ck {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--ink-2);
    cursor: pointer;
}
.ck input {
    accent-color: var(--blue);
}
</style>
