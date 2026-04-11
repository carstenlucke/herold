import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import AppLayout from '../AppLayout.vue'

beforeEach(() => {
    vi.stubEnv('BASE_URL', '/herold/')
})

afterEach(() => {
    vi.unstubAllEnvs()
})

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/' }),
    router: { visit: vi.fn(), post: vi.fn() },
}))

const vuetify = createVuetify()

describe('AppLayout', () => {
    it('builds brand icon src from BASE_URL', () => {
        const wrapper = mount(AppLayout, {
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
