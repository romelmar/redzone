<script setup>
import { ref, onMounted } from 'vue'
import {
    fetchPlans,
    createPlan,
    updatePlan,
    deletePlan,
} from '@/services/plans'

const loading = ref(false)
const dialog = ref(false)
const plans = ref([])

const form = ref({
    id: null,
    name: '',
    price: null,
    description: '',
})

const load = async () => {
    loading.value = true
    const { data } = await fetchPlans()
    plans.value = data.data ?? data
    loading.value = false
}

const openCreate = () => {
    form.value = { id: null, name: '', price: null, description: '' }
    dialog.value = true
}

const openEdit = (plan) => {
    form.value = { ...plan }
    dialog.value = true
}

const save = async () => {
    if (form.value.id) {
        await updatePlan(form.value.id, form.value)
    } else {
        await createPlan(form.value)
    }
    dialog.value = false
    load()
}

const remove = async (plan) => {
    if (!confirm(`Delete plan ${plan.name}?`)) return
    await deletePlan(plan.id)
    load()
}

onMounted(load)
</script>

<template>
    <div class="card">

        <VCardTitle class="d-flex justify-space-between align-center">
            <span>Plans</span>
            <VBtn color="primary" @click="openCreate">Add Plans</VBtn>
        </VCardTitle>

        <div class="table-responsive text-nowrap">
            <VTable>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Description</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in plans" :key="p.id">
                        <td>{{ p.name }}</td>
                        <td>₱{{ Number(p.price).toFixed(2) }}</td>
                        <td>{{ p.description }}</td>
                        <td class="text-end">
                            <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(p)">Edit</VBtn>
                            <VBtn size="small" color="error" variant="outlined" @click="remove(p)">Delete</VBtn>
                        </td>
                    </tr>
                    <tr v-if="!loading && plans.length === 0">
                        <td colspan="5" class="text-center text-muted py-4">No plans found</td>
                    </tr>
                </tbody>
            </VTable>
        </div>
    </div>

    <!-- Dialog -->
    <VDialog v-model="dialog" persistent max-width="600">
        <VCard>
            <VCardTitle>{{ form.id ? 'Edit Plan' : 'Add Plan' }}</VCardTitle>
            <VCardText>
                <VRow>
                    <VCol cols="12" md="6">
                        <VTextField label="Name" v-model="form.name" />
                    </VCol>

                    <VCol cols="12" md="6">
                        <VTextField label="Price" v-model.number="form.price" type="number" />
                    </VCol>
                    <VCol cols="12">
                        <VTextarea label="Description" v-model="form.description" rows="2" />
                    </VCol>
                </VRow>
            </VCardText>
            <VCardActions>
                <VSpacer />
                <VBtn variant="tonal" @click="dialog = false">Cancel</VBtn>
                <VBtn color="primary" @click="save">Save</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>
