<script setup>
import { computed, reactive, ref, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/plugins/axios'
import { peso, businessDate, apiError } from '@/helpers/operations'
const route = useRoute()
const router = useRouter()
const compact = ref(false)
const printDialog = ref(false)
const detailDialog = ref(false)
const selected = ref(null)
const refreshedAt = ref('')
const reportError = ref('')
const visibleKeys = ref(['subscriber_id', 'name', 'plan', 'active', 'due_date', 'overdue', 'balance'])
const reportTypes = [{ value: 'overdue', title: 'Overdue', icon: 'bx-time-five' }, { value: 'disconnected', title: 'Disconnected', icon: 'bx-unlink' }, { value: 'both', title: 'Combined', icon: 'bx-list-ul' }]
const validType = value => ['overdue', 'disconnected', 'both'].includes(value) ? value : 'overdue'
const filters = reactive({ type: validType(route.query.type), date: businessDate(), search: '', sort_by: 'name', sort_dir: 'asc', per_page: 20 })
const applied = ref(null)
const result = ref({ data: [], total: 0, last_page: 1, subscriber_count: 0, overdue_total: 0, balance_total: 0 })
const page = ref(1)
const loading = ref(false)
const printing = ref(false)
const error = ref('')
const dirty = computed(() => !applied.value || JSON.stringify(filters) !== JSON.stringify(applied.value))
let requestNumber = 0
const columns = [
  ['subscriber_id', 'Subscriber ID'], ['subscription_id', 'Subscription ID'], ['name', 'Subscriber'],
  ['phone', 'Phone'], ['address', 'Address'], ['plan', 'Plan'], ['active', 'Status'],
  ['disconnected_at', 'Disconnected'], ['due_date', 'Due date'], ['overdue', 'Overdue'], ['balance', 'Balance'],
]
const visibleColumns = computed(() => columns.filter(([key]) => visibleKeys.value.includes(key)))
const reportTitle = computed(() => reportTypes.find(t => t.value === (applied.value?.type || filters.type))?.title || 'Accounts')
const sortLabel = computed(() => columns.find(([key]) => key === applied.value?.sort_by)?.[1] || 'Subscriber')
const rangeStart = computed(() => result.value.total ? (page.value - 1) * (applied.value?.per_page || 20) + 1 : 0)
const rangeEnd = computed(() => Math.min(page.value * (applied.value?.per_page || 20), result.value.total))
const hasResults = computed(() => applied.value !== null)
function changeType(type) {
  if (type !== filters.type) router.replace({ query: { ...route.query, type } })
}
function showDetails(row) { selected.value = row; detailDialog.value = true }
function displayDate(value) {
  if (!value) return 'Not recorded'
  const [year, month, day] = value.slice(0, 10).split('-').map(Number)
  return new Intl.DateTimeFormat('en-PH', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(year, month - 1, day))
}
function toggleColumn(key, enabled) {
  if (enabled) visibleKeys.value = [...visibleKeys.value, key]
  else visibleKeys.value = visibleKeys.value.filter(item => item !== key)
}
async function load(nextPage = 1) {
  const request = ++requestNumber
  const parameters = { ...filters }
  loading.value = true
  error.value = ''
  try {
    const response = await api.get('/api/operations/accounts', { params: { ...parameters, page: nextPage } })
    if (request !== requestNumber) return
    result.value = response.data
    page.value = nextPage
    applied.value = parameters
    refreshedAt.value = new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' })
  } catch (e) {
    if (request === requestNumber) { error.value = apiError(e); applied.value = null }
  } finally { if (request === requestNumber) loading.value = false }
}
function sort(key) {
  filters.sort_dir = filters.sort_by === key && filters.sort_dir === 'asc' ? 'desc' : 'asc'
  filters.sort_by = key
  load()
}
function reset() {
  Object.assign(filters, { type: validType(route.query.type), date: businessDate(), search: '', sort_by: 'name', sort_dir: 'asc', per_page: 20 })
  load()
}
async function printReport() {
  if (printing.value || loading.value || dirty.value || !result.value.total) return
  const tab = window.open('about:blank', '_blank')
  if (tab) { tab.opener = null; tab.document.title = 'Preparing report...' }
  printing.value = true
  reportError.value = ''
  try {
    const { per_page, ...parameters } = applied.value
    const response = await api.get('/api/operations/accounts/print', { params: parameters, responseType: 'blob' })
    const url = URL.createObjectURL(response.data)
    if (tab) tab.location.href = url
    else {
      const link = document.createElement('a')
      link.href = url
      link.download = `redzone-${parameters.type}-${parameters.date}.pdf`
      link.click()
    }
    printDialog.value = false
    setTimeout(() => URL.revokeObjectURL(url), 180000)
  } catch (e) {
    tab?.close()
    if (e.response?.data instanceof Blob) {
      try { e.response.data = JSON.parse(await e.response.data.text()) } catch { /* use fallback */ }
    }
    reportError.value = apiError(e)
  } finally { printing.value = false }
}
watch(() => route.query.type, value => { filters.type = validType(value); load() })
onMounted(() => load())
</script>

<template>
  <section class="account-workspace" aria-labelledby="account-title">
    <header class="workspace-header">
      <div>
        <div class="eyebrow">COLLECTIONS &amp; ACCOUNT OVERSIGHT</div>
        <h1 id="account-title">Account reports</h1>
        <p class="subtitle">Review balances, prioritize follow-ups, and prepare your collection reports.</p>
      </div>
      <div class="header-actions">
        <VBtn variant="outlined" :disabled="loading" prepend-icon="bx-refresh" @click="load()">Refresh</VBtn>
        <VBtn color="primary" prepend-icon="bx-printer" :disabled="loading || printing || dirty || !result.total" @click="reportError = ''; printDialog = true">Print report</VBtn>
      </div>
    </header>

    <div class="report-tabs" aria-label="Report category">
      <VTabs :model-value="filters.type" color="primary" @update:model-value="changeType">
        <VTab v-for="type in reportTypes" :key="type.value" :value="type.value"><VIcon :icon="type.icon" size="18" class="me-2" />{{ type.title }}</VTab>
      </VTabs>
      <span class="updated-time" v-if="refreshedAt && !loading"><span class="live-dot" /> Updated {{ refreshedAt }}</span>
    </div>

    <div class="metric-grid" aria-live="polite">
      <article class="metric-card"><div class="metric-top"><span>Matching accounts</span><VIcon icon="bx-group" size="21" /></div><strong>{{ hasResults ? result.total.toLocaleString() : 'Not available' }}</strong><span class="metric-note">{{ hasResults ? result.subscriber_count : 'Not available' }} distinct subscribers</span></article>
      <article class="metric-card overdue-metric"><div class="metric-top"><span>Total overdue</span><VIcon icon="bx-time-five" size="21" /></div><strong>{{ hasResults ? peso(result.overdue_total) : 'Not available' }}</strong><span class="metric-note">Unpaid amounts past their due date</span></article>
      <article class="metric-card"><div class="metric-top"><span>Outstanding balance</span><VIcon icon="bx-wallet" size="21" /></div><strong>{{ hasResults ? peso(result.balance_total) : 'Not available' }}</strong><span class="metric-note">Total balance as of the selected date</span></article>
    </div>

    <VCard class="results-card" variant="flat">
      <form class="filter-bar" @submit.prevent="load()">
        <VTextField v-model="filters.search" class="search-control" label="Search accounts" placeholder="Name, ID, phone, address, or plan" prepend-inner-icon="bx-search" clearable variant="outlined" density="compact" hide-details />
        <VTextField v-model="filters.date" class="date-control" type="date" label="Balances as of" variant="outlined" density="compact" hide-details />
        <VBtn type="submit" color="primary" :loading="loading">Apply filters</VBtn>
        <VBtn variant="text" :disabled="loading" @click="reset">Reset</VBtn>
      </form>
      <div v-if="dirty && hasResults && !loading" class="pending-banner" role="status"><VIcon icon="bx-info-circle" size="18" /><span>You have unapplied changes. Apply filters to update results and enable printing.</span></div>
      <VAlert v-if="error" type="error" variant="tonal" class="mx-5 mb-4" role="alert"><div class="d-flex align-center justify-space-between flex-wrap gap-2"><span>{{ error }}</span><VBtn variant="text" size="small" @click="load()">Retry</VBtn></div></VAlert>
      <div class="table-toolbar">
        <div><h2>{{ reportTitle }} accounts <span class="count-badge" v-if="hasResults">{{ result.total }}</span></h2><p>{{ hasResults ? `Balances as of ${displayDate(applied.date)}` : 'Choose filters to view matching accounts' }}<span v-if="applied?.search"> / Search: {{ applied.search }}</span></p></div>
        <div class="table-tools">
          <VBtn :variant="compact ? 'tonal' : 'text'" size="small" :aria-pressed="compact" prepend-icon="bx-table" @click="compact = !compact">{{ compact ? 'Compact' : 'Comfortable' }}</VBtn>
          <VMenu :close-on-content-click="false" location="bottom end"><template #activator="{ props }"><VBtn v-bind="props" size="small" variant="outlined" prepend-icon="bx-columns">Columns</VBtn></template><VCard class="pa-3" min-width="240"><div class="text-subtitle-2 mb-2">Visible columns</div><VCheckbox v-for="[key, label] in columns" :key="key" :model-value="visibleKeys.includes(key)" :label="label" :disabled="key === 'name'" density="compact" hide-details @update:model-value="toggleColumn(key, $event)" /></VCard></VMenu>
        </div>
      </div>
      <div class="table-region" :aria-busy="loading">
        <VProgressLinear v-if="loading" indeterminate color="primary" class="table-progress" aria-label="Loading accounts" />
        <VTable class="accounts-table" :density="compact ? 'compact' : 'comfortable'" fixed-header height="min(62vh, 680px)">
          <thead><tr><th v-for="[key, label] in visibleColumns" :key="key" :class="{ 'money-cell': ['overdue', 'balance'].includes(key) }" :aria-sort="applied?.sort_by === key ? (applied.sort_dir === 'asc' ? 'ascending' : 'descending') : 'none'"><button type="button" class="sort-button" :disabled="loading" @click="sort(key)">{{ label }}<VIcon size="14" :class="{ 'sort-active': applied?.sort_by === key }">{{ applied?.sort_by === key ? (applied.sort_dir === 'asc' ? 'bx-up-arrow-alt' : 'bx-down-arrow-alt') : 'bx-sort' }}</VIcon></button></th><th class="action-heading"><span class="sr-only">Account details</span></th></tr></thead>
          <tbody>
            <template v-if="loading && !hasResults"><tr v-for="n in 6" :key="n"><td v-for="[key] in visibleColumns" :key="key"><span class="skeleton-bar" /></td><td /></tr></template>
            <template v-else-if="hasResults"><tr v-for="row in result.data" :key="row.subscription_id" :class="{ 'refreshing-row': loading }">
              <td v-for="[key] in visibleColumns" :key="key" :class="{ 'money-cell': ['overdue', 'balance'].includes(key), 'name-cell': key === 'name', 'id-cell': key.endsWith('_id') }">
                <button v-if="key === 'name'" class="account-name" @click="showDetails(row)"><strong>{{ row.name }}</strong><span>Subscription #{{ row.subscription_id }}<template v-if="row.phone"> / {{ row.phone }}</template></span></button>
                <span v-else-if="['overdue', 'balance'].includes(key)" :class="{ 'overdue-value': key === 'overdue' && row.overdue > 0 }">{{ peso(row[key]) }}</span>
                <span v-else-if="key === 'active'" :class="['status-pill', row.active ? 'is-active' : 'is-disconnected']"><span />{{ row.active ? 'Active' : 'Disconnected' }}</span>
                <span v-else-if="['due_date', 'disconnected_at'].includes(key)" class="date-value">{{ row[key] ? displayDate(row[key]) : 'Not available' }}</span>
                <span v-else :class="{ 'address-value': key === 'address' }">{{ row[key] || 'Not available' }}</span>
              </td><td><VBtn icon="bx-chevron-right" size="small" variant="text" :aria-label="`View ${row.name}`" @click="showDetails(row)" /></td>
            </tr></template>
            <tr v-if="!loading && !error && !result.data.length"><td :colspan="visibleColumns.length + 1"><div class="empty-state"><VIcon icon="bx-file-find" size="40" /><h3>No accounts match these filters</h3><p>Try a different search or balance date, or reset to today's report.</p><VBtn variant="tonal" @click="reset">Reset filters</VBtn></div></td></tr>
          </tbody>
        </VTable>
      </div>
      <footer class="table-footer"><span class="range-label">{{ hasResults ? `${rangeStart}-${rangeEnd} of ${result.total} accounts` : 'No results loaded' }}</span><div class="pagination-controls"><VSelect v-model="filters.per_page" :items="[10,20,50,100]" label="Rows" density="compact" variant="outlined" hide-details class="page-size" @update:model-value="load()" /><VPagination :model-value="page" :length="result.last_page" :total-visible="4" density="compact" :disabled="loading || dirty" @update:model-value="load" /></div></footer>
    </VCard>
    <p class="workspace-note"><VIcon icon="bx-info-circle" size="15" /> One row per subscription. Totals cover all filtered pages. Status reflects current account status; the date applies to balances.</p>

    <VDialog v-model="printDialog" max-width="510" :persistent="printing"><VCard class="print-card"><VCardTitle class="pt-6 px-6">Prepare print report</VCardTitle><VCardText class="px-6"><p class="mb-5">Review the report scope before generating your PDF.</p><VAlert v-if="reportError" type="error" variant="tonal" class="mb-4">{{ reportError }}</VAlert><dl class="detail-list"><div><dt>Report</dt><dd>{{ reportTitle }} accounts</dd></div><div><dt>Balance date</dt><dd>{{ displayDate(applied?.date) }}</dd></div><div><dt>Accounts</dt><dd>{{ result.total }} across all pages</dd></div><div><dt>Search</dt><dd>{{ applied?.search || 'No search filter' }}</dd></div><div><dt>Order</dt><dd>{{ sortLabel }} / {{ applied?.sort_dir === 'desc' ? 'Descending' : 'Ascending' }}</dd></div></dl><p class="text-caption mt-5 mb-0">The PDF includes all report columns, regardless of table visibility. Balances are refreshed when generated. Open the PDF and use its Print button.</p></VCardText><VCardActions class="px-6 pb-5"><VSpacer /><VBtn :disabled="printing" @click="printDialog = false">Cancel</VBtn><VBtn color="primary" variant="flat" :loading="printing" :disabled="dirty || loading || printing" prepend-icon="bxs-file-pdf" @click="printReport">Generate PDF</VBtn></VCardActions></VCard></VDialog>

    <VDialog v-model="detailDialog" max-width="620"><VCard v-if="selected"><VCardTitle class="pt-6 px-6">Account overview</VCardTitle><VCardText class="px-6"><h2 class="text-h5 mb-1">{{ selected.name }}</h2><p class="text-medium-emphasis mb-5">Subscriber #{{ selected.subscriber_id }} / Subscription #{{ selected.subscription_id }}</p><div class="detail-balances"><div><span>Overdue</span><strong class="overdue-value">{{ peso(selected.overdue) }}</strong></div><div><span>Balance</span><strong>{{ peso(selected.balance) }}</strong></div></div><dl class="detail-list mt-5"><div><dt>Status</dt><dd>{{ selected.active ? 'Active' : 'Disconnected' }}</dd></div><div><dt>Plan</dt><dd>{{ selected.plan || 'Not recorded' }}</dd></div><div><dt>Phone</dt><dd><a v-if="selected.phone" :href="`tel:${selected.phone.replace(/[^+0-9]/g, '')}`">{{ selected.phone }}</a><span v-else>Not recorded</span></dd></div><div><dt>Address</dt><dd>{{ selected.address || 'Not recorded' }}</dd></div><div><dt>Due date</dt><dd>{{ displayDate(selected.due_date) }}</dd></div><div><dt>Disconnected</dt><dd>{{ displayDate(selected.disconnected_at) }}</dd></div></dl></VCardText><VCardActions class="px-6 pb-5"><VSpacer /><VBtn variant="tonal" @click="detailDialog = false">Close</VBtn></VCardActions></VCard></VDialog>
  </section>
</template>

<style scoped>
.account-workspace { --line: rgba(var(--v-theme-on-surface), .1); --muted: rgba(var(--v-theme-on-surface), .62); max-width: 1680px; margin: auto; }
.workspace-header { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 24px; }
.eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .12em; color: var(--muted); margin-bottom: 8px; }
h1 { font-size: clamp(25px, 3vw, 32px); line-height: 1.25; letter-spacing: -.6px; font-weight: 650; }
.subtitle { margin: 8px 0 0; color: var(--muted); font-size: 14px; }
.header-actions, .table-tools, .pagination-controls { display: flex; align-items: center; gap: 10px; }
.report-tabs { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--line); margin-bottom: 24px; }
.updated-time { font-size: 12px; color: var(--muted); white-space: nowrap; display: flex; align-items: center; gap: 7px; }
.live-dot { width: 6px; height: 6px; background: rgb(var(--v-theme-success)); border-radius: 50%; }
.metric-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; margin-bottom: 24px; }
.metric-card { border: 1px solid var(--line); border-radius: 12px; padding: 22px 24px; background: rgb(var(--v-theme-surface)); }
.metric-top { display: flex; justify-content: space-between; color: var(--muted); font-size: 13px; font-weight: 500; margin-bottom: 12px; }
.metric-card strong { display: block; font-size: clamp(22px, 2.5vw, 30px); letter-spacing: -.5px; font-variant-numeric: tabular-nums; line-height: 1.25; overflow-wrap: anywhere; }
.metric-note { display: block; margin-top: 8px; color: var(--muted); font-size: 12px; }
.overdue-metric { border-top: 3px solid rgb(var(--v-theme-error)); padding-top: 20px; }
.overdue-metric strong, .overdue-value { color: rgb(var(--v-theme-error)); }
.results-card { border: 1px solid var(--line); border-radius: 12px !important; overflow: hidden; }
.filter-bar { display: flex; align-items: center; gap: 12px; padding: 22px; }
.search-control { min-width: 220px; flex: 1; }
.date-control { max-width: 205px; min-width: 170px; }
.pending-banner { display: flex; align-items: center; gap: 9px; padding: 10px 22px; font-size: 12px; background: rgba(var(--v-theme-warning), .1); }
.table-toolbar { padding: 18px 22px; display: flex; justify-content: space-between; align-items: center; gap: 16px; border-top: 1px solid var(--line); }
.table-toolbar h2 { font-size: 15px; font-weight: 650; display: flex; align-items: center; gap: 8px; }
.table-toolbar p { color: var(--muted); font-size: 12px; margin: 5px 0 0; }
.count-badge { font-size: 11px; padding: 2px 7px; border-radius: 5px; background: rgba(var(--v-theme-on-surface), .06); font-variant-numeric: tabular-nums; }
.table-region { position: relative; }
.table-progress { position: absolute; top: 0; left: 0; z-index: 5; }
.accounts-table :deep(th) { background: rgb(var(--v-theme-surface)) !important; border-top: 1px solid var(--line); font-size: 11px; letter-spacing: .02em; }
.accounts-table :deep(td) { font-size: 13px; padding-block: 10px; }
.accounts-table :deep(tbody tr:hover) { background: rgba(var(--v-theme-primary), .035); }
.sort-button { display: inline-flex; align-items: center; gap: 6px; color: inherit; font: inherit; white-space: nowrap; cursor: pointer; min-height: 40px; }
.sort-button .v-icon { opacity: .4; }
.sort-button .sort-active { opacity: 1; color: rgb(var(--v-theme-primary)); }
button:focus-visible, a:focus-visible { outline: 2px solid rgb(var(--v-theme-primary)); outline-offset: 3px; border-radius: 3px; }
.money-cell { text-align: right !important; font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 600; }
.name-cell { min-width: 210px; }
.account-name { text-align: left; cursor: pointer; padding: 3px 0; }
.account-name strong { display: block; font-weight: 600; color: rgb(var(--v-theme-on-surface)); }
.account-name:hover strong { color: rgb(var(--v-theme-primary)); }
.account-name span { display: block; margin-top: 4px; font-size: 11px; color: var(--muted); }
.id-cell, .date-value { white-space: nowrap; font-variant-numeric: tabular-nums; }
.address-value { display: block; min-width: 160px; max-width: 270px; }
.status-pill { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 600; border-radius: 5px; padding: 4px 8px; white-space: nowrap; }
.status-pill > span { width: 5px; height: 5px; border-radius: 50%; background: currentColor; }
.is-active { color: rgb(var(--v-theme-success)); background: rgba(var(--v-theme-success), .1); }
.is-disconnected { color: rgb(var(--v-theme-error)); background: rgba(var(--v-theme-error), .08); }
.refreshing-row { opacity: .45; }
.table-footer { padding: 14px 20px; border-top: 1px solid var(--line); display: flex; align-items: center; justify-content: space-between; gap: 16px; }
.range-label { color: var(--muted); font-size: 12px; }
.page-size { min-width: 86px; max-width: 100px; }
.workspace-note { display: flex; align-items: flex-start; gap: 7px; color: var(--muted); font-size: 11px; margin: 16px 0; line-height: 1.6; }
.workspace-note .v-icon { margin-top: 2px; }
.empty-state { text-align: center; padding: 48px 20px; color: var(--muted); }
.empty-state h3 { font-size: 16px; margin: 14px 0 6px; color: rgb(var(--v-theme-on-surface)); }
.empty-state p { font-size: 13px; margin-bottom: 18px; }
.skeleton-bar { display: block; height: 12px; min-width: 48px; width: 80%; border-radius: 4px; background: rgba(var(--v-theme-on-surface), .08); }
.detail-list { margin: 0; }
.detail-list > div { display: grid; grid-template-columns: 130px 1fr; gap: 16px; border-bottom: 1px solid rgba(var(--v-theme-on-surface), .1); padding: 12px 0; font-size: 13px; }
.detail-list dt { color: rgba(var(--v-theme-on-surface), .62); }
.detail-list dd { margin: 0; overflow-wrap: anywhere; }
.detail-balances { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 18px; background: rgba(var(--v-theme-on-surface), .035); border-radius: 8px; }
.detail-balances span { display: block; font-size: 12px; margin-bottom: 5px; }
.detail-balances strong { font-size: 22px; font-variant-numeric: tabular-nums; }
.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
@media (max-width: 960px) { .workspace-header { align-items: flex-start; flex-direction: column; gap: 16px; } .updated-time { display: none; } .filter-bar { flex-wrap: wrap; } .search-control { flex-basis: calc(100% - 235px); } .table-toolbar { align-items: flex-start; flex-wrap: wrap; } }
@media (max-width: 600px) { .metric-grid { grid-template-columns: 1fr; gap: 10px; } .metric-card { padding: 16px 18px; } .metric-top { margin-bottom: 6px; } .metric-card strong { font-size: 26px; } .header-actions { width: 100%; } .header-actions .v-btn { flex: 1; } .filter-bar { padding: 16px; } .search-control { flex-basis: 100%; min-width: 0; } .date-control { flex: 1; max-width: none; } .table-footer { flex-direction: column; align-items: stretch; padding: 12px; } .pagination-controls { justify-content: space-between; gap: 4px; } .table-toolbar { padding: 16px; } .table-tools { width: 100%; justify-content: space-between; } .detail-list > div { grid-template-columns: 100px 1fr; gap: 10px; } }
</style>
