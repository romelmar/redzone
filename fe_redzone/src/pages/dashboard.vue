<script setup>
import { ref, onMounted } from 'vue'
import api from '@/plugins/axios'
import { peso, apiError } from '@/helpers/operations'
const data = ref(null)
const loading = ref(false)
const error = ref('')
async function load() {
  loading.value = true
  error.value = ''
  try { data.value = (await api.get('/api/operations/dashboard')).data }
  catch (e) { error.value = apiError(e) }
  finally { loading.value = false }
}
onMounted(load)
</script>
<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6 flex-wrap gap-3">
      <div><h1 class="text-h4">Daily operations</h1><p class="mb-0">{{ data?.date || 'Today' }} / Philippines time</p></div>
      <VBtn :loading="loading" @click="load">Refresh</VBtn>
    </div>
    <VAlert v-if="error" type="error" class="mb-4">{{ error }}</VAlert>
    <VProgressLinear v-if="loading" indeterminate class="mb-4" />
    <template v-if="data">
      <VRow>
        <VCol cols="12" sm="6" lg="3"><VCard class="pa-5"><div>Collections today</div><h2 class="text-h4 text-success my-2">{{ peso(data.collections_today) }}</h2><small>{{ data.payments_today }} payments / excludes offsets and adjustments</small></VCard></VCol>
        <VCol cols="12" sm="6" lg="3"><VCard class="pa-5"><div>Overdue balance</div><h2 class="text-h4 text-error my-2">{{ peso(data.overdue_total) }}</h2><small>{{ data.overdue_count }} subscriptions</small></VCard></VCol>
        <VCol cols="12" sm="6" lg="3"><VCard class="pa-5"><div>Active subscriptions</div><h2 class="text-h4 my-2">{{ data.active_subscriptions }}</h2><small>{{ data.inactive_subscriptions }} inactive / {{ data.subscribers }} subscribers</small></VCard></VCol>
        <VCol cols="12" sm="6" lg="3"><VCard class="pa-5"><div>Due within 7 days</div><h2 class="text-h4 my-2">{{ data.upcoming_count }}</h2><small>Active subscription due dates</small></VCard></VCol>
      </VRow>
      <div class="d-flex flex-wrap gap-3 my-6"><VBtn to="/payments">Record payment</VBtn><VBtn to="/reconciliation" variant="tonal">Reconcile collections</VBtn><VBtn to="/payment-audit" variant="outlined">Review payment history</VBtn></div>
      <div class="d-flex flex-wrap gap-3 mb-5">
        <VBtn variant="outlined" to="/account-reports?type=overdue">Manage overdue subscribers</VBtn>
        <VBtn variant="outlined" to="/account-reports?type=disconnected">Manage disconnected accounts</VBtn>
        <VBtn variant="text" to="/account-reports?type=both">Review combined report</VBtn>
      </div>
      <VRow>
        <VCol cols="12" md="7"><VCard><VCardTitle>Largest overdue accounts</VCardTitle><VCardSubtitle class="pb-3">Includes inactive accounts with unpaid balances. Showing up to 15.</VCardSubtitle>
          <VTable><thead><tr><th>Subscriber</th><th>Status</th><th class="text-end">Overdue</th></tr></thead><tbody><tr v-for="row in data.overdue" :key="row.subscription_id"><td>{{ row.subscriber }}<small class="d-block">Subscription #{{ row.subscription_id }}</small></td><td>{{ row.active ? 'Active' : 'Inactive' }}</td><td class="text-end">{{ peso(row.amount) }}</td></tr><tr v-if="!data.overdue.length"><td colspan="3" class="pa-5">No overdue accounts.</td></tr></tbody></VTable>
        </VCard></VCol>
        <VCol cols="12" md="5"><VCard><VCardTitle>Upcoming due dates</VCardTitle><VCardSubtitle class="pb-3">Showing the next 15 subscriptions.</VCardSubtitle>
          <VTable><thead><tr><th>Subscriber</th><th>Due date</th></tr></thead><tbody><tr v-for="row in data.upcoming" :key="row.subscription_id"><td>{{ row.subscriber }}<small class="d-block">Subscription #{{ row.subscription_id }}</small></td><td>{{ row.due_date }}</td></tr><tr v-if="!data.upcoming.length"><td colspan="2" class="pa-5">No upcoming due dates.</td></tr></tbody></VTable>
        </VCard></VCol>
      </VRow>
      <p class="mt-5 text-medium-emphasis">Outstanding through this billing month: {{ peso(data.outstanding_balance) }}. Figures use current billing records and exclude voided payments.</p>
    </template>
  </div>
</template>
