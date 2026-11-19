/**
 * 数字藏品 · 验证码中心 API
 */
import createAxios from '/@/utils/axios'

export const url = '/admin/nft.Captcha/'

/** 配置读取（switches: 场景开关列表, mode: 验证形式） */
export function getConfig() {
    return createAxios({ url: url + 'index', method: 'get' })
}

/** 保存配置（data: {captcha_admin_login: '1', ..., captcha_mode: 'click'}） */
export function saveCfg(data: object) {
    return createAxios({ url: url + 'cfg', method: 'post', data })
}
