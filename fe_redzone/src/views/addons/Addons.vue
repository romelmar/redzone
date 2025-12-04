<script setup>
import { ref, onMounted } from 'vue'
import {
    fetchAddons,
    createAddon,
    updateAddon,
    deleteAddon,
} from '@/services/addons'
import { fetchSubscriptions } from '@/services/subscriptions'
import { formatIsoToReadable } from '@/helpers/dateUtils'

const loading = ref(false)
const dialog = ref(false)

const addons = ref([])
const subscriptions = ref([])

const form = ref({
    id: null,
    subscription_id: null,
    name: '',
    amount: null,
    bill_month: '',
})

const load = async () => {
    loading.value = true
    const [addonsRes, subsRes] = await Promise.all([
        fetchAddons(),
        fetchSubscriptions(),
    ])
    addons.value = addonsRes.data.data ?? addonsRes.data
    subscriptions.value = subsRes.data.data ?? subsRes.data
    loading.value = false
}

const openCreate = () => {
    form.value = {
        id: null,
        subscription_id: null,
        name: '',
        amount: null,
        bill_month: new Date().toISOString().slice(0, 10).replace(/\d{2}$/, '01'),
    }
    dialog.value = true
}

const openEdit = (a) => {
    form.value = {
        id: a.id,
        subscription_id: a.subscription_id,
        name: a.name,
        amount: a.amount,
        bill_month: a.bill_month,
    }
    dialog.value = true
}

const save = async () => {
    if (form.value.id) {
        await updateAddon(form.value.id, form.value)
    } else {
        await createAddon(form.value)
    }
    dialog.value = false
    load()
}

const remove = async (a) => {
    if (!confirm(`Delete addon ${a.name}?`)) return
    await deleteAddon(a.id)
    load()
}

const subscriptionLabel = (a) => {
    const sub = subscriptions.value.find(s => s.id === a.subscription_id)
    if (!sub) return '—'
    return `${sub.subscriber?.name ?? ''} - ${sub.plan?.name ?? ''}`
}

onMounted(load)
</script>

<template>
    <div class="card">
        <VCardTitle class="d-flex justify-space-between align-center">
            <span>Add-ons</span>
            <VBtn color="primary" @click="openCreate">Add Add-ons</VBtn>
        </VCardTitle>

        <div class="table-responsive text-nowrap">
            <VTable>
                <thead>
                    <tr>
                        <th>Subscription</th>
                        <th>Name</th>
                        <th>Amount</th>
                        <th>Bill Month</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in addons" :key="a.id">
                        <td>{{ subscriptionLabel(a) }}</td>
                        <td>{{ a.name }}</td>
                        <td>₱{{ Number(a.amount).toFixed(2) }}</td>
                        <td>{{ formatIsoToReadable(a.bill_month) }}</td>
                        <td class="text-end">
                            <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(a)">Edit</VBtn>
                            <VBtn size="small" color="error" variant="outlined" @click="remove(a)">Delete</VBtn>
                        </td>
                    </tr>
                    <tr v-if="!loading && addons.length === 0">
                        <td colspan="5" class="text-center text-muted py-4">No add-ons found</td>
                    </tr>
                </tbody>
            </VTable>
        </div>
    </div>

    <VDialog v-model="dialog" persistent max-width="600">
        <VCard>
            <VCardTitle>{{ form.id ? 'Edit Add-on' : 'Add Add-on' }}</VCardTitle>
            <VCardText>
                <VRow>
                    <VCol cols="12">
                        <VSelect v-model="form.subscription_id" :items="subscriptions" item-title="subscriber.name"
                            item-value="id" label="Subscription (Subscriber)" />
                    </VCol>
                    <VCol cols="12">
                        <VTextField label="Name" v-model="form.name" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField label="Amount" type="number" v-model.number="form.amount" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField label="Bill Month" type="date" v-model="form.bill_month" />
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
