<script setup>
import { ref, onMounted } from "vue"
import {
    fetchSubscribers,
    createSubscriber,
    updateSubscriber,
    deleteSubscriber,
    showSubscriber,
} from "@/services/subscribers"

const loading = ref(false)
const dialog = ref(false)

const subscribers = ref([])

const form = ref({
    id: null,
    name: "",
    email: "",
    phone: "",
    address: "",
})

// Load list
const load = async () => {
    loading.value = true
    const { data } = await fetchSubscribers()
    subscribers.value = data.data ?? data
    loading.value = false
}

watch(dialog, (isOpen) => {
  if (!isOpen) {
    form.value = {
      id: null,
      name: "",
      email: "",
      phone: "",
      address: "",
    }
  }
})


// Open create dialog
const openCreate = () => {
    form.value = {
        id: null,
        name: "",
        email: "",
        phone: "",
        address: "",
    }
    dialog.value = true
}

// Open edit dialog
const openEdit = async (id) => {
    const { data } = await showSubscriber(id)
    form.value = { ...data }
    dialog.value = true
}

// Save (create or update)
const save = async () => {
    if (form.value.id) {
        await updateSubscriber(form.value.id, form.value)
    } else {
        await createSubscriber(form.value)
    }
    dialog.value = false
    load()
}

// Delete
const remove = async (item) => {
    if (!confirm(`Delete subscriber "${item.name}"?`)) return
    await deleteSubscriber(item.id)
    load()
}

onMounted(load)
</script>

<template>
    <div class="card">
        <!-- Header -->
        <VCardTitle class="d-flex justify-space-between align-center">
            <span>Subscribers</span>
            <VBtn color="primary" @click="openCreate">Add Subscriber</VBtn>
        </VCardTitle>

        <!-- Table -->
        <div class="table-responsive text-nowrap">
            <VTable>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Subscriptions</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="s in subscribers" :key="s.id">
                        <td>{{ s.name }}</td>
                        <td>{{ s.email }}</td>
                        <td>{{ s.phone }}</td>
                        <td>{{ s.subscriptions_count }}</td>

                        <td class="text-end">
                            <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(s.id)">
                                Edit
                            </VBtn>

                            <VBtn size="small" color="error" variant="outlined" @click="remove(s)">
                                Delete
                            </VBtn>
                        </td>
                    </tr>

                    <tr v-if="!loading && subscribers.length === 0">
                        <td colspan="5" class="text-center text-muted py-4">
                            No subscribers found
                        </td>
                    </tr>
                </tbody>
            </VTable>
        </div>
    </div>

    <!-- Dialog Form -->
    <VDialog v-model="dialog" persistent max-width="600px">
        <VCard>
            <VCardTitle>
                <span class="text-h6">
                    {{ form.id ? "Edit Subscriber" : "New Subscriber" }}
                </span>
            </VCardTitle>

            <VCardText>
                <VRow>
                    <VCol cols="12">
                        <VTextField label="Name" v-model="form.name" />
                    </VCol>

                    <VCol cols="12">
                        <VTextField label="Email" type="email" v-model="form.email" />
                    </VCol>

                    <VCol cols="12">
                        <VTextField label="Phone" v-model="form.phone" />
                    </VCol>

                    <VCol cols="12">
                        <VTextarea label="Address" rows="2" v-model="form.address" />
                    </VCol>
                </VRow>
            </VCardText>

            <VCardActions>
                <VSpacer />

                <VBtn variant="tonal" @click="dialog = false">
                    Cancel
                </VBtn>

                <VBtn color="primary" @click="save">
                    Save
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>
