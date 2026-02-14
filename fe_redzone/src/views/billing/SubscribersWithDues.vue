<script setup>
import { ref, onMounted, watch } from "vue"
import api from "@/plugins/axios"
import debounce from "lodash/debounce"

const loading = ref(false)
const dues = ref([])

const search = ref("")

const toMoney = (v) => Number(v ?? 0).toFixed(2)

const load = async () => {
  loading.value = true
  try {
    const res = await api.get("/api/dues", {
      params: {
        search: search.value || undefined,
      },
    })
    dues.value = res.data.data ?? res.data
  } finally {
    loading.value = false
  }
}

const debouncedLoad = debounce(() => {
  load()
}, 350)

watch(search, () => {
  debouncedLoad()
})

const downloadSOA = async (subscriptionId) => {
  try {
    const response = await api.get(`/api/subscriptions/${subscriptionId}/soa`, {
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
  }
}

const emailSOA = async (subscriptionId) => {
  try {
    await api.post(`/api/subscriptions/${subscriptionId}/send-soa`)
    alert("SOA email sent successfully.")
  } catch (e) {
    alert("Failed to send SOA email.")
  }
}

onMounted(load)
</script>

<template>
  <div class="card">
    <div class="card-header d-flex flex-column flex-md-row align-center justify-space-between gap-3 my-5">

      <VTextField
        v-model="search"
        label="Search (subscriber, email, plan)"
        variant="outlined"
        density="comfortable"
        prepend-inner-icon="mdi-magnify"
        clearable
        hide-details
        style="min-width: 320px"
      />
    </div>

    <div class="table-responsive text-nowrap">
      <VTable>
        <thead>
          <tr>
            <th>Subscriber</th>
            <th>Plan</th>
            <th>Billing Period</th>
            <th>Base</th>
            <th>Add-ons</th>
            <th>Credits</th>
            <th>Total Due</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="d in dues" :key="d.subscription_id">
            <td>
              <div class="fw-500">{{ d.subscriber }}</div>
              <div class="text-caption text-medium-emphasis">{{ d.subscriber_email }}</div>
            </td>

            <td>
              <div class="fw-500">{{ d.plan }}</div>
              <div class="text-caption text-medium-emphasis" v-if="d.speed">{{ d.speed }} Mbps</div>
            </td>

            <td>{{ d.billing_period }}</td>

            <td>₱{{ toMoney(d.monthly_fee) }}</td>
            <td>₱{{ toMoney(d.addons_amount) }}</td>
            <td>-₱{{ toMoney(d.credits_amount) }}</td>

            <td><strong>₱{{ toMoney(d.total_due) }}</strong></td>

            <td class="text-end">
              <VBtn
                size="small"
                color="primary"
                class="me-2"
                :loading="loading"
                @click="downloadSOA(d.subscription_id)"
              >
                Download SOA
              </VBtn>

              <VBtn
                size="small"
                color="success"
                variant="outlined"
                :loading="loading"
                @click="emailSOA(d.subscription_id)"
              >
                Email SOA
              </VBtn>
            </td>
          </tr>

          <tr v-if="!loading && dues.length === 0">
            <td colspan="8" class="text-center text-muted py-4">No dues</td>
          </tr>
        </tbody>
      </VTable>
    </div>
  </div>
</template>
