<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import { apiError } from '@/helpers/operations'
const paymentId = ref('')
const page = ref(1)
const result = ref({ data: [], last_page: 1 })
const loading = ref(false)
const error = ref('')
const selected = ref(null)
const dialog = ref(false)
const fields = { subscription_id: 'Subscription', amount: 'Amount', payment_date: 'Payment date', payment_type: 'Type', collector_name: 'Collector', payment_method: 'Method', remarks: 'Remarks', deleted_at: 'Voided at' }
async function load() {
  loading.value = true
  error.value = ''
  try { result.value = (await api.get('/api/operations/payment-audits', { params: { page: page.value, payment_id: paymentId.value || undefined } })).data }
  catch (e) { error.value = apiError(e) }
  finally { loading.value = false }
}
function details(row) { selected.value = row; dialog.value = true }
onMounted(load)
</script>
<template>
  <div>
    <h1 class="text-h4 mb-2">Payment audit trail</h1><p>Corrections and voids retain the original values, staff member, time, and reason. Baseline entries capture records that existed when tracking began.</p>
    <VAlert v-if="error" type="error" class="mb-4">{{ error }}</VAlert>
    <div class="d-flex gap-3 mb-4"><VTextField v-model="paymentId" label="Payment ID (optional)" type="number" min="1" hide-details style="max-width:260px" /><VBtn :loading="loading" @click="page = 1; load()">Search</VBtn></div>
    <VCard><VProgressLinear v-if="loading" indeterminate aria-label="Loading audit history" /><VTable :aria-busy="loading"><thead><tr><th>Time (Philippines)</th><th>Payment</th><th>Action</th><th>Staff</th><th>Reason</th><th></th></tr></thead><tbody>
      <tr v-for="row in result.data" :key="row.id"><td>{{ new Date(row.created_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) }}</td><td>#{{ row.payment_id }}</td><td><VChip size="small" :color="row.action === 'voided' ? 'error' : 'primary'">{{ row.action }}</VChip></td><td>{{ row.actor_name }}</td><td style="max-width:350px;white-space:normal">{{ row.reason || '—' }}</td><td><VBtn variant="text" @click="details(row)">Details</VBtn></td></tr>
      <tr v-if="!loading && !result.data.length"><td colspan="6" class="pa-5">No matching payment history.</td></tr>
    </tbody></VTable><VPagination v-model="page" :length="result.last_page" :total-visible="5" @update:model-value="load" /></VCard>
    <VDialog v-model="dialog" max-width="800"><VCard v-if="selected"><VCardTitle>Payment #{{ selected.payment_id }} · {{ selected.action }}</VCardTitle><VCardText><p>{{ selected.reason }}</p><VTable><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody><tr v-for="(label, key) in fields" :key="key"><td>{{ label }}</td><td style="white-space:normal">{{ selected.before?.[key] ?? '—' }}</td><td style="white-space:normal">{{ selected.after?.[key] ?? '—' }}</td></tr></tbody></VTable></VCardText><VCardActions><VSpacer /><VBtn @click="dialog = false">Close</VBtn></VCardActions></VCard></VDialog>
  </div>
</template>
