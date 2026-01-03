<script setup>
import { ref, onMounted } from "vue";
import {
  fetchAddons,
  createAddon,
  updateAddon,
  deleteAddon,
} from "@/services/addons";

import axios from 'axios'

import { useToastStore } from "@/stores/toast";
const toast = useToastStore();

// -------------------- STATE ----------------------
const addons = ref([]);
const subscriptions = ref([])
const plans = ref([])

const loading = ref(false);
const error = ref(null);
const dialog = ref(false);
const isEditing = ref(false);

const form = ref({
  id: null,
  subscription_id: "",
  name: "",
  amount: "",
  credit_month: "", // If needed
});

// -------------------- LOAD DATA ----------------------
const load = async () => {
  loading.value = true;
  try {
    const { data } = await fetchAddons();
    addons.value = data.data ?? data;
  } catch (e) {
    error.value = "Failed to load addons";
  } finally {
    loading.value = false;
  }
};

const loadDropdowns = async () => {
  subscriptions.value = (await axios.get('/api/subscriptions')).data
  plans.value = (await axios.get('/api/plans')).data
}


// -------------------- RESET FORM ----------------------
const resetForm = () => {
  form.value = {
    id: null,
    subscription_id: "",
    name: "",
    amount: "",
    credit_month: "",
  };
  isEditing.value = false;
  dialog.value = false;
};

// -------------------- SUBMIT FORM ----------------------
const submit = async () => {
  try {
    if (isEditing.value) {
      await updateAddon(form.value.id, form.value);
      toast.show("Addon updated successfully!", "success");
    } else {
      await createAddon(form.value);
      toast.show("Addon created successfully!", "success");
    }

    resetForm();
    await load();
  } catch (e) {
    error.value = "Failed to save addon";
  }
};

// -------------------- EDIT ITEM ----------------------
const edit = (addon) => {
  form.value = { ...addon };
  isEditing.value = true;
  dialog.value = true;
};

// -------------------- DELETE ITEM ----------------------
const remove = async (addon) => {
  if (!confirm(`Delete addon ${addon.name}?`)) return;

  await deleteAddon(addon.id);
  toast.show("Addon deleted successfully!", "success");

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
      <span>Addons</span>
      <VBtn color="primary" @click="openCreate">Add Addon</VBtn>
    </VCardTitle>

    <VCardText>
      <div v-if="loading" class="text-center py-10">Loading...</div>

      <VTable v-else fixed-header height="650px">
        <thead>
          <tr>
            <th>Subscription</th>
            <th>Name</th>
            <th>Amount</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="addon in addons" :key="addon.id">
            <td>
              {{ addon.subscription?.subscriber?.name ?? 'N/A' }}
            </td>

            <td>{{ addon.name }}</td>

            <td>
              ₱{{ Number(addon.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) }}
            </td>

            <td class="d-flex mt-2">
              <VBtn color="warning" size="small" class="mr-2" @click="edit(addon)">
                Edit
              </VBtn>

              <VBtn color="error" size="small" @click="remove(addon)">
                Delete
              </VBtn>
            </td>
          </tr>

          <tr v-if="!loading && addons.length === 0">
            <td colspan="4" class="text-center text-muted">No addons found</td>
          </tr>
        </tbody>

      </VTable>
    </VCardText>
  </VCard>

  <!-- DIALOG FORM -->
  <VDialog v-model="dialog" max-width="500px">
    <VCard>
      <VCardTitle>{{ isEditing ? "Edit Addon" : "Add Addon" }}</VCardTitle>

      <VCardText>
        <!-- <VTextField label="Subscription ID" v-model="form.subscription_id" outlined dense /> -->
        <VSelect
          label="Plan"
          :items="subscriptions.subscriber"
          item-title="name"
          item-value="id"
          v-model="form.subscription_id"
          outlined dense
        />


        <VTextField label="Addon Name" v-model="form.name" outlined dense />

        <VTextField label="Amount" type="number" v-model="form.amount" outlined dense />

        <VTextField v-if="form.credit_month !== undefined" label="Bill Month" type="date" v-model="form.credit_month"
          outlined dense />
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
