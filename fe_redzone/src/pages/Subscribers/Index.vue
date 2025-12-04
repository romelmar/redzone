<script setup>
import { ref, onMounted } from "vue";
import {
  fetchSubscribers,
  createSubscriber,
  updateSubscriber,
  deleteSubscriber,
} from "@/services/subscribers";
import { useToastStore } from '@/stores/toast'
const toast = useToastStore()

const subscribers = ref([]);
const loading = ref(false);
const error = ref(null);
const dialog = ref(false);

const form = ref({
  id: null,
  name: "",
  email: "",
  phone: "",
  address: "",
});

const isEditing = ref(false);

const load = async () => {
  loading.value = true;
  try {
    const { data } = await fetchSubscribers();
    subscribers.value = data.data ?? data;
  } catch (e) {
    error.value = "Failed to load subscribers";
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  form.value = { id: null, name: "", email: "", phone: "", address: "" };
  isEditing.value = false;
  dialog.value = false;
};

const submit = async () => {
  try {
    if (isEditing.value) {
      await updateSubscriber(form.value.id, form.value);
      toast.show('Subscriber updated successfully!', 'success')
    } else {
      await createSubscriber(form.value);
      toast.show('Subscriber created successfully!', 'success')
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
  await deleteSubscriber(s.id);
  toast.show('Subscriber Deleted successfully!', 'success')
  await load();
};

onMounted(load);
</script>

<template>
  <VCard class="p-6">
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Subscribers</span>
      <VBtn color="primary" @click="openCreate">Add Subscriber</VBtn>
    </VCardTitle>

    <VCardText>
      <div v-if="loading" class="text-center py-10">Loading...</div>

      <VTable v-else fixed-header height="650px">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(s, index) in subscribers" :key="s.id">
            <td>{{ index + 1 }}</td>
            <td>{{ s.name }}</td>
            <td>{{ s.email }}</td>
            <td>{{ s.phone }}</td>
            <td>{{ s.address }}</td>

            <td class="d-flex mt-2">
              <VBtn color="warning" size="small"  class="mr-2" @click="edit(s)">Edit</VBtn>

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
        {{ isEditing ? "Edit Subscriber" : "Add Subscriber" }}
      </VCardTitle>

      <VCardText>
        <VTextField label="Name" v-model="form.name" outlined dense />
        <VTextField label="Email" v-model="form.email" outlined dense />
        <VTextField label="Phone" v-model="form.phone" outlined dense />
        <VTextField label="Address" v-model="form.address" outlined dense />
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
