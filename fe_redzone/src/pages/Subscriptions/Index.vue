<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { formatIsoToReadable } from '@/helpers/dateUtils';
import { useToastStore } from '@/stores/toast'

const subscriptions = ref([])
const subscribers = ref([])
const plans = ref([])

const dialog = ref(false)
const editId = ref(null)
const toast = useToastStore()

const rawDate = ref('');
const formattedDate = computed(() => {
  return formatIsoToReadable(rawDate.value);
});

const form = ref({
  subscriber_id: '',
  plan_id: '',
  start_date: '',
  monthly_discount: 0,
})

const load = async () => {
  const res = await axios.get('/api/subscriptions')
  subscriptions.value = res.data
}

const loadDropdowns = async () => {
  subscribers.value = (await axios.get('/api/subscribers')).data
  plans.value = (await axios.get('/api/plans')).data
}

const destroy = async (id) => {
  if (!confirm('Delete this subscription?')) return
  await axios.delete(`/api/subscriptions/${id}`)
  await load()
}

// -----------------------------------------------------------
// SAVE / UPDATE SUBSCRIPTION
// -----------------------------------------------------------
const saveSubscription = async () => {
  try {
    if (editId.value) {
      await axios.put(`/api/subscriptions/${editId.value}`, form.value)
       toast.show('Subscription updated successfully', 'success')
    } else {
      await axios.post('/api/subscriptions', form.value)
       toast.show('Subscription created successfully', 'success')
    }

    dialog.value = false
    resetForm()
    await load()
  } catch (err) {
    console.error(err)
     toast.show('Error saving subscription', 'error')
  }
}

const editSubscription = (sub) => {
  editId.value = sub.id
  form.value = {
    subscriber_id: sub.subscriber_id,
    plan_id: sub.plan_id,
    start_date: sub.start_date,
    monthly_discount: sub.monthly_discount ?? 0,
  }
  dialog.value = true
}

const resetForm = () => {
  form.value = {
    subscriber_id: '',
    plan_id: '',
    start_date: '',
    monthly_discount: 0,
  }
  editId.value = null
}

// -----------------------------------------------------------

onMounted(async () => {
  await load()
  await loadDropdowns()
})
</script>
<template>
  <VCard class="p-6">
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Subscriptions</span>
      <VBtn color="primary" @click="dialog = true">Add Subscription</VBtn>
    </VCardTitle>

    <VCardText>
      <VTable fixed-header height="400px">
        <thead>
          <tr>
            <th>#</th>
            <th>Subscriber</th>
            <th>Plan</th>
            <th>Start Date</th>
            <th>Discount</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody v-if="subscriptions.length">
          <tr v-for="(sub, index) in subscriptions" :key="sub.id">
            <td>{{ index + 1 }}</td>
            <td>{{ sub.subscriber.name }}</td>
            <td>{{ sub.plan.name }}</td>
            <td>{{ formatIsoToReadable(sub.start_date)  }}</td>
            <td>{{ sub.monthly_discount ?? 0 }}</td>
            <td>
              <VBtn
                size="small"
                color="info"
                class="me-2"
                @click="editSubscription(sub)"
              >
                Edit
              </VBtn>

              <VBtn size="small" color="red" @click="destroy(sub.id)">
                Delete
              </VBtn>
            </td>
          </tr>
        </tbody>

        <tbody v-else>
          <tr>
            <td colspan="6" class="text-center py-4">
              No subscriptions found.
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>

  <!-- Dialog -->
  <VDialog v-model="dialog" max-width="500px">
    <VCard>
      <VCardTitle>
        {{ editId ? 'Edit Subscription' : 'Add Subscription' }}
      </VCardTitle>

      <VCardText>
        <VSelect
          label="Subscriber"
          :items="subscribers"
          item-title="name"
          item-value="id"
          v-model="form.subscriber_id"
          outlined dense
        />

        <VSelect
          label="Plan"
          :items="plans"
          item-title="name"
          item-value="id"
          v-model="form.plan_id"
          outlined dense
        />

        <VTextField
          type="date"
          label="Start Date"
          v-model="form.start_date"
          outlined dense
        />

        <VTextField
          label="Monthly Discount"
          type="number"
          v-model="form.monthly_discount"
          outlined dense
        />
      </VCardText>

      <div class="d-flex justify-end pa-4">
        <VBtn color="grey" variant="text" @click="dialog = false">Cancel</VBtn>
        <VBtn color="primary" variant="flat" @click="saveSubscription">
          {{ editId ? 'Update' : 'Save' }}
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>
