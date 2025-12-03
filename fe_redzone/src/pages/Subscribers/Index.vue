<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useToastStore } from '@/stores/toast'

const subscribers = ref([])
const dialog = ref(false)
const editId = ref(null)
const form = ref({ name: '', email: '', phone: '', address: '' })
const loading = ref(false)
const toast = useToastStore()


const fetchSubscribers = async () => {
  loading.value = true
  const { data } = await axios.get('/api/subscribers')
  subscribers.value = data
  loading.value = false
}

const saveSubscriber = async () => {
  try {
    if (editId.value) {
      await axios.put(`/api/subscribers/${editId.value}`, form.value)
      toast.show('Subscriber updated successfully!', 'success')

    } else {
      await axios.post('/api/subscribers', form.value)
      toast.show('Subscriber created successfully!', 'success')
    }
    dialog.value = false
    resetForm()
    fetchSubscribers()
  } catch (err) {
    toast.show('Error saving subscriber', 'error')
  }
}

const editSubscriber = (subscriber) => {
  form.value = { ...subscriber }
  editId.value = subscriber.id
  dialog.value = true
}

const deleteSubscriber = async (id) => {
  if (confirm('Are you sure you want to delete this subscriber?')) {
    await axios.delete(`/api/subscribers/${id}`)
    fetchSubscribers()
  }
}

const resetForm = () => {
  form.value = { name: '', email: '', phone: '', address: '' }
  editId.value = null
}

onMounted(fetchSubscribers)
</script>

<template>
  <VCard class="p-6">
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Subscribers</span>
      <VBtn color="primary" @click="dialog = true">Add Subscriber</VBtn>
    </VCardTitle>

    <VCardText>
      <div v-if="loading" class="text-center py-10">Loading...</div>
      <VTable v-else fixed-header height="400px">
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
          <tr v-for="(subscriber, index) in subscribers" :key="subscriber.id">
            <td>{{ index + 1 }}</td>
            <td>{{ subscriber.name }}</td>
            <td>{{ subscriber.email }}</td>
            <td>{{ subscriber.phone }}</td>
            <td>{{ subscriber.address }}</td>
            <td>
              <VBtn color="warning" size="small" class="mr-2" @click="editSubscriber(subscriber)">Edit</VBtn>
              <VBtn color="error" size="small" @click="deleteSubscriber(subscriber.id)">Delete</VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>
    </VCardText>
  </VCard>

  <!-- Dialog -->
  <VDialog v-model="dialog" max-width="500px">
    <VCard>
      <VCardTitle>{{ editId ? 'Edit Subscriber' : 'Add Subscriber' }}</VCardTitle>
      <VCardText>
        <VTextField label="Name" v-model="form.name" outlined dense />
        <VTextField label="Email" v-model="form.email" outlined dense />
        <VTextField label="Phone" v-model="form.phone" outlined dense />
        <VTextField label="Address" v-model="form.address" outlined dense />
      </VCardText>
      <div class="d-flex justify-end pa-4">
        <VBtn color="grey" variant="text" @click="dialog = false">Cancel</VBtn>
        <VBtn color="primary" variant="flat" @click="saveSubscriber">
          {{ editId ? 'Update' : 'Save' }}
        </VBtn>
      </div>
    </VCard>
  </VDialog>
</template>
