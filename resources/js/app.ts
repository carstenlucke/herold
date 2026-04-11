import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'
import '../css/app.css'

const vuetify = createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'heroldDark',
    themes: {
      heroldDark: {
        dark: true,
        colors: {
          background: '#0a0a12',
          surface: '#10101c',
          'surface-bright': '#1e1e38',
          'surface-variant': '#16162a',
          primary: '#ff2d78',
          secondary: '#00ffcc',
          warning: '#ffe04a',
          error: '#ff4444',
          'on-background': '#e0e0ec',
          'on-surface': '#e0e0ec',
        },
      },
      heroldLight: {
        dark: false,
        colors: {
          background: '#f5f5f5',
          surface: '#ffffff',
          'surface-bright': '#fafafa',
          'surface-variant': '#eeeeee',
          primary: '#ff2d78',
          secondary: '#00997a',
          warning: '#cc9900',
          error: '#cc0000',
        },
      },
    },
  },
})

createInertiaApp({
  resolve: (name: string) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(vuetify)
      .mount(el)
  },
})
