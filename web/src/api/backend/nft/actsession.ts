/**
 * 数字藏品 · 多场次活动 API（act_session）
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.ActSession/'

export function index(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: { id?: number; type: number; title: string; start_time?: number; end_time?: number }) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function del(id: number) {
    return createAxios({ url: url + 'del', method: 'post', data: { id } })
}

export function toggle(id: number, status: number) {
    return createAxios({ url: url + 'toggle', method: 'post', data: { id, status } })
}
