/**
 * 数字藏品 · 第三方渠道 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Thirdparty/'

export function list(params: object = {}) {
    return createAxios({ url: url + 'index', method: 'get', params })
}

export function save(data: object) {
    return createAxios({ url: url + 'save', method: 'post', data })
}

export function status(id: number, enabled: number) {
    return createAxios({ url: url + 'status', method: 'post', data: { id, enabled } })
}

export function del(id: number) {
    return createAxios({ url: url + 'del', method: 'post', data: { id } })
}

export function sms() {
    return createAxios({ url: url + 'sms', method: 'get' })
}

export function saveSms(data: { items: object[]; enabled: number }) {
    return createAxios({ url: url + 'saveSms', method: 'post', data })
}

export function sendSmsTest(phone: string) {
    return createAxios({ url: url + 'sendSmsTest', method: 'post', data: { phone } })
}
