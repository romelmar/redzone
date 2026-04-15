<script setup>
import { ref, onMounted, watch, computed } from "vue"
import debounce from "lodash/debounce"

import {
  fetchPayments,
  createPayment,
  updatePayment,
  deletePayment,
} from "@/services/payments"

import { fetchSubscriptionOptions } from "@/services/subscriptions"
import { formatIsoToReadable } from "@/helpers/dateUtils"

/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/
const loading = ref(false)
const loadingSubs = ref(false)
const dialog = ref(false)

const payments = ref([])
const subscriptions = ref([])

const page = ref(1)
const perPage = ref(10)
const totalItems = ref(0)

const search = ref("")
const paymentTypeFilter = ref(null)
const dateFrom = ref("")
const dateTo = ref("")

const subscriptionSearch = ref("")

const form = ref({
  id: null,
  subscription_id: null,
  amount: null,
  payment_date: "",
  payment_type: "",
  remarks: "",
})

/*
|--------------------------------------------------------------------------
| OPTIONS
|--------------------------------------------------------------------------
*/
const paymentTypeOptions = [
  { label: "All", value: null },
  { label: "Payment", value: "payment" },
  { label: "Offset", value: "offset" },
  { label: "Adjustment", value: "adjustment" },
]

const subscriptionOptions = computed(() => subscriptions.value)

const money = v => Number(v ?? 0).toFixed(2)

/*
|--------------------------------------------------------------------------
| LOAD PAYMENTS
|--------------------------------------------------------------------------
*/
const load = async () => {
  loading.value = true
  try {
    const payRes = await fetchPayments({
      page: page.value,
      per_page: perPage.value,
      search: search.value || undefined,
      payment_type: paymentTypeFilter.value || undefined,
      date_from: dateFrom.value || undefined,
      date_to: dateTo.value || undefined,
    })

    payments.value =
      payRes.data?.data?.data ??
      payRes.data?.data ??
      payRes.data ??
      []

    totalItems.value =
      payRes.data?.data?.total ??
      payRes.data?.total ??
      payments.value.length

  } finally {
    loading.value = false
  }
}

/*
|--------------------------------------------------------------------------
| LOAD SUBSCRIPTION OPTIONS (🔥 key improvement)
|--------------------------------------------------------------------------
*/
const loadSubscriptionOptions = async (searchValue = "") => {
  loadingSubs.value = true
  try {
    const res = await fetchSubscriptionOptions({
      search: searchValue || undefined,
      limit: 50,
    })

    subscriptions.value = res.data ?? []
  } finally {
    loadingSubs.value = false
  }
}

/*
|--------------------------------------------------------------------------
| DEBOUNCE
|--------------------------------------------------------------------------
*/
const debouncedLoad = debounce(() => {
  page.value = 1
  load()
}, 350)

const debouncedSubSearch = debounce((val) => {
  loadSubscriptionOptions(val)
}, 300)

/*
|--------------------------------------------------------------------------
| WATCHERS
|--------------------------------------------------------------------------
*/
watch(search, debouncedLoad)

watch([paymentTypeFilter, dateFrom, dateTo], () => {
  page.value = 1
  load()
})

watch(subscriptionSearch, debouncedSubSearch)

/*
|--------------------------------------------------------------------------
| CRUD
|--------------------------------------------------------------------------
*/
const openCreate = () => {
  form.value = {
    id: null,
    subscription_id: null,
    amount: null,
    payment_date: new Date().toISOString().slice(0, 10),
    payment_type: "payment",
    remarks: "",
  }

  dialog.value = true
}

const openEdit = async (p) => {
  form.value = {
    ...p,
    payment_type: p.payment_type ?? "",
    remarks: p.remarks ?? "",
  }

  dialog.value = true

  // 🔥 ensure selected subscription is loaded
  if (p.subscription_id) {
    await loadSubscriptionOptions()
  }
}

const save = async () => {
  if (!form.value.subscription_id) {
    alert("Please select a subscription.")
    return
  }

  if (!form.value.amount || Number(form.value.amount) <= 0) {
    alert("Please enter a valid amount.")
    return
  }

  if (!form.value.payment_date) {
    alert("Please select a payment date.")
    return
  }

  if (form.value.id) {
    await updatePayment(form.value.id, form.value)
  } else {
    await createPayment(form.value)
  }

  dialog.value = false
  load()
}

const remove = async (p) => {
  if (!confirm(`Delete payment #${p.id}?`)) return
  await deletePayment(p.id)
  load()
}

/*
|--------------------------------------------------------------------------
| INIT
|--------------------------------------------------------------------------
*/
onMounted(() => {
  load()
  loadSubscriptionOptions()
})
</script>

<template>
  <div class="card">
    <VCardTitle class="d-flex flex-column flex-md-row align-center justify-space-between gap-3">
      <span>Payments</span>

      <div class="d-flex flex-wrap gap-3 align-center">
        <VTextField
          v-model="search"
          label="Search subscription / remarks"
          variant="outlined"
          density="comfortable"
          prepend-inner-icon="mdi-magnify"
          clearable
          hide-details
          style="min-width: 260px"
        />

        <VSelect
          v-model="paymentTypeFilter"
          :items="paymentTypeOptions"
          item-title="label"
          item-value="value"
          label="Payment Type"
          variant="outlined"
          density="comfortable"
          clearable
          hide-details
          style="min-width: 180px"
        />

        <VTextField
          v-model="dateFrom"
          label="From"
          type="date"
          variant="outlined"
          density="comfortable"
          hide-details
          style="min-width: 160px"
        />

        <VTextField
          v-model="dateTo"
          label="To"
          type="date"
          variant="outlined"
          density="comfortable"
          hide-details
          style="min-width: 160px"
        />

        <VBtn color="primary" @click="openCreate">Add Payment</VBtn>
      </div>
    </VCardTitle>

    <div class="table-responsive text-nowrap">
      <VTable>
        <thead>
          <tr>
            <th>Subscription</th>
            <th>Amount</th>
            <th>Paid At</th>
            <th>Payment Type</th>
            <th>Remarks</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="p in payments" :key="p.id">
            <td>
              <div class="fw-500">{{ p.subscription?.subscriber?.name ?? "—" }} - 
{{ p.subscription?.plan?.name ?? "—" }}</div>
            </td>

            <td>₱{{ money(p.amount) }}</td>
            <td>{{ formatIsoToReadable(p.payment_date) }}</td>

            <td>
              <VChip
                size="small"
                :color="
                  p.payment_type === 'payment'
                    ? 'success'
                    : p.payment_type === 'offset'
                    ? 'warning'
                    : 'secondary'
                "
              >
                {{ p.payment_type || "—" }}
              </VChip>
            </td>

            <td>{{ p.remarks || "—" }}</td>

            <td class="text-end">
              <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(p)">
                Edit
              </VBtn>
              <VBtn size="small" color="error" variant="outlined" @click="remove(p)">
                Delete
              </VBtn>
            </td>
          </tr>

          <tr v-if="!loading && payments.length === 0">
            <td colspan="6" class="text-center text-muted py-4">No payments found</td>
          </tr>
        </tbody>
      </VTable>

      <div class="d-flex flex-column flex-sm-row align-center justify-space-between px-4 py-3 gap-3">
        <VPagination
          v-model="page"
          :length="Math.ceil(totalItems / perPage) || 1"
          @update:modelValue="load"
          rounded="lg"
          variant="flat"
          color="primary"
          class="pagination-sneat"
        />

        <div class="d-flex align-center">
          <span class="me-2 text-body-2">Rows per page:</span>

          <VSelect
            v-model="perPage"
            :items="[10, 20, 50, 100]"
            density="comfortable"
            variant="outlined"
            hide-details
            class="sneat-rows-select"
            style="max-width: 110px"
            @update:modelValue="() => { page = 1; load() }"
          />
        </div>
      </div>
    </div>
  </div>

  <VDialog v-model="dialog" persistent max-width="700">
    <VCard>
      <VCardTitle>{{ form.id ? "Edit Payment" : "Add Payment wwww" }}</VCardTitle>

      <VCardText>
        <VRow>
          <VCol cols="12">
            <VAutocomplete
  v-model="form.subscription_id"
  :items="subscriptionOptions"
  item-title="label"
  item-value="value"
  v-model:search="subscriptionSearch"
  :loading="loadingSubs"
  label="Subscription"
  variant="outlined"
  clearable
/>
          </VCol>

          <VCol cols="12" md="6">
            <VTextField
              label="Amount"
              type="number"
              v-model.number="form.amount"
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField
              label="Paid At"
              type="date"
              v-model="form.payment_date"
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VSelect
              label="Payment Type"
              v-model="form.payment_type"
              :items="['payment', 'offset', 'adjustment']"
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VTextarea
              label="Remarks"
              rows="2"
              v-model="form.remarks"
              variant="outlined"
            />
          </VCol>
        </VRow>
      </VCardText>

      <VCardActions>
        <VSpacer />
        <VBtn variant="tonal" @click="dialog = false">Cancel</VBtn>
        <VBtn color="primary" @click="save">Save</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.pagination-sneat .v-pagination__item,
.pagination-sneat .v-pagination__first,
.pagination-sneat .v-pagination__last,
.pagination-sneat .v-pagination__next,
.pagination-sneat .v-pagination__prev {
  border-radius: 8px !important;
  min-width: 38px !important;
  height: 38px !important;
  font-weight: 500;
  box-shadow: var(--v-shadow-2);
  transition: all 0.2s ease-in-out;
}

.pagination-sneat .v-pagination__item:hover {
  background-color: rgba(var(--v-theme-primary), 0.1) !important;
  transform: translateY(-2px);
}

.pagination-sneat .v-pagination__item--is-active {
  background-color: rgb(var(--v-theme-primary)) !important;
  color: white !important;
  box-shadow: var(--v-shadow-4);
}

.sneat-rows-select .v-field {
  border-radius: 8px !important;
}
</style>