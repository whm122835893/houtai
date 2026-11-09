/**
 * 数字藏品 · 置换管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Swap/'

export const SWAP_MAX_SRC = 5
export const SWAP_MAX_RATIO = 10

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

/** 预览名单：items=[{collection_id,x,y}] + new_id，返回 users/total_recovered/total_airdropped/pool/enough */
export function preview(data: object) {
    return createAxios({ url: url + 'preview', method: 'post', data })
}

/** 执行置换：items + new_id + reason */
export function exec(data: object) {
    return createAxios({ url: url + 'exec', method: 'post', data })
}

/** 计划明细（源明细 + 用户名单快照分页） */
export function detail(id: number, params: object = {}) {
    return createAxios({ url: url + 'detail', method: 'get', params: { id, ...params } })
}
