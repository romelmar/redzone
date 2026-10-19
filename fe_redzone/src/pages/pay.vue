<script setup>
import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
import axios from 'axios'
import { peso, apiError } from '@/helpers/operations'
import logo from '@images/logo.png'
// Fragment tokens are not sent in page URLs, referrers, or hosting access logs.
const token = window.location.hash.slice(1)
const api = axios.create({ baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000', withCredentials: false, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
const bill = ref(null)
const error = ref('')
const busy = ref(false)
const loading = ref(true)
const titles = { ready: 'Awaiting payment', creating: 'Preparing checkout', pending: 'Awaiting confirmation', paid: 'Payment received', paid_review: 'Payment received', review: 'Staff review required', expired: 'Link expired' }
const paid = computed(() => !!bill.value?.payment_reference)
const amount = computed(() => bill.value?.status === 'pending' ? bill.value.checkout_amount_centavos : bill.value?.quote.amount_centavos)
let timer
let disposed = false
async function load() {
  try {
    if (!/^[a-f0-9]{64}$/.test(token)) throw new Error('Invalid payment link. Request a new link from Redzone.')
    const { data } = await api.get(`/api/public/payments/${token}`)
    if (!disposed) bill.value = data
  } catch (e) { if (!disposed) error.value = e.response ? apiError(e) : e.message }
  finally { loading.value = false }
}
async function checkout() {
  if (busy.value) return
  busy.value = true; error.value = ''
  try {
    const { data } = await api.post(`/api/public/payments/${token}/checkout`, { expected_amount_centavos: amount.value })
    const url = new URL(data.checkout_url)
    if (url.protocol !== 'https:' || url.hostname !== 'checkout.paymongo.com') throw new Error('Invalid checkout URL.')
    window.location.assign(url.href)
  } catch (e) { error.value = e.response ? apiError(e) : e.message; await load() }
  finally { busy.value = false }
}
async function refresh() {
  if (disposed) return
  // Provider calls are recovery only. Webhooks and the scheduled command update closed pages.
  if (bill.value?.status === 'pending') {
    try { await api.post(`/api/public/payments/${token}/sync`) } catch { /* Keep confirmation pending; never infer success. */ }
  }
  await load()
  if (!disposed && ['ready', 'creating', 'pending'].includes(bill.value?.status)) timer = setTimeout(refresh, 20000)
}
onMounted(() => {
  document.title = 'Pay your bill | Redzone'
  const meta = document.createElement('meta'); meta.name = 'referrer'; meta.content = 'no-referrer'; document.head.appendChild(meta)
  const robots = document.createElement('meta'); robots.name = 'robots'; robots.content = 'noindex,nofollow'; document.head.appendChild(robots)
  refresh()
})
onBeforeUnmount(() => { disposed = true; clearTimeout(timer) })
function download() {
  const text = `REDZONE PAYMENT ACKNOWLEDGEMENT\n${bill.value.mode === 'test' ? 'TEST MODE — no real money collected\n' : ''}Reference: ${bill.value.payment_reference}\nRequest: ${bill.value.reference}\nReceived: ${peso(bill.value.paid_amount_centavos / 100)}\nThis acknowledgement is not a tax invoice.\n`
  const url = URL.createObjectURL(new Blob([text], { type: 'text/plain' }))
  const a = document.createElement('a'); a.href = url; a.download = `${bill.value.payment_reference}.txt`; a.click()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
</script>
<template>
  <main class="bill-page"><header><img :src="logo" alt="Redzone" width="120" /><span>Subscriber payment services</span></header>
    <VCard class="bill-card"><VCardText>
      <VProgressLinear v-if="loading" indeterminate aria-label="Loading your bill" />
      <VAlert v-if="error" type="error" variant="tonal" class="mb-4">{{ error }} <VBtn size="small" variant="text" @click="load">Refresh</VBtn></VAlert>
      <template v-if="bill">
        <VAlert v-if="bill.mode === 'test'" type="warning" variant="tonal" class="mb-5">TEST MODE — no real money is collected. Test accounts only.</VAlert>
        <div class="d-flex justify-space-between flex-wrap gap-3 mb-5"><h1 class="text-h4">Your Redzone bill</h1><VChip :color="paid ? 'success' : 'info'">{{ titles[bill.status] || bill.status }}</VChip></div>
        <p class="text-caption">{{ bill.reference }}</p>
        <dl class="bill-lines"><div><dt>Subscriber</dt><dd>{{ bill.subscriber }}</dd></div><div><dt>Plan</dt><dd>{{ bill.quote.plan }}</dd></div><div><dt>Current billing month</dt><dd>{{ bill.quote.billing_month.slice(0, 7) }}</dd></div><div><dt>Previous balance</dt><dd>{{ peso(bill.quote.previous_balance) }}</dd></div><div><dt>Monthly fee less discount</dt><dd>{{ peso(bill.quote.monthly_fee - bill.quote.discount) }}</dd></div><div><dt>Add-ons</dt><dd>{{ peso(bill.quote.addons) }}</dd></div><div><dt>Service credits</dt><dd>{{ peso(bill.quote.credits) }}</dd></div><div><dt>Payments this month</dt><dd>{{ peso(bill.quote.payments) }}</dd></div><div><dt>Due date</dt><dd>{{ bill.quote.due_date }}</dd></div></dl>
        <div class="bill-total"><span>Current outstanding balance</span><strong>{{ peso(bill.quote.amount_centavos / 100) }}</strong></div>
        <template v-if="paid"><VAlert type="success" variant="tonal" class="mb-4">Payment of {{ peso(bill.paid_amount_centavos / 100) }} received. Reference: {{ bill.payment_reference }}.</VAlert><p v-if="bill.status === 'paid_review'">Redzone will review any excess credit or service reconnection. Contact support for assistance.</p><VBtn block variant="outlined" @click="download">Download acknowledgement</VBtn></template>
        <template v-else>
          <VAlert v-if="bill.status === 'review'" type="warning" variant="tonal" class="mb-4">Please contact Redzone to verify this payment. Wait for verification before making another payment.</VAlert>
          <p v-if="bill.status === 'pending'">Waiting for verified payment confirmation. Resume the same checkout below. Its amount is {{ peso(bill.checkout_amount_centavos / 100) }} for {{ bill.checkout_month }}; the current balance above may have changed.</p>
          <p v-if="bill.status === 'creating'">Preparing your checkout. This page will refresh automatically.</p>
          <p v-if="bill.status === 'expired'">This link has expired. Request a new payment link from Redzone.</p>
          <p v-if="bill.status === 'ready' && !bill.can_pay">There is no payable balance or online payments are currently unavailable. Contact Redzone for assistance.</p>
          <VBtn v-if="bill.can_pay" block size="large" :loading="busy" :disabled="busy" @click="checkout">{{ bill.status === 'pending' ? 'Resume secure checkout' : `Pay ${peso(amount / 100)} with GCash` }}</VBtn>
        </template>
        <p class="text-caption mt-5 mb-0">Payment status updates automatically after verification. Keep this private link secure.</p>
      </template>
    </VCardText></VCard><footer>REDZONE · Billing &amp; account services</footer>
  </main>
</template>
<style scoped>
.bill-page { max-width: 700px; margin: auto; padding: 32px 16px; }
header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-block-end: 24px; }
header span, footer { font-size: 13px; color: rgb(var(--v-theme-on-surface), .65); }
.bill-card { border-radius: 16px; }
.bill-lines { margin-block: 24px; }
.bill-lines div { display: flex; justify-content: space-between; gap: 16px; padding-block: 8px; }
.bill-lines dd { margin: 0; text-align: end; }
.bill-total { padding: 20px 0; border-block-start: 1px solid #ddd; margin-block-end: 20px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
.bill-total strong { font-size: 30px; }
footer { margin-block-start: 24px; text-align: center; }
</style>
