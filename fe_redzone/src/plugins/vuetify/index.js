import { createVuetify } from 'vuetify'
import { VBtn } from 'vuetify/components/VBtn'
import defaults from './defaults'
import { icons } from './icons'
import theme from './theme'
import { watch } from 'vue'
import { readThemePreference, saveThemePreference } from '@/helpers/themePreference'

// Styles
import '@core/scss/template/libs/vuetify/index.scss'
import 'vuetify/styles'

const vuetify = createVuetify({
  aliases: {
    IconBtn: VBtn,
  },
  defaults,
  icons,
  theme: { ...theme, defaultTheme: readThemePreference() },
})

watch(vuetify.theme.global.name, saveThemePreference, { flush: 'sync' })

export default vuetify
