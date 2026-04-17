<script setup>
import { ref, onMounted, computed } from 'vue'
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
const subscriptionSearch = ref('')

const form = ref({
  id: null,
  subscription_id: null,
  name: '',
  description: '',
  amount: null,
  credit_month: '',
})

const load = async () => {
  loading.value = true
  try {
    const [addonsRes, subsRes] = await Promise.all([
      fetchAddons(),
      fetchSubscriptions({
        per_page: 10,
        sort_by: 'subscriber_name',
        sort_dir: 'asc',
      }),
    ])

    addons.value =
      addonsRes.data?.data?.data ??
      addonsRes.data?.data ??
      addonsRes.data ??
      []

    subscriptions.value =
      subsRes.data?.data?.data ??
      subsRes.data?.data ??
      subsRes.data ??
      []
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  form.value = {
    id: null,
    subscription_id: null,
    name: '',
    description: '',
    amount: null,
    credit_month: new Date().toISOString().slice(0, 10).replace(/\d{2}$/, '01'),
  }
  dialog.value = true
}

const openEdit = (a) => {
  form.value = {
    id: a.id,
    subscription_id: a.subscription_id,
    name: a.name ?? '',
    description: a.description ?? '',
    amount: a.amount,
    credit_month: a.credit_month ? a.credit_month.slice(0, 10) : '',
  }
  dialog.value = true
}

const save = async () => {
  const payload = {
    subscription_id: form.value.subscription_id,
    name: form.value.name,
    description: form.value.description || null,
    amount: Number(form.value.amount),
    credit_month: form.value.credit_month,
  }

  if (!payload.subscription_id) {
    alert('Please select a subscription.')
    return
  }

  if (!payload.name) {
    alert('Please enter a name.')
    return
  }

  if (!payload.amount || payload.amount <= 0) {
    alert('Please enter a valid amount.')
    return
  }

  if (!payload.credit_month) {
    alert('Please select a bill month.')
    return
  }

  try {
    if (form.value.id) {
      await updateAddon(form.value.id, payload)
    } else {
      await createAddon(payload)
    }

    dialog.value = false
    load()
  } catch (e) {
    console.error('Addon save error:', e.response?.data || e)
    alert(e.response?.data?.message || 'Failed to save add-on.')
  }
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

const subscriptionOptions = computed(() => {
  const q = subscriptionSearch.value.toLowerCase()

  return subscriptions.value
    .filter(s => {
      const name = s.subscriber?.name?.toLowerCase() ?? ''
      const plan = s.plan?.name?.toLowerCase() ?? ''
      return name.includes(q) || plan.includes(q)
    })
    .sort((a, b) =>
      (a.subscriber?.name ?? '').localeCompare(b.subscriber?.name ?? '')
    )
    .map(sub => ({
      ...sub,
      label: `${sub.subscriber?.name ?? '—'} - ${sub.plan?.name ?? '—'}`,
    }))
})

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
            <th>Description</th>
            <th>Amount</th>
            <th>Bill Month</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="a in addons" :key="a.id">
            <td>{{ subscriptionLabel(a) }}</td>
            <td>{{ a.name }}</td>
            <td>{{ a.description || '—' }}</td>
            <td>₱{{ Number(a.amount ?? 0).toFixed(2) }}</td>
            <td>{{ a.credit_month ? formatIsoToReadable(a.credit_month) : '—' }}</td>
            <td class="text-end">
              <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(a)">Edit</VBtn>
              <VBtn size="small" color="error" variant="outlined" @click="remove(a)">Delete</VBtn>
            </td>
          </tr>
          <tr v-if="!loading && addons.length === 0">
            <td colspan="6" class="text-center text-muted py-4">No add-ons found</td>
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
            <VAutocomplete
              v-model="form.subscription_id"
              v-model:search="subscriptionSearch"
              :items="subscriptionOptions"
              item-title="label"
              item-value="id"
              label="Subscription"
              variant="outlined"
              clearable
            >
              <template #item="{ props, item }">
                <VListItem v-bind="props">
                  <VListItemTitle>
                    {{ item.raw.subscriber?.name ?? '—' }}
                  </VListItemTitle>
                  <VListItemSubtitle>
                    {{ item.raw.plan?.name ?? '—' }}
                    <span v-if="item.raw.plan?.price">
                      — ₱{{ Number(item.raw.plan.price).toFixed(2) }}
                    </span>
                  </VListItemSubtitle>
                </VListItem>
              </template>

              <template #selection="{ item }">
                <span>
                  {{ item.raw.subscriber?.name ?? '—' }} - {{ item.raw.plan?.name ?? '—' }}
                </span>
              </template>
            </VAutocomplete>
          </VCol>

          <VCol cols="12">
            <VTextField label="Name" v-model="form.name" variant="outlined" />
          </VCol>

          <VCol cols="12">
            <VTextField
              label="Description"
              v-model="form.description"
              clearable
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField
              label="Amount"
              type="number"
              v-model.number="form.amount"
              variant="outlined"
            />
          </VCol>

          <VCol cols="12" md="6">
            <VTextField
              label="Bill Month"
              type="date"
              v-model="form.credit_month"
              variant="outlined"
            />
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