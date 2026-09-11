<script setup>
import { computed, ref } from 'vue'
import api from '@/plugins/axios'
import { businessDate, peso, apiError } from '@/helpers/operations'
const props = defineProps({ subscriptionId: { type: Number, required: true }, email: String, month: String })
const dialog = ref(false)
const type = ref('billing')
const from = ref('')
const to = ref('')
const billingMonth = ref('')
const busy = ref(false)
const error = ref('')
const success = ref('')
const preview = ref(null)
const reviewed = ref('')
const label = computed(() => type.value === 'billing' ? 'billing statement' : 'statement of account')
const signature = computed(() => JSON.stringify([from.value, to.value]))
const validPreview = computed(() => preview.value && reviewed.value === signature.value)
function open() {
  billingMonth.value = (props.month || businessDate()).slice(0, 7)
  from.value = billingMonth.value + '-01'
  to.value = businessDate()
  preview.value = null; error.value = ''; success.value = ''; dialog.value = true
}
async function perform(action) {
  if (busy.value) return
  if (action === 'email' && !window.confirm(`Email this ${label.value} to ${props.email}?`)) return
  busy.value = true; error.value = ''; success.value = ''
  const account = type.value === 'account'
  const params = account ? { from: from.value, to: to.value } : { month: billingMonth.value + '-01' }
  const root = `/api/subscriptions/${props.subscriptionId}`
  try {
    if (action === 'preview') {
      const { data } = await api.get(root + '/account-statement', { params })
      preview.value = data; reviewed.value = signature.value
    } else if (action === 'email') {
      await api.post(root + (account ? '/account-statement/email' : '/billing-statement/email'), params)
      success.value = `The ${label.value} was emailed to ${props.email}.`
    } else {
      const { data } = await api.get(root + (account ? '/account-statement/pdf' : '/billing-statement'), { params, responseType: 'blob' })
      const url = URL.createObjectURL(data)
      const link = document.createElement('a')
      link.href = url
      link.download = `${account ? 'Statement-of-account' : 'Billing-statement'}-${props.subscriptionId}-${account ? to.value : billingMonth.value}.pdf`
      link.click(); setTimeout(() => URL.revokeObjectURL(url), 60000)
    }
  } catch (e) {
    if (e.response?.data instanceof Blob) { try { e.response.data = JSON.parse(await e.response.data.text()) } catch {} }
    error.value = apiError(e)
  } finally { busy.value = false }
}
</script>
<template>
  <VBtn size="small" variant="outlined" prepend-icon="bx-file" @click="open">Statements</VBtn>
  <VDialog v-model="dialog" max-width="1000" :persistent="busy"><VCard><VCardTitle>Statements / Subscription #{{ subscriptionId }}</VCardTitle><VCardText>
    <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert><VAlert v-if="success" type="success" variant="tonal" class="mb-4">{{ success }}</VAlert>
    <VSelect v-model="type" :disabled="busy" label="Document type" :items="[{ title: 'Billing statement', value: 'billing' }, { title: 'Statement of account', value: 'account' }]" class="mb-4" />
    <template v-if="type === 'billing'"><p>Monthly bill showing previous balance, monthly charges, credits, payments, and amount due.</p><VTextField v-model="billingMonth" label="Billing month" type="month" :disabled="busy" /></template>
    <template v-else><p>Account activity with opening balance, dated charges, payments, credits, and running balance. Monthly charges and service credits are posted on the first day of their billing month.</p><VRow><VCol cols="12" sm="5"><VTextField v-model="from" label="From date" type="date" :max="to" :disabled="busy" /></VCol><VCol cols="12" sm="5"><VTextField v-model="to" label="Through date" type="date" :min="from" :max="businessDate()" :disabled="busy" /></VCol><VCol cols="12" sm="2"><VBtn :loading="busy" :disabled="!from || !to" @click="perform('preview')">Review</VBtn></VCol></VRow>
      <template v-if="validPreview"><div class="d-flex flex-wrap gap-4 my-4"><span>Opening: <strong>{{ peso(preview.opening_balance) }}</strong></span><span>Charges: <strong>{{ peso(preview.total_charges) }}</strong></span><span>Payments &amp; credits: <strong>{{ peso(preview.total_credits) }}</strong></span><span>Closing: <strong>{{ peso(preview.closing_balance) }}</strong></span></div><VTable height="320" fixed-header density="compact"><thead><tr><th>Date</th><th>Reference / Description</th><th>Charges</th><th>Credits</th><th>Balance</th></tr></thead><tbody><tr v-for="entry in preview.entries" :key="entry.reference"><td>{{ entry.date }}</td><td>{{ entry.reference }}<div>{{ entry.description }}</div></td><td>{{ peso(entry.debit) }}</td><td>{{ peso(entry.credit) }}</td><td>{{ peso(entry.balance) }}</td></tr><tr v-if="!preview.entries.length"><td colspan="5">No transactions in this period.</td></tr></tbody></VTable><p class="text-caption mt-3">Negative balances represent credit. Documents are recalculated when downloaded or emailed.</p></template>
    </template><p class="text-caption mt-4 mb-0">Email recipient: {{ email || 'No email on file. Update the subscriber record to enable email.' }}</p>
  </VCardText><VCardActions><VBtn :disabled="busy" @click="dialog = false">Close</VBtn><VSpacer /><VBtn :loading="busy" :disabled="busy || (type === 'account' ? !validPreview : !billingMonth)" @click="perform('download')">Download {{ label }}</VBtn><VBtn color="primary" variant="flat" :disabled="busy || !email || (type === 'account' ? !validPreview : !billingMonth)" @click="perform('email')">Email {{ label }}</VBtn></VCardActions></VCard></VDialog>
</template>
