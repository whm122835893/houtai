/**
 * 数字藏品 · 藏品管理 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Collection/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

/** GET edit 动作返回单行原始数据（含富文本） */
export function getRow(id: number) {
    return createAxios({ url: url + 'edit', method: 'get', params: { id } })
}

export function add(data: object) {
    return createAxios({ url: url + 'add', method: 'post', data })
}

export function edit(id: number, data: object = {}) {
    return createAxios({ url: url + 'edit', method: 'post', data: { id, ...data } })
}

export function del(ids: number[]) {
    return createAxios({ url: url + 'del', method: 'post', data: { ids } })
}

export function sale(id: number, saleTime: string, saleEndTime: string = '') {
    return createAxios({ url: url + 'sale', method: 'post', data: { id, sale_time: saleTime, sale_end_time: saleEndTime } })
}

export function off(id: number) {
    return createAxios({ url: url + 'off', method: 'post', data: { id } })
}

export function soldout(id: number) {
    return createAxios({ url: url + 'soldout', method: 'post', data: { id } })
}
