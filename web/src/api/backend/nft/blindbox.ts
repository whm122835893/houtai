/**
 * 数字藏品 · 盲盒管理 API
 * 概率约定：前后端均传「万分比整数」（概率% × 10000，100% = 1000000）
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Blindbox/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

/** 新增/编辑（奖池一并保存），prizes 内 probability 为万分比整数 */
export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function status(id: number) {
    return createAxios({ url: url + 'status', method: 'post', data: { id } })
}

/** GET 奖池 / POST 整体保存 */
export function prizes(boxId: number, data: object | null = null) {
    if (data === null) {
        return createAxios({ url: url + 'prizes', method: 'get', params: { box_id: boxId } })
    }
    return createAxios({ url: url + 'prizes', method: 'post', data: { box_id: boxId, ...data } })
}

export function records(params: object = {}) {
    return createAxios({ url: url + 'records', method: 'get', params })
}
