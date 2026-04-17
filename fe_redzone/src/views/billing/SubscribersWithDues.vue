<script setup>
import { ref, onMounted, watch } from "vue"
import debounce from "lodash/debounce"
import api from "@/plugins/axios"

const loading = ref(false)
const emailingId = ref(null)
const downloadingId = ref(null)

const dues = ref([])

const page = ref(1)
const perPage = ref(10)
const totalItems = ref(0)

const search = ref("")
const month = ref(new Date().toISOString().slice(0, 10).replace(/\d{2}$/, "01"))

const money = v => `₱${Number(v ?? 0).toFixed(2)}`

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get("/api/dues", {
      params: {
        page: page.value,
        per_page: perPage.value,
        search: search.value || undefined,
        month: month.value || undefined,
      },
    })

    dues.value = data?.data ?? []
    totalItems.value = data?.total ?? dues.value.length
  } finally {
    loading.value = false
  }
}

const debouncedLoad = debounce(() => {
  page.value = 1
  load()
}, 350)

watch(search, debouncedLoad)

watch(month, () => {
  page.value = 1
  load()
})

const downloadSOA = async (subscriptionId) => {
  downloadingId.value = subscriptionId
  try {
    const response = await api.get(`/api/subscriptions/${subscriptionId}/soa`, {
      params: {
        month: month.value || undefined,
      },
      responseType: "blob",
    })

    const blob = new Blob([response.data], { type: "application/pdf" })
    const url = window.URL.createObjectURL(blob)

    const a = document.createElement("a")
    a.href = url
    a.download = `SOA-${subscriptionId}.pdf`
    a.click()

    window.URL.revokeObjectURL(url)
  } catch (e) {
    alert("Failed to download SOA.")
  } finally {
    downloadingId.value = null
  }
}

const emailSOA = async (subscriptionId) => {
  emailingId.value = subscriptionId
  try {
    await api.post(`/api/subscriptions/${subscriptionId}/send-soa`, null, {
      params: {
        month: month.value || undefined,
      },
    })
    alert("SOA email sent successfully.")
  } catch (e) {
    alert("Failed to send SOA email.")
  } finally {
    emailingId.value = null
  }
}

onMounted(load)
</script>

<template>
  <div class="card">
    <div class="px-4 pt-4 d-flex flex-column flex-md-row gap-3 align-center justify-space-between mb-2">
      <div class="d-flex flex-wrap gap-3 align-center">
        <VTextField
          v-model="search"
          label="Search (subscriber, email, plan)"
          variant="outlined"
          density="comfortable"
          prepend-inner-icon="mdi-magnify"
          hide-details
          style="min-width: 260px"
        />

        <VTextField
          v-model="month"
          label="Billing Month"
          type="date"
          variant="outlined"
          density="comfortable"
          hide-details
          style="min-width: 190px"
        />
      </div>
    </div>

    <div class="table-responsive text-nowrap">
      <VTable>
        <thead>
          <tr>
            <th>Subscriber</th>
            <th>Plan</th>
            <th>Billing Period</th>
            <th>Previous Balance</th>
            <th>Monthly Fee</th>
            <th>Add-ons</th>
            <th>Credits</th>
            <th>Payments</th>
            <th>Total Due</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="d in dues" :key="d.subscription_id">
            <td>
              <div class="fw-500">{{ d.subscriber }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ d.subscriber_email || "—" }}
              </div>
            </td>

            <td>
              <div class="fw-500">{{ d.plan }}</div>
              <div class="text-caption text-medium-emphasis" v-if="d.speed">
                {{ d.speed }} Mbps
              </div>
            </td>

            <td>{{ d.billing_period }}</td>
            <td>{{ money(d.previous_balance) }}</td>
            <td>{{ money(d.monthly_fee) }}</td>
            <td>{{ money(d.addons_amount) }}</td>
            <td>-{{ money(d.credits_amount) }}</td>
            <td>-{{ money(d.payments_amount) }}</td>
            <td>
              <strong>{{ money(d.total_due) }}</strong>
            </td>

            <td class="text-end">
              <VBtn
                size="small"
                color="primary"
                class="me-2"
                :loading="downloadingId === d.subscription_id"
                @click="downloadSOA(d.subscription_id)"
              >
                Download SOA
              </VBtn>

              <VBtn
                size="small"
                color="success"
                variant="outlined"
                :loading="emailingId === d.subscription_id"
                @click="emailSOA(d.subscription_id)"
              >
                Email SOA
              </VBtn>
            </td>
          </tr>

          <tr v-if="!loading && dues.length === 0">
            <td colspan="10" class="text-center text-muted py-4">
              No dues found
            </td>
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