import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import Login from '../Login.vue'

beforeEach(() => {
    vi.stubEnv('BASE_URL', '/herold/')
})

afterEach(() => {
    vi.unstubAllEnvs()
})

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { errors: {} } }),
    useForm: (defaults: Record<string, unknown>) => ({
        ...defaults,
        processing: false,
        errors: {},
        post: vi.fn(),
        reset: vi.fn(),
    }),
}))

vi.mock('qrcode', () => ({
    default: { toDataURL: vi.fn() },
}))

const vuetify = createVuetify()

describe('Login', () => {
    it('builds brand icon src from BASE_URL', () => {
        const wrapper = mount(Login, {
            global: {
                plugins: [vuetify],
                stubs: { teleport: true },
            },
        })

        const img = wrapper.find('img[alt="Herold"]')
        expect(img.exists()).toBe(true)
        expect(img.attributes('src')).toBe('/herold/images/herold-icon.png')
        expect(img.attributes('src')).not.toBe('/images/herold-icon.png')
    })
})
