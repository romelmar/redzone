<script setup>
import { ref, onMounted, watch } from "vue"
import { formatIsoToReadable } from "@/helpers/dateUtils"

import {
  fetchSubscriptions,
  createSubscription,
  updateSubscription,
  deleteSubscription,
} from "@/services/subscriptions"

import {
  fetchSubscribers,
  searchSubscribers
} from "@/services/subscribers"

import { fetchPlans } from "@/services/plans"
import debounce from "lodash/debounce"

// ─────────────────────────────────────────────
// STATE
// ─────────────────────────────────────────────
const loading = ref(false)
const dialog = ref(false)

const subscriptions = ref([])

const page = ref(1)
const perPage = ref(20)
const totalItems = ref(0)

const search = ref("")
const searchResults = ref([])
const searchLoading = ref(false)

// dropdowns
const subscribers = ref([])
const plans = ref([])

const form = ref({
  id: null,
  subscriber_id: null,
  plan_id: null,
  start_date: "",
  end_date: null,
  monthly_discount: 0,
  active: true,
})

// ─────────────────────────────────────────────
// LOAD TABLE (server-side pagination + search)
// ─────────────────────────────────────────────
const load = async () => {
  loading.value = true

  const { data } = await fetchSubscriptions({
    page: page.value,
    per_page: perPage.value,
    search: search.value,
  })

  subscriptions.value = data.data
  totalItems.value = data.total

  loading.value = false
}

// ─────────────────────────────────────────────
// REMOTE SUBSCRIBER SEARCH (VAutocomplete)
// ─────────────────────────────────────────────
const onSearch = debounce(async (query) => {
  if (!query) {
    searchResults.value = []
    return
  }

  searchLoading.value = true
  const { data } = await searchSubscribers(query)
  searchResults.value = data
  searchLoading.value = false
}, 300)

const applySearchSelection = (id) => {
  if (!id) {
    search.value = ""
    page.value = 1
    load()
    return
  }

  const item = searchResults.value.find(i => i.id === id)
  if (item) {
    search.value = item.name
    page.value = 1
    load()
  }
}

// React to search text change
watch(search, () => {
  page.value = 1
  debouncedLoad()
})

const debouncedLoad = debounce(load, 400)

// ─────────────────────────────────────────────
// INITIAL DATA FOR SELECT DROPDOWNS
// ─────────────────────────────────────────────
const loadDropdowns = async () => {
  const [subsRes, plansRes] = await Promise.all([
    fetchSubscribers(),
    fetchPlans()
  ])

  subscribers.value = subsRes.data.data ?? subsRes.data
  plans.value = plansRes.data.data ?? plansRes.data
}

// ─────────────────────────────────────────────
// CRUD
// ─────────────────────────────────────────────
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
  form.value = { ...s }
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

onMounted(() => {
  load()
  loadDropdowns()
})
</script>

<template>
  <div class="card">

    <!-- Search Autocomplete -->
    <div class="px-4 pt-4">
      <VAutocomplete
        v-model="selectedSearch"
        v-model:search="search"
        label="Search subscribers"
        clearable
        variant="outlined"
        prepend-inner-icon="mdi-magnify"
        :items="searchResults"
        item-title="name"
        item-value="id"
        :loading="searchLoading"
        :no-filter="true"
        hide-details
        @update:search="onSearch"
        @update:modelValue="applySearchSelection"
      />
    </div>

    <!-- Header -->
    <VCardTitle class="d-flex justify-space-between align-center">
      <span>Subscriptions</span>
      <VBtn color="primary" @click="openCreate">Add Subscription</VBtn>
    </VCardTitle>

    <!-- Table -->
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
            <td>{{ s.subscriber?.name }}</td>
            <td>{{ s.plan?.name }}</td>
            <td>{{ formatIsoToReadable(s.start_date) }}</td>
            <td>₱{{ Number(s.monthly_discount).toFixed(2) }}</td>

            <td>
              <VChip size="small" :color="s.active ? 'success' : 'secondary'">
                {{ s.active ? "Active" : "Inactive" }}
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

      <!-- Pagination -->
      <div class="d-flex flex-column flex-sm-row align-center justify-space-between px-4 py-3 gap-3">
        <VPagination
          v-model="page"
          :length="Math.ceil(totalItems / perPage)"
          @update:modelValue="load"
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

    <!-- Dialog -->
    <VDialog v-model="dialog" persistent max-width="700">
      <VCard>
        <VCardTitle>{{ form.id ? "Edit Subscription" : "Add Subscription" }}</VCardTitle>

        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <VSelect v-model="form.subscriber_id" :items="subscribers" item-title="name" item-value="id" label="Subscriber" />
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
              <VTextField v-model.number="form.monthly_discount" label="Monthly Discount" type="number" />
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
  </div>
</template>
