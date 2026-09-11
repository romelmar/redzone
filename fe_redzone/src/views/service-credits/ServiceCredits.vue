<script setup>
import { ref, onMounted } from 'vue'
import {
    fetchServiceCredits,
    createServiceCredit,
    updateServiceCredit,
    deleteServiceCredit,
} from '@/services/serviceCredits'
import { fetchSubscriptions } from '@/services/subscriptions'

const loading = ref(false)
const dialog = ref(false)

const credits = ref([])
const subscriptions = ref([])
const sortBy = ref('credit_month')
const sortDir = ref('desc')

const form = ref({
    id: null,
    subscription_id: null,
    credit_month: '',
    outage_days: 0,
    reason: '',
})

const load = async () => {
    loading.value = true
    const [credRes, subsRes] = await Promise.all([
        fetchServiceCredits({ sort_by: sortBy.value, sort_dir: sortDir.value }),
        fetchSubscriptions(),
    ])
    credits.value = credRes.data.data ?? credRes.data
    subscriptions.value = subsRes.data.data ?? subsRes.data
    loading.value = false
}

const openCreate = () => {
    form.value = {
        id: null,
        subscription_id: null,
        credit_month: new Date().toISOString().slice(0, 10).replace(/\d{2}$/, '01'),
        outage_days: 0,
        reason: '',
    }
    dialog.value = true
}

const openEdit = (c) => {
    form.value = { ...c }
    dialog.value = true
}

const save = async () => {
    if (form.value.id) {
        await updateServiceCredit(form.value.id, form.value)
    } else {
        await createServiceCredit(form.value)
    }
    dialog.value = false
    load()
}

const remove = async (c) => {
    if (!confirm(`Delete credit #${c.id}?`)) return
    await deleteServiceCredit(c.id)
    load()
}

const subscriptionLabel = (c) => {
    const sub = subscriptions.value.find(s => s.id === c.subscription_id)
    if (!sub) return '—'
    return `${sub.subscriber?.name ?? ''} - ${sub.plan?.name ?? ''}`
}

const setSort = (column) => {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = column
        sortDir.value = 'asc'
    }
    load()
}

const sortIcon = (column) => {
    if (sortBy.value !== column) return 'mdi-swap-vertical'
    return sortDir.value === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down'
}

onMounted(load)
</script>

<template>
    <div class="card">


        <VCardTitle class="d-flex justify-space-between align-center">
            <span>Service Credits (Outage)</span>
            <VBtn color="primary" @click="openCreate">Add Credit</VBtn>
        </VCardTitle>

        <div class="table-responsive text-nowrap">
            <VProgressLinear v-if="loading" indeterminate color="primary" aria-label="Loading records" />
      <VTable :aria-busy="loading">
                <thead>
                    <tr>
                        <th tabindex="0" @keydown.enter.prevent="setSort('subscription_name')" @keydown.space.prevent="setSort('subscription_name')" @click="setSort('subscription_name')" class="sortable-header">Subscription <VIcon size="16" class="ms-1">{{ sortIcon('subscription_name') }}</VIcon></th>
                        <th tabindex="0" @keydown.enter.prevent="setSort('credit_month')" @keydown.space.prevent="setSort('credit_month')" @click="setSort('credit_month')" class="sortable-header">Bill Month <VIcon size="16" class="ms-1">{{ sortIcon('credit_month') }}</VIcon></th>
                        <th tabindex="0" @keydown.enter.prevent="setSort('outage_days')" @keydown.space.prevent="setSort('outage_days')" @click="setSort('outage_days')" class="sortable-header">Outage Days <VIcon size="16" class="ms-1">{{ sortIcon('outage_days') }}</VIcon></th>
                        <th tabindex="0" @keydown.enter.prevent="setSort('reason')" @keydown.space.prevent="setSort('reason')" @click="setSort('reason')" class="sortable-header">Reason <VIcon size="16" class="ms-1">{{ sortIcon('reason') }}</VIcon></th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in credits" :key="c.id">
                        <td>{{ subscriptionLabel(c) }}</td>
                        <td>{{ c.credit_month }}</td>
                        <td>{{ c.outage_days }}</td>
                        <td>{{ c.reason }}</td>
                        <td class="text-end">
                            <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(c)">Edit</VBtn>
                            <VBtn size="small" color="error" variant="outlined" @click="remove(c)">Delete</VBtn>
                        </td>
                    </tr>
                    <tr v-if="!loading && credits.length === 0">
                        <td colspan="5" class="text-center text-muted py-4">No service credits match this view.</td>
                    </tr>
                </tbody>
            </VTable>
        </div>
    </div>

    <VDialog v-model="dialog" persistent max-width="650">
        <VCard>
            <VCardTitle>{{ form.id ? 'Edit Credit' : 'Add Credit' }}</VCardTitle>
            <VCardText>
                <VRow>
                    <VCol cols="12">
                        <VSelect v-model="form.subscription_id" :items="subscriptions" item-title="subscriber.name"
                            item-value="id" label="Subscription" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField label="Bill Month" type="date" v-model="form.credit_month" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField label="Outage Days" type="number" v-model.number="form.outage_days" min="0" />
                    </VCol>
                    <VCol cols="12">
                        <VTextarea label="Reason" rows="2" v-model="form.reason" />
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
