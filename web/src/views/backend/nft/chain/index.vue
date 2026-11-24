<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>区块链管理</h2>
            <span class="en">BLOCKCHAIN</span>
        </div>

        <!-- 链配置 -->
        <div class="glass rise" :style="{ '--i': 1 }" style="margin-bottom: 14px">
            <div class="card-head"><h3>链配置</h3><span class="en">CONFIG</span></div>
            <div class="chain-cfg">
                <div class="cfg-grid">
                    <div class="form-row">
                        <label>上链开关（开启后业务单据自动生成「待上链」记录）</label>
                        <select v-model="cfg.chain_enabled" class="select">
                            <option value="0">关闭</option>
                            <option value="1">开启</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>网络环境</label>
                        <select v-model="cfg.chain_net" class="select">
                            <option value="test">测试网</option>
                            <option value="main">主网</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>链名称</label>
                        <input v-model="cfg.chain_name" class="input" placeholder="如：长安链 / FabricBFT / BSN" />
                    </div>
                    <div class="form-row">
                        <label>节点 RPC 地址</label>
                        <input v-model="cfg.chain_rpc" class="input" placeholder="https://node.example.com" />
                    </div>
                    <div class="form-row">
                        <label>合约地址 / 链码名称</label>
                        <input v-model="cfg.chain_contract" class="input" placeholder="0x…" />
                    </div>
                    <div class="form-row">
                        <label>区块链浏览器地址（记录页拼接哈希跳转）</label>
                        <input v-model="cfg.chain_scan" class="input" placeholder="https://scan.example.com" />
                    </div>
                    <div class="form-row">
                        <label>平台钱包地址</label>
                        <input v-model="cfg.chain_platform_wallet" class="input" placeholder="平台收款/归集地址" />
                    </div>
                </div>
                <div class="group-foot" style="padding: 4px 0 0">
                    <span class="cell-sub">接入真实链前可先开启开关排队记录，由「补录哈希」人工回填交易哈希</span>
                    <button v-if="auth('config')" class="btn btn-primary btn-sm" :disabled="savingCfg" @click="saveCfg">{{ savingCfg ? '保存中…' : '保存链配置' }}</button>
                </div>
            </div>
        </div>

        <!-- 统计 + 筛选 -->
        <div class="glass filter-bar rise" :style="{ '--i': 2 }">
            <span class="stat-inline">待上链 <b class="serif-num">{{ stat.pending }}</b></span>
            <span class="stat-inline">已上链 <b class="serif-num ok">{{ stat.ok }}</b></span>
            <span class="stat-inline">失败 <b class="serif-num bad">{{ stat.fail }}</b></span>
            <span style="flex: 1"></span>
            <select v-model="query.biz_type" class="select" style="width: 140px" @change="load(1)">
                <option value="">全部业务</option>
                <option v-for="[k, v] in bizTypes" :key="k" :value="k">{{ v }}</option>
            </select>
            <select v-model="query.status" class="select" style="width: 120px" @change="load(1)">
                <option value="-1">全部状态</option>
                <option value="0">待上链</option>
                <option value="1">已上链</option>
                <option value="2">失败</option>
            </select>
            <input v-model="query.keyword" class="input" style="width: 190px" placeholder="搜索哈希/地址/备注" @keyup.enter="load(1)" />
            <button class="btn btn-ghost" @click="load(1)">搜索</button>
            <button v-if="auth('record')" class="btn btn-primary" @click="openRecord">补录哈希</button>
        </div>

        <!-- 上链记录 -->
        <div class="glass rise" :style="{ '--i': 3 }">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>ID</th><th>业务</th><th>单据</th><th>链</th><th>交易哈希</th><th>区块</th><th>钱包地址</th><th>状态</th><th>备注</th><th>时间</th><th style="width: 110px">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in list" :key="row.id">
                            <td class="serif-num">{{ row.id }}</td>
                            <td><span class="tag tag-blue">{{ row.biz_type_cn }}</span></td>
                            <td class="serif-num">#{{ row.biz_id }}</td>
                            <td class="cell-sub">{{ row.chain || '-' }}</td>
                            <td class="serif-num hash-cell">
                                <a v-if="row.scan_url" :href="row.scan_url" target="_blank" class="hash-link">{{ shortHash(row.tx_hash) }}</a>
                                <span v-else-if="row.tx_hash">{{ shortHash(row.tx_hash) }}</span>
                                <span v-else class="cell-sub">待回填</span>
                            </td>
                            <td class="serif-num">{{ row.block_no || '-' }}</td>
                            <td class="cell-sub">{{ row.address ? shortHash(row.address) : '-' }}</td>
                            <td>
                                <span class="tag" :class="row.status == 1 ? 'tag-green' : (row.status == 2 ? 'tag-red' : 'tag-orange')">{{ row.status_cn }}</span>
                            </td>
                            <td class="cell-sub remark-cell">{{ row.remark || '-' }}</td>
                            <td class="cell-sub">{{ fmtTime(row.updatetime || row.createtime) }}</td>
                            <td style="white-space: nowrap">
                                <button v-if="row.status == 2 && auth('retry')" class="btn btn-ghost btn-sm" @click="onRetry(row)">重试</button>
                                <span v-else-if="row.status == 0" class="cell-sub">排队中</span>
                                <span v-else class="cell-sub">-</span>
                            </td>
                        </tr>
                        <tr v-if="!list.length">
                            <td colspan="11"><div class="empty">暂无上链记录</div></td>
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
        <div v-if="recOpen" class="modal-mask" @click.self="recOpen = false">
            <div class="glass modal modal-w480">
                <div class="modal-head"><h3>补录上链哈希</h3><span class="close" @click="recOpen = false">×</span></div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>业务类型</label>
                        <select v-model="recForm.biz_type" class="select">
                            <option v-for="[k, v] in bizTypes" :key="k" :value="k">{{ v }}</option>
                        </select>
                    </div>
                    <div class="form-row"><label>业务单据 ID</label><input v-model="recForm.biz_id" class="input serif-num" placeholder="订单/转赠单等 ID" /></div>
                    <div class="form-row"><label>交易哈希（0x 开头）</label><input v-model="recForm.tx_hash" class="input serif-num" placeholder="0x…" /></div>
                    <div class="form-row"><label>区块高度（可选）</label><input v-model="recForm.block_no" class="input serif-num" placeholder="区块号" /></div>
                    <div class="form-row"><label>用户钱包地址（可选）</label><input v-model="recForm.address" class="input serif-num" placeholder="0x…" /></div>
                    <div class="form-row"><label>备注（可选）</label><input v-model="recForm.remark" class="input" placeholder="补录说明" /></div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="recOpen = false">取消</button>
                    <button class="btn btn-primary" :disabled="savingRec" @click="submitRecord">{{ savingRec ? '提交中…' : '确认补录' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { reactive, ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { auth } from '/@/utils/common'
import { fmtTime } from '/@/utils/nft'
import * as api from '/@/api/backend/nft/chain'

defineOptions({ name: 'nft/chain' })

const bizTypes: [string, string][] = [
    ['order', '订单购买'],
    ['transfer', '转赠'],
    ['synthesis', '合成'],
    ['swap', '置换'],
    ['mint', '铸造/发行'],
    ['airdrop', '定向空投'],
]

const list = ref<any[]>([])
const total = ref(0)
const stat = reactive({ pending: 0, ok: 0, fail: 0 })
const cfg = reactive<Record<string, string>>({ chain_enabled: '0', chain_net: 'test', chain_name: '', chain_rpc: '', chain_contract: '', chain_scan: '', chain_platform_wallet: '' })
const query = reactive({ biz_type: '', status: '-1', keyword: '', page: 1, limit: 10 })
const savingCfg = ref(false)
const recOpen = ref(false)
const savingRec = ref(false)
const recForm = reactive({ biz_type: 'transfer', biz_id: '', tx_hash: '', block_no: '', address: '', remark: '' })

const shortHash = (h: string) => (h && h.length > 14 ? h.slice(0, 8) + '…' + h.slice(-6) : h || '')

const load = async (page = 1) => {
    query.page = page
    const res = await api.list({ biz_type: query.biz_type, status: query.status, keyword: query.keyword, page: query.page, limit: query.limit })
    list.value = res.data.list || []
    total.value = res.data.total || 0
    Object.assign(stat, res.data.stat || {})
    Object.assign(cfg, res.data.config || {})
}

const saveCfg = async () => {
    savingCfg.value = true
    try {
        await api.saveConfig({ data: { ...cfg } })
        ElMessage.success('链配置已保存')
    } finally {
        savingCfg.value = false
    }
}

const openRecord = () => {
    Object.assign(recForm, { biz_type: query.biz_type || 'transfer', biz_id: '', tx_hash: '', block_no: '', address: '', remark: '' })
    recOpen.value = true
}

const submitRecord = async () => {
    savingRec.value = true
    try {
        await api.record({
            biz_type: recForm.biz_type,
            biz_id: Number(recForm.biz_id) || 0,
            tx_hash: recForm.tx_hash.trim(),
            block_no: Number(recForm.block_no) || 0,
            address: recForm.address.trim(),
            remark: recForm.remark,
        })
        ElMessage.success('补录成功')
        recOpen.value = false
        load(1)
    } finally {
        savingRec.value = false
    }
}

const onRetry = async (row: any) => {
    await api.retry(row.id)
    ElMessage.success('已重置为待上链')
    load(query.page)
}

onMounted(() => load(1))
</script>

<style scoped>
.chain-cfg {
    padding: 4px 20px 16px;
}
.cfg-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 20px;
}
.stat-inline {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--ink-3);
}
.stat-inline b {
    font-size: 15px;
    color: var(--ink);
}
.stat-inline b.ok {
    color: var(--ok);
}
.stat-inline b.bad {
    color: var(--danger);
}
.hash-cell {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.hash-link {
    color: var(--blue-deep);
    text-decoration: none;
}
.hash-link:hover {
    text-decoration: underline;
}
.remark-cell {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
@media (max-width: 768px) {
    .cfg-grid {
        grid-template-columns: 1fr;
    }
}
</style>
