<template>
    <div>
        <!-- 原型登录页 1:1 移植（preview renderLogin） -->
        <div id="login-view">
            <div class="login-card glass pop">
                <div class="login-logo">灵</div>
                <h1>灵境数藏</h1>
                <div class="en">ADMIN CONSOLE</div>
                <el-form @keyup.enter="onSubmitPre()" ref="formRef" :rules="rules" :model="form" label-position="top" class="login-form">
                    <el-form-item prop="username" label="账号">
                        <el-input ref="usernameRef" type="text" clearable v-model="form.username" placeholder="admin" />
                    </el-form-item>
                    <el-form-item prop="password" label="密码">
                        <el-input ref="passwordRef" v-model="form.password" type="password" show-password placeholder="123456" />
                    </el-form-item>
                    <el-form-item>
                        <el-button :loading="state.submitLoading" class="submit-button" type="primary" @click="onSubmitPre()">
                            登 录
                        </el-button>
                    </el-form-item>
                </el-form>
                <div class="login-tip">灵境数藏 · 数字藏品管理后台</div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount, reactive, nextTick, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { editDefaultLang } from '/@/lang/index'
import { useConfig } from '/@/stores/config'
import { useAdminInfo } from '/@/stores/adminInfo'
import { login } from '/@/api/backend'
import { uuid } from '/@/utils/random'
import { buildValidatorData } from '/@/utils/validate'
import router from '/@/router'
import clickCaptcha from '/@/components/clickCaptcha'
import { fullUrl } from '/@/utils/common'
import { adminBaseRoutePath } from '/@/router/static/adminBase'
const adminInfo = useAdminInfo()

const formRef = useTemplateRef('formRef')
const usernameRef = useTemplateRef('usernameRef')
const passwordRef = useTemplateRef('passwordRef')

const state = reactive({
    showCaptcha: false,
    submitLoading: false,
})
const form = reactive({
    username: '',
    password: '',
    keep: true,
    captchaId: uuid(),
    captchaInfo: '',
})

const { t } = useI18n()

// 表单验证规则
const rules = reactive({
    username: [buildValidatorData({ name: 'required', message: t('login.Please enter an account') }), buildValidatorData({ name: 'account' })],
    password: [buildValidatorData({ name: 'required', message: t('login.Please input a password') }), buildValidatorData({ name: 'password' })],
})

const focusInput = () => {
    if (form.username === '') {
        usernameRef.value?.focus()
    } else if (form.password === '') {
        passwordRef.value?.focus()
    }
}

onMounted(() => {
    login('get')
        .then((res) => {
            state.showCaptcha = res.data.captcha
            nextTick(() => focusInput())
        })
        .catch((err) => {
            console.log(err)
        })
})

const onSubmitPre = () => {
    formRef.value?.validate((valid) => {
        if (valid) {
            if (state.showCaptcha) {
                clickCaptcha(form.captchaId, (captchaInfo: string) => onSubmit(captchaInfo))
            } else {
                onSubmit()
            }
        }
    })
}

const onSubmit = (captchaInfo = '') => {
    state.submitLoading = true
    form.captchaInfo = captchaInfo
    login('post', form)
        .then((res) => {
            adminInfo.dataFill(res.data.userInfo, false)
            router.push({ path: adminBaseRoutePath })
        })
        .finally(() => {
            state.submitLoading = false
        })
}
</script>

<style scoped lang="scss">
.switch-language {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1;
}
.bubble {
    overflow: hidden;
    background: url(/@/assets/bg.jpg) repeat;
}
.form-item-icon {
    height: auto;
}
.login {
    position: absolute;
    top: 0;
    display: flex;
    width: 100vw;
    height: 100vh;
    align-items: center;
    justify-content: center;
    .login-box {
        overflow: hidden;
        width: 430px;
        padding: 0;
        background: var(--ba-bg-color-overlay);
        margin-bottom: 80px;
    }
    .head {
        background: #ccccff;
        img {
            display: block;
            width: 430px;
            margin: 0 auto;
            user-select: none;
        }
    }
    .form {
        position: relative;
        .profile-avatar {
            display: block;
            position: absolute;
            height: 100px;
            width: 100px;
            border-radius: 50%;
            border: 4px solid var(--ba-bg-color-overlay);
            top: -50px;
            right: calc(50% - 50px);
            z-index: 2;
            user-select: none;
        }
        .content {
            padding: 100px 40px 40px 40px;
        }
        .submit-button {
            width: 100%;
            letter-spacing: 2px;
            font-weight: 300;
            margin-top: 15px;
            --el-button-bg-color: var(--el-color-primary);
        }
    }
}

@media screen and (max-width: 720px) {
    .login {
        display: flex;
        align-items: center;
        justify-content: center;
        .login-box {
            width: 340px;
            margin-top: 0;
        }
    }
}
.chang-lang :deep(.el-dropdown-menu__item) {
    justify-content: center;
}
.content :deep(.el-input__prefix) {
    display: flex;
    align-items: center;
}

// 暗黑样式
@at-root .dark {
    .bubble {
        background: url(/@/assets/bg-dark.jpg) repeat;
    }
    .login {
        .login-box {
            background: #161b22;
        }
        .head {
            img {
                filter: brightness(61%);
            }
        }
        .form {
            .submit-button {
                --el-button-bg-color: var(--el-color-primary-light-5);
                --el-button-border-color: rgba(240, 252, 241, 0.1);
            }
        }
    }
}
@media screen and (max-height: 800px) {
    .login .login-box {
        margin-bottom: 0;
    }
}
</style>
