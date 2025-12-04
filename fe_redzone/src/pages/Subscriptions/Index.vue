<script setup>
import { ref, onMounted } from 'vue'
import {
  fetchSubscriptions,
  createSubscription,
  updateSubscription,
  deleteSubscription,
} from "@/services/subscriptions";
import axios from 'axios'
import { formatIsoToReadable } from '@/helpers/dateUtils';
import { useToastStore } from '@/stores/toast'

const subscriptions = ref([])
const subscribers = ref([])
const plans = ref([])

const dialog = ref(false)
const editId = ref(null)
const loading = ref(false)
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
const isEditing = ref(false);
const load = async () => {
  loading.value = true;
  try {
    const { data } = await fetchSubscriptions();
    subscribers.value = data.data ?? data;
  } catch (e) {
    error.value = "Failed to load subscribers";
  } finally {
    loading.value = false;
  }
};


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
  isEditing.value = false;
  dialog.value = false;
}


const submit = async () => {
  try {
    if (isEditing.value) {
      await updateSubscription(form.value.id, form.value);
      toast.show('Subscription updated successfully!', 'success')
    } else {
      await createSubscription(form.value);
      toast.show('Subscription created successfully!', 'success')
    }

    resetForm();
    await load();
  } catch (e) {
    error.value = "Failed to save subscriber";
  }
};

const edit = (s) => {
  form.value = { ...s };
  isEditing.value = true;
  dialog.value = true;
};

const remove = async (s) => {
  if (!confirm(`Delete subscriber ${s.name}?`)) return;
  await deleteSubscription(s.id);
  toast.show('Subscription Deleted successfully!', 'success')
  await load();
};

onMounted(async () => {
  await load()
  await loadDropdowns()
})
</script>
<template>
  <VCard class="p-6">
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Subscriptions</span>
      <VBtn color="primary" @click="openCreate">Add Subscription</VBtn>
    </VCardTitle>

    <VCardText>
      <div v-if="loading" class="text-center py-10">Loading...</div>

      <VTable v-else fixed-header height="650px">
        <thead>
          <tr>
            <th>#</th>
            <th>Subscription</th>
            <th>Plan</th>
            <th>Start Date</th>
            <th>Discount</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(s, index) in subscribers" :key="s.id">
            <td>{{ index + 1 }}</td>
            <td>{{ s.subscriber.name }}</td>
            <td>{{ s.plan.name }}</td>
            <td>{{ formatIsoToReadable(s.start_date)  }}</td>
            <td>{{ s.monthly_discount ?? 0 }}</td>

            <td class="d-flex mt-2">
              <VBtn color="warning" size="small"   class="mr-2" @click="edit(s)">Edit</VBtn>

              <VBtn color="error" size="small" @click="remove(s)">
                Delete
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>

  <!-- DIALOG FORM -->
  <VDialog v-model="dialog" max-width="500px">
    <VCard>
      <VCardTitle>
        {{ isEditing ? "Edit Subscription" : "Add Subscription" }}
      </VCardTitle>

      <VCardText>
        <VSelect
          label="Subscription"
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
        <VTextField label="Monthly Discount" v-model="form.monthly_discount" outlined dense />
      </VCardText>

      <div class="d-flex justify-end pa-4 ga-2">
        <VBtn variant="text" @click="resetForm()">Cancel</VBtn>

        <VBtn color="primary" variant="flat" @click="submit">
          {{ isEditing ? "Update" : "Save" }}
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>
