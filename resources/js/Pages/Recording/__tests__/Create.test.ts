import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import Create from '../Create.vue'

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ url: '/notes/create' }),
    router: { visit: vi.fn(), post: vi.fn() },
    useForm: (defaults: Record<string, unknown>) => ({
        ...defaults,
        processing: false,
        errors: { 'metadata.vault': 'The metadata.vault field must not be greater than 255 characters.' },
        post: vi.fn(),
    }),
}))

// jsdom lacks ResizeObserver, which Vuetify's input components rely on
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver

const vuetify = createVuetify({ components })

describe('Recording/Create', () => {
    it('renders metadata slot validation errors on the slot field', () => {
        const wrapper = mount(Create, {
            props: {
                types: {
                    obsidian: {
                        label: 'Obsidian',
                        icon: 'mdi-note',
                        github_label: 'obsidian',
                        extra_fields: [{ name: 'vault', type: 'text', required: false, label: 'Vault' }],
                    },
                },
                maxTextSlotLength: 255,
                maxUrlSlotLength: 2048,
            },
            global: {
                plugins: [vuetify],
                stubs: { teleport: true, AudioRecorder: true },
            },
        })

        expect(wrapper.text()).toContain('must not be greater than 255 characters')
        expect(wrapper.text()).toContain('0 / 255')
    })
})
