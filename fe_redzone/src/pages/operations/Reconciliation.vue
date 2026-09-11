<script setup>
import { computed, ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import { peso, businessDate, apiError } from '@/helpers/operations'
import { newRequestKey } from '@/helpers/requestKey'

const date = ref(businessDate())
const report = ref({ rows: [], remittances: [] })
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const formError = ref('')
const dialog = ref(false)
const form = ref({})
const collectors = computed(() => [...new Set(report.value.rows.map(r => r.collector_name).filter(Boolean))])
const total = key => report.value.rows.reduce((sum, row) => sum + Number(row[key]), 0)
async function load() {
  loading.value = true
  error.value = ''
  try { report.value = (await api.get('/api/operations/reconciliation', { params: { date: date.value } })).data }
  catch (e) { error.value = apiError(e); report.value = { rows: [], remittances: [] } }
  finally { loading.value = false }
}
function openForm() {
  form.value = { collection_date: date.value, collector_name: '', payment_method: 'cash', amount: '', reference: '', notes: '', request_key: newRequestKey() }
  formError.value = ''
  dialog.value = true
}
async function save() {
  if (saving.value) return
  saving.value = true
  formError.value = ''
  try { await api.post('/api/operations/remittances', form.value); dialog.value = false; await load() }
  catch (e) { formError.value = apiError(e) }
  finally { saving.value = false }
}
async function voidRecord(row) {
  const reason = prompt(`Void remittance #${row.id}? Enter a reason. Its history will be retained.`)
  if (!reason) return
  try { await api.post(`/api/operations/remittances/${row.id}/void`, { reason }); await load() }
  catch (e) { error.value = apiError(e) }
}
onMounted(load)
</script>

<template>
  <div>
    <div class="d-flex justify-space-between align-center flex-wrap gap-3 mb-5">
      <div><h1 class="text-h4">Cash reconciliation</h1><p class="mb-0">Compare collections with money actually remitted.</p></div>
      <VBtn :disabled="loading || !!error" @click="openForm">Record remittance</VBtn>
    </div>
    <VAlert v-if="error" type="error" class="mb-4">{{ error }}</VAlert>
    <div class="d-flex gap-3 mb-5"><VTextField v-model="date" type="date" label="Collection date" hide-details style="max-width:240px" /><VBtn :loading="loading" @click="load">Load</VBtn></div>
    <VRow class="mb-5"><VCol v-for="card in [{ key: 'collected', label: 'Recorded collections' }, { key: 'remitted', label: 'Actual remittances' }, { key: 'unremitted', label: 'Difference' }]" :key="card.key" cols="12" md="4"><VCard class="pa-5"><div>{{ card.label }}</div><h2 class="text-h4 mt-2">{{ peso(total(card.key)) }}</h2></VCard></VCol></VRow>
    <VCard class="mb-6"><VCardTitle>Collector balances · {{ report.date || date }}</VCardTitle>
      <VCardText>Positive differences are unremitted collections; negative differences need review for excess remittances. Cash, GCash, and bank transfers are tracked separately. Offsets and adjustments are excluded. Assign missing collectors or methods on the payment record before reconciling.</VCardText>
      <VProgressLinear v-if="loading" indeterminate aria-label="Loading reconciliation" /><VTable :aria-busy="loading"><thead><tr><th>Collector</th><th>Method</th><th>Payments</th><th>Collected</th><th>Remitted</th><th>Difference</th></tr></thead><tbody>
        <tr v-for="(row, i) in report.rows" :key="i"><td>{{ row.collector_name || 'Unassigned' }}</td><td>{{ row.payment_method }}</td><td>{{ row.payment_count }}</td><td>{{ peso(row.collected) }}</td><td>{{ peso(row.remitted) }}</td><td :class="row.unremitted > 0 ? 'text-error' : row.unremitted < 0 ? 'text-warning' : 'text-success'">{{ peso(row.unremitted) }}</td></tr>
        <tr v-if="!loading && !report.rows.length"><td colspan="6" class="pa-5">No collections or remittances for this date.</td></tr>
      </tbody></VTable>
    </VCard>
    <VCard><VCardTitle>Remittance history</VCardTitle><VTable><thead><tr><th>Reference</th><th>Collector / method</th><th>Amount</th><th>Recorded by</th><th>Status</th><th></th></tr></thead><tbody>
      <tr v-for="row in report.remittances" :key="row.id"><td>#{{ row.id }} · {{ row.reference }}<small class="d-block">{{ row.notes }}</small></td><td>{{ row.collector_name }} · {{ row.payment_method }}</td><td>{{ peso(row.amount) }}</td><td>{{ row.recorded_by_name }}<small class="d-block">{{ new Date(row.created_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) }}</small></td><td>{{ row.voided_at ? 'Voided' : 'Recorded' }}<small v-if="row.voided_at" class="d-block">{{ row.voided_by_name }}: {{ row.void_reason }}</small></td><td><VBtn v-if="!row.voided_at" color="error" variant="text" @click="voidRecord(row)">Void</VBtn></td></tr>
      <tr v-if="!loading && !report.remittances.length"><td colspan="6" class="pa-5">No remittances recorded.</td></tr>
    </tbody></VTable></VCard>
    <VDialog v-model="dialog" persistent max-width="600"><VCard><VCardTitle>Record actual remittance</VCardTitle><VCardText>
      <VAlert v-if="formError" type="error" class="mb-4">{{ formError }}</VAlert>
      <p>Record only money received or verified. This does not create a subscriber payment.</p>
      <VTextField v-model="form.collection_date" type="date" label="Collection date" />
      <VCombobox v-model="form.collector_name" :items="collectors" label="Collector name" />
      <VSelect v-model="form.payment_method" :items="['cash', 'gcash', 'bank']" label="Payment method" />
      <VTextField v-model="form.amount" type="number" step="0.01" min="0.01" label="Amount received" />
      <VTextField v-model="form.reference" label="Cash acknowledgment / transfer reference" />
      <VTextarea v-model="form.notes" label="Notes" rows="2" />
    </VCardText><VCardActions><VSpacer /><VBtn :disabled="saving" @click="dialog = false">Cancel</VBtn><VBtn :loading="saving" :disabled="saving" @click="save">Save remittance</VBtn></VCardActions></VCard></VDialog>
  </div>
</template>
