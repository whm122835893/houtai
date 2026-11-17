/**
 * 数字藏品 · 区块链管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Chain/'

/** 链配置 + 上链记录列表 + 统计 */
export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

/** 保存链配置（data: {name: value}） */
export function saveConfig(data: object) {
    return createAxios({ url: url + 'config', method: 'post', data })
}

/** 补录交易哈希 */
export function record(data: object) {
    return createAxios({ url: url + 'record', method: 'post', data })
}

/** 失败记录重试（重置为待上链） */
export function retry(id: number) {
    return createAxios({ url: url + 'retry', method: 'post', data: { id } })
}
