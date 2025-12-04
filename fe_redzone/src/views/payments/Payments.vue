<script setup>
import { ref, onMounted } from 'vue'
import {
    fetchPayments,
    createPayment,
    updatePayment,
    deletePayment,
} from '@/services/payments'
import { fetchSubscriptions } from '@/services/subscriptions'
import { formatIsoToReadable } from '@/helpers/dateUtils'

const loading = ref(false)
const dialog = ref(false)

const payments = ref([])
const subscriptions = ref([])

const form = ref({
    id: null,
    subscription_id: null,
    amount: null,
    paid_at: '',
    reference: '',
    notes: '',
})

const load = async () => {
    loading.value = true
    const [payRes, subsRes] = await Promise.all([
        fetchPayments(),
        fetchSubscriptions(),
    ])
    payments.value = payRes.data.data ?? payRes.data
    subscriptions.value = subsRes.data.data ?? subsRes.data
    loading.value = false
}

const openCreate = () => {
    form.value = {
        id: null,
        subscription_id: null,
        amount: null,
        paid_at: new Date().toISOString().slice(0, 10),
        reference: '',
        notes: '',
    }
    dialog.value = true
}

const openEdit = (p) => {
    form.value = { ...p }
    dialog.value = true
}

const save = async () => {
    if (form.value.id) {
        await updatePayment(form.value.id, form.value)
    } else {
        await createPayment(form.value)
    }
    dialog.value = false
    load()
}

const remove = async (p) => {
    if (!confirm(`Delete payment #${p.id}?`)) return
    await deletePayment(p.id)
    load()
}

const subscriptionLabel = (p) => {
    const sub = subscriptions.value.find(s => s.id === p.subscription_id)
    if (!sub) return '—'
    return `${sub.subscriber?.name ?? ''} - ${sub.plan?.name ?? ''}`
}

onMounted(load)
</script>

<template>
    <div class="card">

        <VCardTitle class="d-flex justify-space-between align-center">
            <span>Payments</span>
            <VBtn color="primary" @click="openCreate">Add Payment</VBtn>
        </VCardTitle>

        <div class="table-responsive text-nowrap">
            <VTable>
                <thead>
                    <tr>
                        <th>Subscription</th>
                        <th>Amount</th>
                        <th>Paid At</th>
                        <th>Reference</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="p in payments" :key="p.id">
                        <td>{{ subscriptionLabel(p) }}</td>
                        <td>₱{{ Number(p.amount).toFixed(2) }}</td>
                        <td>{{ formatIsoToReadable(p.paid_at) }}</td>
                        <td>{{ p.reference }}</td>
                        <td>{{ p.notes }}</td>
                        <td class="text-end">
                            <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(p)">Edit</VBtn>
                            <VBtn size="small" color="error" variant="outlined" @click="remove(p)">Delete</VBtn>
                        </td>
                    </tr>
                    <tr v-if="!loading && payments.length === 0">
                        <td colspan="6" class="text-center text-muted py-4">No payments found</td>
                    </tr>
                </tbody>
            </VTable>
        </div>
    </div>

    <VDialog v-model="dialog" persistent max-width="700">
        <VCard>
            <VCardTitle>{{ form.id ? 'Edit Payment' : 'Add Payment' }}</VCardTitle>
            <VCardText>
                <VRow>
                    <VCol cols="12">
                        <VSelect v-model="form.subscription_id" :items="subscriptions" item-title="subscriber.name"
                            item-value="id" label="Subscription" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField label="Amount" type="number" v-model.number="form.amount" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField label="Paid At" type="date" v-model="form.paid_at" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField label="Reference" v-model="form.reference" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextarea label="Notes" rows="2" v-model="form.notes" />
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
