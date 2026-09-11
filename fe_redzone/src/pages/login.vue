<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import logo from '@images/logo.png'
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const isPasswordVisible = ref(false)
const loading = ref(false)
const error = ref('')
async function handleLogin() {
  if (loading.value) return
  loading.value = true
  error.value = ''
  try {
    await auth.login({ email: email.value, password: password.value })
    await router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || 'Unable to sign in. Check your credentials and try again.'
  } finally { loading.value = false }
}
</script>
<template>
  <main class="redzone-login">
    <section class="login-intro"><img :src="logo" alt="REDZONE" width="140" /><div class="login-eyebrow">OPERATIONS WORKSPACE</div><h1>Your business.<br>Your network.<br>One workspace.</h1><p>Keep subscriber accounts, billing, and daily collections organized.</p><div class="login-feature"><VIcon icon="bx-user-check" /> Subscriber management</div><div class="login-feature"><VIcon icon="bx-wallet" /> Billing &amp; collections</div><div class="login-feature"><VIcon icon="bx-bar-chart-alt-2" /> Operational reporting</div></section>
    <VCard class="login-panel" variant="flat"><VCardText><div class="login-eyebrow">WELCOME BACK</div><h2>Sign in to REDZONE</h2><p class="mb-6">Enter your staff account credentials to continue.</p><VAlert v-if="route.query.reason === 'session-expired' && !error" type="info" variant="tonal" class="mb-5">Your session has expired. Please sign in again.</VAlert><VAlert v-if="error" type="error" variant="tonal" class="mb-5" role="alert">{{ error }}</VAlert><form @submit.prevent="handleLogin"><VTextField v-model="email" label="Email address" type="email" autocomplete="username" autofocus required class="mb-5" :disabled="loading" /><VTextField v-model="password" label="Password" autocomplete="current-password" required class="mb-6" :disabled="loading" :type="isPasswordVisible ? 'text' : 'password'" :append-inner-icon="isPasswordVisible ? 'bx-hide' : 'bx-show'" @click:append-inner="isPasswordVisible = !isPasswordVisible" /><VBtn type="submit" block size="large" :loading="loading" :disabled="loading">Sign in</VBtn></form><p class="login-help">Need access or a password reset? Contact your system administrator.</p></VCardText></VCard>
  </main>
</template>
