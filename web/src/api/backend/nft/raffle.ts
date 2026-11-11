/**
 * 数字藏品 · 抽签发售 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Raffle/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function del(ids: number[]) {
    return createAxios({ url: url + 'del', method: 'post', data: { ids } })
}

export function draw(id: number) {
    return createAxios({ url: url + 'draw', method: 'post', data: { id } })
}

export function applys(params: object = {}) {
    return createAxios({ url: url + 'applys', method: 'get', params })
}

export function codes(params: object = {}) {
    return createAxios({ url: url + 'codes', method: 'get', params })
}

export function grantCode(data: { raffle_id: number; user: string; num?: number; remark?: string }) {
    return createAxios({ url: url + 'grantcode', method: 'post', data })
}

export function voidCode(id: number) {
    return createAxios({ url: url + 'voidcode', method: 'post', data: { id } })
}
