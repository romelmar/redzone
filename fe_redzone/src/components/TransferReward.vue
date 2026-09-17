<script setup>
import { ref, computed } from 'vue'
import api from '@/plugins/axios'
import { businessDate, peso, apiError } from '@/helpers/operations'
const props = defineProps({ subscriptionId: { type: Number, required: true } })
const emit = defineEmits(['applied'])
const dialog = ref(false), busy = ref(false), error = ref(''), success = ref(false)
const month = ref(''), provider = ref(''), quote = ref(null), reviewedMonth = ref('')
const reviewed = computed(() => quote.value && reviewedMonth.value === month.value)
async function review() {
  busy.value = true; error.value = ''; quote.value = null
  try {
    const { data } = await api.get(`/api/subscriptions/${props.subscriptionId}/transfer-reward`, { params: { month: month.value } })
    quote.value = data; reviewedMonth.value = month.value
  } catch (e) { error.value = apiError(e) } finally { busy.value = false }
}
function open() {
  month.value = businessDate().slice(0, 7); provider.value = ''; success.value = false; dialog.value = true; review()
}
async function apply() {
  if (busy.value || !reviewed.value || !quote.value.eligible) return
  busy.value = true; error.value = ''
  try {
    const { data } = await api.post(`/api/subscriptions/${props.subscriptionId}/transfer-reward`, { month: month.value, previous_provider: provider.value.trim(), expected_amount: quote.value.amount })
    quote.value = { eligible: false, reward: data }; success.value = true; emit('applied')
  } catch (e) { error.value = apiError(e) } finally { busy.value = false }
}
</script>
<template>
  <VBtn size="small" variant="text" @click="open">Transfer reward</VBtn>
  <VDialog v-model="dialog" max-width="600" :persistent="busy"><VCard><VCardTitle>One-month transfer reward</VCardTitle><VCardText>
    <p>Subscription #{{ subscriptionId }}. Waive one billing month's recurring fee for a customer transferring from another provider.</p>
    <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert>
    <VAlert v-if="success" type="success" variant="tonal" class="mb-4">Reward applied. Billing balances and statements now include the credit.</VAlert>
    <template v-if="quote?.reward"><p><strong>Reward already recorded</strong></p><dl><dt>Billing month</dt><dd>{{ quote.reward.credit_month.slice(0, 7) }}</dd><dt>Credit</dt><dd>{{ peso(quote.reward.amount) }}</dd><dt>Previous provider</dt><dd>{{ quote.reward.previous_provider }}</dd><dt>Granted by</dt><dd>{{ quote.reward.granted_by_name }}</dd></dl></template>
    <template v-else><VTextField v-model="month" type="month" label="Free billing month" :max="businessDate().slice(0, 7)" :disabled="busy" class="mb-4" /><VTextField v-model="provider" label="Previous internet provider" maxlength="150" :disabled="busy" class="mb-4" />
    <VBtn variant="outlined" :loading="busy" :disabled="busy || !month" @click="review">Review credit</VBtn>
    <VAlert v-if="reviewed" :type="quote.eligible ? 'info' : 'warning'" variant="tonal" class="mt-4">{{ quote.eligible ? `Transfer reward credit: ${peso(quote.amount)}` : quote.reason }}</VAlert>
    <p class="text-caption mt-4">One reward per subscription. Existing discounts and service credits reduce the reward amount. Add-ons and previous balances remain payable. The reward is retained as a service credit, not a payment, and does not change the ongoing monthly rate.</p></template>
  </VCardText><VCardActions><VSpacer /><VBtn :disabled="busy" @click="dialog = false">Close</VBtn><VBtn v-if="!quote?.reward" color="primary" variant="flat" :loading="busy" :disabled="busy || !reviewed || !quote?.eligible || !provider.trim()" @click="apply">Apply one-month reward</VBtn></VCardActions></VCard></VDialog>
</template>
<style scoped>dt { margin-top: 10px; font-size: 12px; opacity: .7; } dd { margin: 2px 0 0; }</style>
