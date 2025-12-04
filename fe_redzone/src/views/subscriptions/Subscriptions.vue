<script setup>
import { ref, onMounted } from 'vue'
import { formatIsoToReadable } from '@/helpers/dateUtils';
import {
    fetchSubscriptions,
    createSubscription,
    updateSubscription,
    deleteSubscription,
} from '@/services/subscriptions'
import { fetchSubscribers } from '@/services/subscribers'
import { fetchPlans } from '@/services/plans'

const loading = ref(false)
const dialog = ref(false)

const subscriptions = ref([])
const subscribers = ref([])
const plans = ref([])

const form = ref({
    id: null,
    subscriber_id: null,
    plan_id: null,
    start_date: '',
    end_date: null,
    monthly_discount: 0,
    active: true,
})

const load = async () => {
    loading.value = true
    const [subsRes, subscrRes, plansRes] = await Promise.all([
        fetchSubscriptions(),
        fetchSubscribers(),
        fetchPlans(),
    ])
    subscriptions.value = subsRes.data.data ?? subsRes.data
    subscribers.value = subscrRes.data.data ?? subscrRes.data
    plans.value = plansRes.data.data ?? plansRes.data
    loading.value = false
}

const openCreate = () => {
    form.value = {
        id: null,
        subscriber_id: null,
        plan_id: null,
        start_date: new Date().toISOString().slice(0, 10),
        end_date: null,
        monthly_discount: 0,
        active: true,
    }
    dialog.value = true
}

const openEdit = (s) => {
    form.value = {
        id: s.id,
        subscriber_id: s.subscriber_id,
        plan_id: s.plan_id,
        start_date: s.start_date,
        end_date: s.end_date,
        monthly_discount: s.monthly_discount,
        active: s.active,
    }
    dialog.value = true
}

const save = async () => {
    if (form.value.id) {
        await updateSubscription(form.value.id, form.value)
    } else {
        await createSubscription(form.value)
    }
    dialog.value = false
    load()
}

const remove = async (s) => {
    if (!confirm(`Delete subscription for ${s.subscriber?.name}?`)) return
    await deleteSubscription(s.id)
    load()
}

const subscriberName = (s) => s.subscriber?.name ?? '—'
const planName = (s) => s.plan?.name ?? '—'

onMounted(load)
</script>

<template>
    <div class="card">

        <VCardTitle class="d-flex justify-space-between align-center">
            <span>Subscriptions</span>
            <VBtn color="primary" @click="openCreate">Add Subscriptions</VBtn>
        </VCardTitle>

        <div class="table-responsive text-nowrap">
            <VTable>
                <thead>
                    <tr>
                        <th>Subscriber</th>
                        <th>Plan</th>
                        <th>Start</th>
                        <th>Discount</th>
                        <th>Active</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in subscriptions" :key="s.id">
                        <td>{{ subscriberName(s) }}</td>
                        <td>{{ planName(s) }}</td>
                        <td>{{ formatIsoToReadable(s.start_date)  }}</td>
                        <td>₱{{ Number(s.monthly_discount).toFixed(2) }}</td>
                        <td>
                            <VChip size="small" :color="s.active ? 'success' : 'secondary'">
                                {{ s.active ? 'Active' : 'Inactive' }}
                            </VChip>
                        </td>
                        <td class="text-end">
                            <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(s)">Edit</VBtn>
                            <VBtn size="small" color="error" variant="outlined" @click="remove(s)">Delete</VBtn>
                        </td>
                    </tr>
                    <tr v-if="!loading && subscriptions.length === 0">
                        <td colspan="6" class="text-center text-muted py-4">No subscriptions found</td>
                    </tr>
                </tbody>
            </VTable>
        </div>
    </div>

    <!-- Dialog -->
    <VDialog v-model="dialog" persistent max-width="700">
        <VCard>
            <VCardTitle>
                {{ form.id ? 'Edit Subscription' : 'Add Subscription' }}
            </VCardTitle>
            <VCardText>
                <VRow>
                    <VCol cols="12" md="6">
                        <VSelect v-model="form.subscriber_id" :items="subscribers" item-title="name" item-value="id"
                            label="Subscriber" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VSelect v-model="form.plan_id" :items="plans" item-title="name" item-value="id" label="Plan" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField v-model="form.start_date" label="Start Date" type="date" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField v-model="form.end_date" label="End Date (optional)" type="date" />
                    </VCol>
                    <VCol cols="12" md="6">
                        <VTextField v-model.number="form.monthly_discount" type="number" label="Monthly Discount" />
                    </VCol>
                    <VCol cols="12" md="6" class="d-flex align-center">
                        <VSwitch v-model="form.active" label="Active" inset />
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
