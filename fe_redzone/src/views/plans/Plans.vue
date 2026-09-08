<script setup>
import { ref, onMounted } from 'vue'
import {
    fetchPlans,
    createPlan,
    updatePlan,
    deletePlan,
} from '@/services/plans'

const loading = ref(false)
const saving = ref(false)
const formError = ref("")
const dialog = ref(false)
const plans = ref([])
const page = ref(1)
const perPage = ref(10)
const totalItems = ref(0)
const sortBy = ref('name')
const sortDir = ref('asc')

const form = ref({
    id: null,
    name: '',
    price: null,
    description: '',
})

const load = async () => {
    loading.value = true
    try {
    const { data } = await fetchPlans({
        page: page.value,
        per_page: perPage.value,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    })

    plans.value = data.data ?? []
    totalItems.value = data.total ?? plans.value.length
    } catch (error) {
        formError.value = error.response?.data?.message || "Unable to load records. Please try again."
    } finally {
        loading.value = false
    }
}

const setSort = (column) => {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = column
        sortDir.value = 'asc'
    }
    page.value = 1
    load()
}

const sortIcon = (column) => {
    if (sortBy.value !== column) return 'mdi-swap-vertical'
    return sortDir.value === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down'
}

const openCreate = () => {
  formError.value = ""
    form.value = { id: null, name: '', price: null, description: '' }
    dialog.value = true
}

const openEdit = (plan) => {
    form.value = { ...plan }
    dialog.value = true
}

watch(page, load)

const save = async () => {
  if (saving.value) return
  saving.value = true
  formError.value = ""
  try {
    if (form.value.id) {
        await updatePlan(form.value.id, form.value)
    } else {
        await createPlan(form.value)
    }
    dialog.value = false
    await load()

  } catch (error) {
    formError.value = Object.values(error.response?.data?.errors || {}).flat().join(" ") || error.response?.data?.message || "Unable to save. Please try again."
  } finally {
    saving.value = false
  }
};

const remove = async (plan) => {
    if (!confirm(`Delete plan ${plan.name}?`)) return
    await deletePlan(plan.id)
    load()
}

onMounted(load)
</script>

<template>
    <div class="card">
        <VAlert v-if="formError && !dialog" type="error" class="mb-4">{{ formError }}</VAlert>

        <VCardTitle class="d-flex justify-space-between align-center">
            <span>Plans</span>
            <VBtn color="primary" @click="openCreate">Add Plans</VBtn>
        </VCardTitle>

        <div class="table-responsive text-nowrap">
            <VTable>
                <thead>
                    <tr>
                        <th @click="setSort('name')" class="sortable-header">Name <VIcon size="16" class="ms-1">{{ sortIcon('name') }}</VIcon></th>
                        <th @click="setSort('price')" class="sortable-header">Price <VIcon size="16" class="ms-1">{{ sortIcon('price') }}</VIcon></th>
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

            <div class="d-flex flex-column flex-sm-row align-center justify-space-between px-4 py-3 gap-3 mt-3">
                <VPagination
                    v-model="page"
                    :length="Math.ceil(totalItems / perPage) || 1"
                    @update:modelValue="load"
                    :totalVisible="5"
                    rounded="lg"
                    variant="flat"
                    color="primary"
                    class="pagination-sneat"
                />

                <div class="d-flex align-center">
                    <span class="me-2 text-body-2">Rows per page:</span>
                    <VSelect
                        v-model="perPage"
                        :items="[10, 20, 50, 100]"
                        density="comfortable"
                        variant="outlined"
                        hide-details
                        class="sneat-rows-select"
                        style="max-width: 110px"
                        @update:modelValue="() => { page = 1; load() }"
                    />
                </div>
            </div>
        </div>
    </div>

    <!-- Dialog -->
    <VDialog v-model="dialog" persistent max-width="600">
        <VCard>
            <VCardTitle>{{ form.id ? 'Edit Plan' : 'Add Plan' }}</VCardTitle>
            <VCardText>
                <p v-if="form.id" class="mb-4">Price changes apply to existing subscriptions from next month.</p>
        <VAlert v-if="formError" type="error" class="mb-4">{{ formError }}</VAlert>
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
                <VBtn variant="tonal" :disabled="saving" @click="dialog = false">Cancel</VBtn>
                <VBtn color="primary" :loading="saving" :disabled="saving" @click="save">Save</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>
