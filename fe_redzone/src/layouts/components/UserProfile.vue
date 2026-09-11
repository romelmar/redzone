<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'
const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()
const busy = ref(false)
const name = computed(() => auth.user?.name || auth.user?.email || 'Staff account')
const initials = computed(() => name.value.split(/\s+/).slice(0, 2).map(s => s[0]).join('').toUpperCase())
async function logout() {
  if (busy.value) return
  busy.value = true
  try { await auth.logout() }
  catch { toast.show('Signed out on this device. The server could not confirm logout.', 'warning') }
  finally { busy.value = false; await router.replace('/login') }
}
</script>
<template><VMenu location="bottom end" offset="12"><template #activator="{ props }"><VBtn v-bind="props" icon variant="tonal" :aria-label="`Account menu for ${name}`">{{ initials }}</VBtn></template><VList min-width="260"><VListItem :title="name" :subtitle="auth.user?.email" /><VDivider class="my-2" /><VListItem prepend-icon="bx-log-out" title="Sign out" :disabled="busy" @click="logout" /></VList></VMenu></template>
