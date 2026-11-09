<template>
    <div class="nft-page">
        <!-- 页头 -->
        <div class="page-head rise" :style="{ '--i': 0 }">
            <h2>第三方渠道</h2>
            <span class="en">Third-party</span>
        </div>

        <!-- 筛选条 -->
        <div class="glass filter-bar rise" :style="{ '--i': 1 }">
            <span class="cell-sub">支付与第三方通道配置；密钥类字段请妥善保管，停用通道后 C 端对应功能立即不可用</span>
            <span style="flex: 1"></span>
            <button v-if="auth('save')" class="btn btn-primary btn-sm" @click="openAdd">添加支付渠道</button>
        </div>

        <!-- 渠道卡片 -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 16px; margin-top: 16px">
            <div v-for="(ch, ci) in channels" :key="ch.id" class="glass rise" :style="{ '--i': ci + 2 }">
                <div class="card-head">
                    <h3>{{ ch.name }}</h3>
                    <span style="display: inline-flex; align-items: center; gap: 8px">
                        <span class="tag" :class="ch.enabled ? 'tag-green' : 'tag-gray'">{{ ch.enabled ? '已启用' : '未启用' }}</span>
                        <span class="en">Pay Channel</span>
                    </span>
                </div>
                <div class="card-pad">
                    <div v-for="it in tplOf(ch.channel).items" :key="it[0]" class="form-row">
                        <label>{{ it[1] }}</label>
                        <input v-model="ch.values[it[0]]" class="input" :disabled="!canSave" />
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center; margin-top: 6px">
                        <label v-if="canSave" style="font-size: 13px; display: inline-flex; align-items: center; gap: 6px">
                            启用通道
                            <select v-model.number="ch.enabled" class="select" style="width: 100px">
                                <option :value="1">启用</option>
                                <option :value="0">停用</option>
                            </select>
                        </label>
                        <button v-if="canSave" class="btn btn-primary" @click="saveCard(ch)">保存本渠道</button>
                        <button v-if="auth('del')" class="btn btn-ghost btn-sm" style="margin-left: auto" @click="delCard(ch)">删除渠道</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 添加渠道弹窗 -->
        <div v-if="addVisible" class="modal-mask fadein" @click.self="addVisible = false">
            <div class="modal glass pop" style="width: 460px">
                <div class="modal-head">
                    <h3>添加支付渠道</h3>
                    <span class="close" @click="addVisible = false">✕</span>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <label>渠道类型（决定配置字段模板）</label>
                        <select v-model="addForm.channel" class="select" style="width: 100%">
                            <option v-for="(v, k) in PAY_TPL" :key="k" :value="k">{{ v.label }}</option>
                        </select>
                    </div>
                    <div class="form-row">
                        <label>渠道名称（后台展示用，建议标注环境）</label>
                        <input v-model="addForm.name" class="input" placeholder="如：汇付天下-正式环境" />
                    </div>
                    <div class="form-row">
                        <label>初始状态</label>
                        <select v-model.number="addForm.enabled" class="select" style="width: 100%">
                            <option :value="0">停用（先配置，验证后再启用）</option>
                            <option :value="1">启用</option>
                        </select>
                    </div>
                    <div class="cell-sub" style="margin-top: 4px">添加后会生成该类型的配置表单，密钥类字段请在沙箱验证通过后再切换正式环境并启用。</div>
                </div>
                <div class="modal-foot">
                    <button class="btn btn-ghost" @click="addVisible = false">取消</button>
                    <button class="btn btn-primary" @click="submitAdd">确认添加</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { auth } from '/@/utils/common'
import * as api from '/@/api/backend/nft/thirdparty'

defineOptions({ name: 'nft/thirdparty' })

/** 渠道类型 → 配置字段模板（与后端 CHANNELS 对应） */
const PAY_TPL: Record<string, { label: string; items: [string, string][] }> = {
    huifu: {
        label: '汇付天下钱包',
        items: [
            ['sys_id', '汇聚系统号（Sys ID）'],
            ['merchant_id', '汇付商户号'],
            ['store_id', '门店编号'],
            ['private_key', '商户 RSA 私钥'],
            ['cert_sn', '证书序列号'],
            ['gateway', '网关地址'],
            ['notify_url', '异步通知地址'],
        ],
    },
    wechat: {
        label: '微信支付',
        items: [
            ['mch_id', '商户号'],
            ['appid', 'AppID'],
            ['api_v3_key', 'APIv3 密钥'],
            ['serial_no', '证书序列号'],
            ['private_key', '商户私钥'],
            ['gateway', '网关地址'],
            ['notify_url', '异步通知地址'],
        ],
    },
    alipay: {
        label: '支付宝',
        items: [
            ['app_id', '应用 AppID'],
            ['private_key', '应用私钥'],
            ['ali_public_key', '支付宝公钥'],
            ['gateway', '网关地址'],
            ['notify_url', '异步通知地址'],
        ],
    },
    custom: {
        label: '自定义渠道',
        items: [
            ['gateway', '网关地址'],
            ['merchant_id', '商户号'],
            ['secret', '接口密钥'],
            ['notify_url', '异步通知地址'],
        ],
    },
}
const tplOf = (channel: string) => PAY_TPL[channel] || { label: channel, items: [] }

const canSave = computed(() => auth('save'))

interface ChannelCard {
    id: number
    channel: string
    name: string
    enabled: number
    values: Record<string, string>
}
const channels = ref<ChannelCard[]>([])

const load = async () => {
    const res = await api.list()
    channels.value = (res.data.list || []).map((row: any) => {
        const values: Record<string, string> = {}
        for (const [key] of tplOf(row.channel).items) {
            values[key] = String(row.config?.[key] ?? '')
        }
        return { id: Number(row.id), channel: row.channel, name: row.name, enabled: Number(row.enabled) ? 1 : 0, values }
    })
}

const saveCard = async (ch: ChannelCard) => {
    if (!ch.name.trim()) {
        ElMessage.warning('请输入渠道名称')
        return
    }
    const config: Record<string, string> = {}
    for (const [key] of tplOf(ch.channel).items) {
        config[key] = ch.values[key] ?? ''
    }
    await api.save({ id: ch.id, name: ch.name.trim(), enabled: ch.enabled, config })
    ElMessage.success(`${ch.name} 配置已保存，通道${ch.enabled ? '已启用' : '已停用'}`)
    load()
}

const addVisible = ref(false)
const addForm = reactive({ channel: 'huifu', name: '', enabled: 0 })
const openAdd = () => {
    Object.assign(addForm, { channel: 'huifu', name: '', enabled: 0 })
    addVisible.value = true
}
const submitAdd = async () => {
    const name = addForm.name.trim() || PAY_TPL[addForm.channel].label
    const config: Record<string, string> = {}
    for (const [key] of PAY_TPL[addForm.channel].items) {
        config[key] = ''
    }
    await api.save({ id: 0, channel: addForm.channel, name, enabled: addForm.enabled, config })
    addVisible.value = false
    ElMessage.success(`支付渠道「${name}」已添加，请完善配置信息`)
    load()
}

const delCard = (ch: ChannelCard) => {
    if (channels.value.length <= 1) {
        ElMessage.error('至少保留一个支付渠道')
        return
    }
    if (ch.enabled) {
        ElMessage.error('已启用的渠道不能删除，请先停用')
        return
    }
    ElMessageBox.confirm(`确认删除支付渠道「${ch.name}」？删除后 C 端将无法使用该渠道支付`, '危险操作', { type: 'warning' }).then(() =>
        api.del(ch.id).then(() => {
            ElMessage.success(`支付渠道「${ch.name}」已删除`)
            load()
        })
    )
}

onMounted(() => load())
</script>
