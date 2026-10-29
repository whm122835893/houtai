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
