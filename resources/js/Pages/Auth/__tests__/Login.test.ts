import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import Login from '../Login.vue'

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
    it('renders brand icon from public directory', () => {
        const wrapper = mount(Login, {
            global: {
                plugins: [vuetify],
                stubs: { teleport: true },
            },
        })

        const img = wrapper.find('img[alt="Herold"]')
        expect(img.exists()).toBe(true)
        expect(img.attributes('src')).toBe('/images/herold-icon.png')
    })
})
