<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";

// State
const subscriptions = ref([]);
const search = ref("");
const loading = ref(true);

// Fetch subscriptions with subscriber + plan eager loaded
const fetchSubscriptions = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get("/api/subscriptions"); 
    subscriptions.value = data;
  } catch (error) {
    console.error("Error fetching subscriptions:", error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchSubscriptions);

// Filtering
const filtered = computed(() => {
  if (!search.value) return subscriptions.value;
  const q = search.value.toLowerCase();
  return subscriptions.value.filter((s) =>
    (s.subscriber?.name || "").toLowerCase().includes(q) ||
    (s.plan?.name || "").toLowerCase().includes(q) ||
    String(s.id).includes(q)
  );
});

// Download SOA
const downloadSoa = async (id) => {
  try {
    const res = await axios.get(`/subscriptions/${id}/soa`, {
      responseType: "blob",
    });

    const url = URL.createObjectURL(new Blob([res.data]));
    const a = document.createElement("a");
    a.href = url;
    a.download = `Statement-${id}.pdf`;
    a.click();
    URL.revokeObjectURL(url);
  } catch (error) {
    console.error("Error downloading SOA:", error);
  }
};
</script>

<template>
  <div class="p-6">

    <!-- 🔍 Search Box -->
    <div class="mb-4">
      <input
        v-model="search"
        type="text"
        placeholder="Search by ID, Subscriber, or Plan..."
        class="border rounded px-3 py-2 w-1/3"
      />
    </div>

    <!-- Table -->
    <VTable height="600" fixed-header>
      <thead>
        <tr>
          <th class="border px-4 py-2">ID</th>
          <th class="border px-4 py-2">Subscriber</th>
          <th class="border px-4 py-2">Plan</th>
          <th class="border px-4 py-2">Status</th>
          <th class="border px-4 py-2">Next Billing</th>
          <th class="border px-4 py-2">Balance</th>
          <th class="border px-4 py-2">Action</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="subscription in filtered" :key="subscription.id">
          <td class="border px-4 py-2">{{ subscription.id }}</td>
          <td class="border px-4 py-2">
            {{ subscription.subscriber?.name || "N/A" }}
          </td>
          <td class="border px-4 py-2">
            {{ subscription.plan?.name || "N/A" }}
          </td>
          <td class="border px-4 py-2">{{ subscription.status }}</td>
          <td class="border px-4 py-2">{{ subscription.next_billing_date }}</td>
          <td class="border px-4 py-2">{{ subscription.balance }}</td>
          <td class="border px-4 py-2 text-center">
            <VBtn color="primary" @click="downloadSoa(subscription.id)">
              Download SOA
            </VBtn>
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Loading -->
    <div v-if="loading" class="mt-4 text-gray-500">Loading...</div>
  </div>
</template>
