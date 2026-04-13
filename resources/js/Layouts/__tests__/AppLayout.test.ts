import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import AppLayout from '../AppLayout.vue'

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/' }),
    router: { visit: vi.fn(), post: vi.fn() },
}))

const vuetify = createVuetify()

describe('AppLayout', () => {
    it('renders brand icon from public directory', () => {
        const wrapper = mount(AppLayout, {
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
