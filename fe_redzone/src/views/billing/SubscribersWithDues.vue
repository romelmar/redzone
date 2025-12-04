<script setup>
import { ref, onMounted } from "vue"
import api from "@/plugins/axios"
import { useRouter } from "vue-router"

const loading = ref(false)
const dues = ref([])

const router = useRouter()

const load = async () => {
  loading.value = true
  const res = await api.get("/api/dues")
  dues.value = res.data.data ?? res.data
  loading.value = false
}

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
    <div class="card-header">
      <h5 class="mb-0">Subscribers With Dues</h5>
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
            <td>{{ d.subscriber }}</td>
            <td>{{ d.plan }}</td>
            <td>{{ d.billing_period }}</td>
            <td>₱{{ d.base_amount.toFixed(2) }}</td>
            <td>₱{{ d.addons_amount.toFixed(2) }}</td>
            <td>-₱{{ d.credits_amount.toFixed(2) }}</td>
            <td><strong>₱{{ d.total_due.toFixed(2) }}</strong></td>

            <td class="text-end">
              <VBtn
                size="small"
                color="primary"
                class="me-2"
                @click="downloadSOA(d.subscription_id)"
              >
                Download SOA
              </VBtn>

              <VBtn
                size="small"
                color="success"
                variant="outlined"
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
