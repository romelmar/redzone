<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useToastStore } from '@/stores/toast'

const plans = ref([])
const dialog = ref(false)
const editId = ref(null)
const form = ref({ name: '', description: '', price: '' })
const loading = ref(false)
const toast = useToastStore()

const fetchPlans = async () => {
  loading.value = true
  const { data } = await axios.get('/api/plans')
  plans.value = data
  loading.value = false
}

const savePlan = async () => {
  try {
    if (editId.value) {
      await axios.put(`/api/plans/${editId.value}`, form.value)
      toast.show('Plan updated successfully!', 'success')
    } else {
      await axios.post('/api/plans', form.value)
      toast.show('Plan created successfully!', 'success')

    }
    dialog.value = false
    resetForm()
    fetchPlans()
  } catch (err) {
    alert('Error saving plan')
  }
}

const editPlan = (plan) => {
  form.value = { ...plan }
  editId.value = plan.id
  dialog.value = true
}

const deletePlan = async (id) => {
  if (confirm('Are you sure you want to delete this plan?')) {
    await axios.delete(`/api/plans/${id}`)
    fetchPlans()
    toast.show('Plan Deleted successfully!', 'success')
  }
}

const resetForm = () => {
  form.value = { name: '', description: '', price: '' }
  editId.value = null
}

onMounted(fetchPlans)
</script>

<template>
  <VCard class="p-6">
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Subscription Plans</span>
      <VBtn color="primary" @click="dialog = true">Add Plan</VBtn>
    </VCardTitle>

    <VCardText>
      <div v-if="loading" class="text-center py-10">Loading...</div>
      <VTable v-else fixed-header height="400px">
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
            <td>
              <VBtn color="warning" size="small" class="mr-2" @click="editPlan(plan)">Edit</VBtn>
              <VBtn color="error" size="small" @click="deletePlan(plan.id)">Delete</VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>

  <!-- Dialog -->
  <VDialog v-model="dialog" max-width="500px">
    <VCard>
      <VCardTitle>{{ editId ? 'Edit Plan' : 'Add Plan' }}</VCardTitle>
      <VCardText>
        <VTextField label="Name" v-model="form.name" outlined dense />
        <VTextField label="Description" v-model="form.description" outlined dense />
        <VTextField label="Price" type="number" v-model="form.price" outlined dense />
      </VCardText>
      <div class="d-flex justify-end pa-4">
        <VBtn color="grey" variant="text" @click="dialog = false">Cancel</VBtn>
        <VBtn color="primary" variant="flat" @click="savePlan">
          {{ editId ? 'Update' : 'Save' }}
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>
