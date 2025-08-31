import { defineStore } from 'pinia'

export const useToastStore = defineStore('toast', {
  state: () => ({
    visible: false,
    message: '',
    color: 'success',
    timeout: 2000,
  }),
  actions: {
    show(message, color = 'success', timeout = 2000) {
      this.message = message
      this.color = color
      this.timeout = timeout
      this.visible = true
    },
    hide() {
      this.visible = false
    },
  },
})
