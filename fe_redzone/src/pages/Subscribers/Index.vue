<script setup>
import { ref, computed, onMounted } from "vue";
import axios from "axios";

// State
const subscribers = ref([]);
const search = ref("");
const loading = ref(true);

// Fetch subscribers with subscriber + plan eager loaded
const fetchSubscribers = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/subscribers')
    
    subscribers.value = data;
  } catch (error) {
    console.error("Error fetching subscribers:", error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchSubscribers);

// Filtering
const filtered = computed(() => {
  if (!search.value) return subscribers.value;
  const q = search.value.toLowerCase();
  return subscribers.value.filter((s) =>
    (s.subscriber?.name || "").toLowerCase().includes(q) ||
    (s.subscriber?.email || "").toLowerCase().includes(q) ||
    String(s.id).includes(q)
  );
});

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
          <th class="border px-4 py-2">Email</th>
          <th class="border px-4 py-2">Phone</th>
          <th class="border px-4 py-2">Address</th>
          <th class="border px-4 py-2">Action</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="subscriber in filtered" :key="subscriber.id">
          <td class="border px-4 py-2">{{ subscriber.id }}</td>
          <td class="border px-4 py-2">
            {{ subscriber.name || "N/A" }}
          </td>

          <td class="border px-4 py-2">{{ subscriber?.email }}</td>
          <td class="border px-4 py-2">{{ subscriber?.phone }}</td>
          <td class="border px-4 py-2">{{ subscriber?.address }}</td>
          <td class="border px-4 py-2 text-center">
            <VBtn color="primary" @click="downloadSoa(subscriber.id)">
             Manage
            </VBtn>
          </td>
        </tr>
      </tbody>
    </VTable>

    <!-- Loading -->
    <div v-if="loading" class="mt-4 text-gray-500">Loading...</div>
  </div>
</template>
