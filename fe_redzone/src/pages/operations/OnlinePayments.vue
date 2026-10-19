<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import api from '@/plugins/axios'
import PaymentLink from '@/components/PaymentLink.vue'
import { apiError, peso, businessDate } from '@/helpers/operations'
const records = ref({ data: [] })
const page = ref(1)
const status = ref('')
const busy = ref(false)
const error = ref('')
const message = ref('')
const settlement = ref(null)
const settlementOpen = computed({ get: () => !!settlement.value, set: value => { if (!value) settlement.value = null } })
const settlementReference = ref('')
const settlementDate = ref(businessDate())
let timer
async function load() {
  try { records.value = (await api.get('/api/online-payments', { params: { page: page.value, status: status.value || undefined } })).data }
  catch (e) { error.value = apiError(e) }
}
async function action(row, name, data = {}) {
  busy.value = true; error.value = ''; message.value = ''
  try {
    const result = (await api.post(`/api/online-payments/${row.id}/${name}`, data)).data
    message.value = result.review ? 'Payment requires review. Check the reason below.' : result.pending ? 'PayMongo has not confirmed payment yet.' : 'Payment status updated.'
    settlement.value = null; await load()
  } catch (e) { error.value = apiError(e) } finally { busy.value = false }
}
async function expire(row) {
  if (window.confirm('Expire this checkout? Redzone will confirm expiration with PayMongo before allowing a replacement.')) await action(row, 'expire')
}
async function recover(row) {
  const checkoutId = window.prompt(`Find reference ${row.reference} in PayMongo, then enter its checkout session ID. Do not create another session.`)
  if (checkoutId?.trim()) await action(row, 'recover', { checkout_id: checkoutId.trim() })
}
function startSettlement(row) { settlement.value = row; settlementReference.value = ''; settlementDate.value = businessDate() }
onMounted(() => { load(); timer = setInterval(() => { if (!busy.value) load() }, 20000) })
onBeforeUnmount(() => clearInterval(timer))
</script>
<template>
  <h1 class="text-h4 mb-2">Online payments</h1><p>Review PayMongo checkouts, confirmed payments, and settlements. Gateway fees do not reduce the subscriber’s payment credit.</p>
  <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }}</VAlert><VAlert v-if="message" type="info" variant="tonal" class="mb-4">{{ message }}</VAlert>
  <div class="d-flex gap-3 mb-4"><VSelect v-model="status" label="Status" :items="['ready', 'creating', 'pending', 'review', 'paid', 'paid_review', 'expired']" clearable hide-details style="max-width:260px" @update:model-value="page = 1; load()" /><VBtn :disabled="busy" @click="load">Refresh</VBtn></div>
  <VCard><VTable><thead><tr><th>Request / Subscriber</th><th>Status</th><th>Gross / Fee / Net</th><th>Gateway / Settlement</th><th>Actions</th></tr></thead><tbody>
    <tr v-for="row in records.data" :key="row.id">
      <td>{{ row.subscription?.subscriber?.name }}<small class="d-block">Subscription #{{ row.subscription_id }} · {{ row.mode.toUpperCase() }}</small><small>{{ row.reference }}</small></td>
      <td><VChip size="small" :color="row.status.includes('review') ? 'warning' : row.payment_id ? 'success' : 'info'">{{ row.status }}</VChip><small v-if="row.review_reason" class="d-block" style="max-width:250px;white-space:normal">{{ row.review_reason }}</small></td>
      <td>{{ peso(row.amount_centavos / 100) }}<small class="d-block">Fee: {{ row.fee_centavos == null ? 'Pending' : peso(row.fee_centavos / 100) }}</small><small>Net: {{ row.net_centavos == null ? 'Pending' : peso(row.net_centavos / 100) }}</small></td>
      <td><small class="d-block">{{ row.checkout_id || 'No confirmed session' }}</small><small class="d-block">{{ row.provider_payment_id }}</small><small>{{ row.settled_at ? `${row.settlement_reference} · ${row.settled_at.slice(0, 10)}` : 'Not marked settled' }}</small></td>
      <td><div class="d-flex flex-wrap gap-2 py-2" style="min-width:210px"><PaymentLink :request-id="row.id" />
        <VBtn v-if="row.checkout_id && !row.payment_id" size="small" variant="text" :disabled="busy" @click="action(row, 'sync')">Sync</VBtn>
        <VBtn v-if="!row.checkout_id && ['review', 'creating'].includes(row.status)" size="small" variant="text" :disabled="busy" @click="recover(row)">Find checkout</VBtn>
        <VBtn v-if="!row.payment_id && (row.checkout_id || row.status === 'ready') && row.status !== 'expired'" size="small" color="warning" variant="text" :disabled="busy" @click="expire(row)">Expire</VBtn>
        <VBtn v-if="row.payment_id && !row.settled_at" size="small" variant="text" :disabled="busy" @click="startSettlement(row)">Record settlement</VBtn>
      </div></td>
    </tr><tr v-if="!records.data.length"><td colspan="5" class="pa-6 text-center">No online payments found.</td></tr>
  </tbody></VTable><VCardActions><span>{{ records.total || 0 }} requests · Page {{ page }}</span><VSpacer /><VBtn :disabled="page === 1 || busy" @click="page--; load()">Previous</VBtn><VBtn :disabled="!records.next_page_url || busy" @click="page++; load()">Next</VBtn></VCardActions></VCard>
  <VDialog v-model="settlementOpen" max-width="500" :persistent="busy"><VCard><VCardTitle>Record verified bank settlement</VCardTitle><VCardText><p>Confirm the payout in your PayMongo dashboard and bank statement. This records settlement evidence; it does not create another subscriber payment.</p><VTextField v-model="settlementReference" label="PayMongo payout / bank reference" maxlength="255" /><VTextField v-model="settlementDate" label="Settlement date" type="date" :max="businessDate()" /></VCardText><VCardActions><VBtn :disabled="busy" @click="settlement = null">Cancel</VBtn><VSpacer /><VBtn :loading="busy" :disabled="busy || !settlementReference || !settlementDate" @click="action(settlement, 'settlement', { settlement_reference: settlementReference, settled_at: settlementDate })">Record settlement</VBtn></VCardActions></VCard></VDialog>
</template>
