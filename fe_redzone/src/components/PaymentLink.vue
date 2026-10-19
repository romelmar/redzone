<script setup>
import { ref } from 'vue'
import api from '@/plugins/axios'
import { apiError } from '@/helpers/operations'
const props = defineProps({ subscriptionId: Number, requestId: Number })
const dialog = ref(false)
const busy = ref(false)
const error = ref('')
const link = ref(null)
const copied = ref(false)
async function open() {
  dialog.value = true; busy.value = true; error.value = ''; link.value = null; copied.value = false
  try {
    const response = props.requestId
      ? await api.get(`/api/online-payments/${props.requestId}/link`)
      : await api.post(`/api/subscriptions/${props.subscriptionId}/payment-link`)
    link.value = response.data
  } catch (e) { error.value = apiError(e) } finally { busy.value = false }
}
async function copy() {
  try { await navigator.clipboard.writeText(link.value.url); copied.value = true }
  catch { error.value = 'Select and copy the payment link manually.' }
}
</script>
<template>
  <VBtn size="small" variant="outlined" prepend-icon="bx-link" @click="open">Payment link</VBtn>
  <VDialog v-model="dialog" max-width="560"><VCard><VCardTitle>Share payment link</VCardTitle><VCardText>
    <VProgressLinear v-if="busy" indeterminate aria-label="Preparing payment link" />
    <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
    <template v-if="link">
      <VAlert v-if="link.mode === 'test'" type="warning" variant="tonal" class="mb-4">TEST MODE — use test accounts only. No real money is collected.</VAlert>
      <p>This private link shows the current account balance. Share it directly with the subscriber.</p>
      <img :src="link.qr" alt="QR code for the private Redzone bill payment page" width="220" height="220" class="d-block mx-auto my-4" />
      <VTextField :model-value="link.url" readonly label="Subscriber payment link" @focus="$event.target.select()" />
      <p v-if="link.expires_at" class="text-caption">New checkout available until {{ new Date(link.expires_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) }}. An existing pending checkout is reused.</p>
      <VBtn class="mr-2" @click="copy">{{ copied ? 'Copied' : 'Copy link' }}</VBtn>
      <VBtn :href="link.url" target="_blank" rel="noreferrer" variant="outlined">Open payment page</VBtn>
    </template>
  </VCardText><VCardActions><VSpacer /><VBtn @click="dialog = false">Close</VBtn></VCardActions></VCard></VDialog>
</template>
