<script setup>
import { ref, onMounted } from "vue";
import {
  fetchPlans,
  createPlan,
  updatePlan,
  deletePlan,
} from "@/services/plans";
import { useToastStore } from '@/stores/toast'
const toast = useToastStore()

const plans = ref([]);
const loading = ref(false);
const error = ref(null);
const dialog = ref(false);

const form = ref({ name: '', description: '', price: '' })

const isEditing = ref(false);

const load = async () => {
  loading.value = true;
  try {
    const { data } = await fetchPlans();
    plans.value = data.data ?? data;
  } catch (e) {
    error.value = "Failed to load plans";
  } finally {
    loading.value = false;
  }
};

const resetForm = () => {
  form.value = { name: '', description: '', price: '' }
  isEditing.value = false;
  dialog.value = false;
};

const submit = async () => {
  try {
    if (isEditing.value) {
      await updatePlan(form.value.id, form.value);
      toast.show('Plan updated successfully!', 'success')
    } else {
      await createPlan(form.value);
      toast.show('Plan created successfully!', 'success')
    }

    resetForm();
    await load();
  } catch (e) {
    error.value = "Failed to save plan";
  }
};

const edit = (s) => {
  form.value = { ...s };
  isEditing.value = true;
  dialog.value = true;
};

const remove = async (s) => {
  if (!confirm(`Delete plan ${s.name}?`)) return;
  await deletePlan(s.id);
  toast.show('Plan Deleted successfully!', 'success')
  await load();
};

onMounted(load);
</script>

<template>
  <VCard class="p-6">
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Plans</span>
      <VBtn color="primary" @click="openCreate">Add Plan</VBtn>
    </VCardTitle>

    <VCardText>
      <div v-if="loading" class="text-center py-10">Loading...</div>

      <VTable v-else fixed-header height="650px">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Price (₱)</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="plan in plans" :key="plan.id">
            <td>{{ plan.name }}</td>
            <td>{{ plan.description }}</td>
            <td>{{ Number(plan.price).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}</td>

            <td class="d-flex mt-2">
              <VBtn color="warning" size="small"  class="mr-2" @click="edit(plan)">Edit</VBtn>

              <VBtn color="error" size="small" @click="remove(plan)">
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
        {{ isEditing ? "Edit Plan" : "Add Plan" }}
      </VCardTitle>

      <VCardText>
        <VTextField label="Name" v-model="form.name" outlined dense />
        <VTextField label="Description" v-model="form.description" outlined dense />
        <VTextField label="Price" type="number" v-model="form.price" outlined dense />
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
