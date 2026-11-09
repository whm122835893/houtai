/**
 * 数字藏品 · 发放名录（人群快照）API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Roster/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

/** 生成名单（服务端圈人冻结快照，空结果报错） */
export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

/** 预览命中人数 */
export function estimate(params: object) {
    return createAxios({ url: url + 'estimate', method: 'get', params })
}

/** 明细（roster_item 分页） */
export function detail(id: number, params: object = {}) {
    return createAxios({ url: url + 'detail', method: 'get', params: { id, ...params } })
}

/** 删除（被空投引用的禁止删） */
export function del(ids: number[]) {
    return createAxios({ url: url + 'del', method: 'post', data: { ids } })
}

/** 导入白名单 */
export function toWhitelist(data: object) {
    return createAxios({ url: url + 'toWhitelist', method: 'post', data })
}
