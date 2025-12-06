<script setup>
import { ref, onMounted, watch, computed } from "vue"
import axios from "axios"
import { formatIsoToReadable } from "@/helpers/dateUtils"

import {
    fetchSubscriptions,
    createSubscription,
    updateSubscription,
    deleteSubscription,
    activateSubscription,
    deactivateSubscription
} from "@/services/subscriptions"

import { fetchSubscribers } from "@/services/subscribers"
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

// filters & search
const search = ref("")
const activeFilter = ref("")      // '', 'active', 'suspended', 'inactive'
const planFilter = ref(null)      // plan_id

// sorting
const sortBy = ref("start_date")
const sortDir = ref("desc")       // 'asc' | 'desc'

// dropdown data
const subscribers = ref([])
const plans = ref([])

// bulk selection
const selectedIds = ref([])

// dialogs for extra features
const billingDialog = ref(false)
const billingLoading = ref(false)
const billingData = ref(null)

const historyDialog = ref(false)
const historyLoading = ref(false)
const historyEvents = ref([])

// form
const form = ref({
    id: null,
    subscriber_id: null,
    plan_id: null,
    start_date: "",
    end_date: null,
    monthly_discount: 0,
    active: true, // keep for compatibility; status comes from backend
})

// ─────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────
const statusOptions = [
    { label: "All", value: "" },
    { label: "Active", value: "active" },
    { label: "Suspended", value: "suspended" },
    { label: "Inactive", value: "inactive" },
]

const statusColor = (status) => {
    switch (status) {
        case "active": return "success"
        case "suspended": return "warning"
        case "inactive": return "secondary"
        default: return "secondary"
    }
}

const formatCurrency = (amount) =>
    `₱${Number(amount ?? 0).toFixed(2)}`

// Bulk selection helpers
const allVisibleSelected = computed(() =>
    subscriptions.value.length > 0 &&
    subscriptions.value.every(s => selectedIds.value.includes(s.id))
)

const toggleSelectAll = () => {
    if (allVisibleSelected.value) {
        selectedIds.value = []
    } else {
        selectedIds.value = subscriptions.value.map(s => s.id)
    }
}

// Sorting
const setSort = (column) => {
    if (sortBy.value === column) {
        sortDir.value = sortDir.value === "asc" ? "desc" : "asc"
    } else {
        sortBy.value = column
        sortDir.value = "asc"
    }
    load()
}

const sortIcon = (column) => {
    if (sortBy.value !== column) return "mdi-swap-vertical"
    return sortDir.value === "asc" ? "mdi-arrow-up" : "mdi-arrow-down"
}

// ─────────────────────────────────────────────
// LOAD TABLE (server-side pagination + search + filters + sort)
// ─────────────────────────────────────────────
const load = async () => {
    loading.value = true

    const { data } = await fetchSubscriptions({
        page: page.value,
        per_page: perPage.value,
        search: search.value || undefined,
        status: activeFilter.value || undefined,
        plan_id: planFilter.value || undefined,
        sort_by: sortBy.value,
        sort_dir: sortDir.value,
    })

    subscriptions.value = data.data ?? data.data
    totalItems.value = data.total ?? 0
    selectedIds.value = [] // reset bulk selection

    loading.value = false
}

const debouncedLoad = debounce(load, 400)

// react to search/filter changes
watch([search, activeFilter, planFilter], () => {
    page.value = 1
    debouncedLoad()
})

// ─────────────────────────────────────────────
// DROPDOWNS FOR FORM FILTERS
// ─────────────────────────────────────────────
const loadDropdowns = async () => {
    const [subsRes, plansRes] = await Promise.all([
        fetchSubscribers(),
        fetchPlans(),
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
    form.value = {
        id: s.id,
        subscriber_id: s.subscriber_id,
        plan_id: s.plan_id,
        start_date: s.start_date,
        end_date: s.end_date,
        monthly_discount: s.monthly_discount,
        active: s.status === "active",
    }
    dialog.value = true
}

const save = async () => {
    const payload = { ...form.value }
    // optional: map boolean active -> status
    payload.status = form.value.active ? "active" : "inactive"

    if (form.value.id) {
        await updateSubscription(form.value.id, payload)
    } else {
        await createSubscription(payload)
    }
    dialog.value = false
    load()
}

const remove = async (s) => {
    if (!confirm(`Delete subscription for ${s.subscriber?.name}?`)) return
    await deleteSubscription(s.id)
    load()
}

// ─────────────────────────────────────────────
// STATUS CHANGE (Activate / Suspend / Deactivate)
// ─────────────────────────────────────────────
const updateStatus = async (s, status) => {
    await updateSubscription(s.id, { status })
    load()
}

// BULK ACTIONS
const bulkUpdateStatus = async (status) => {
    if (!selectedIds.value.length) return
    if (!confirm(`Set ${selectedIds.value.length} subscription(s) to "${status}"?`)) return

    // simple approach: loop, you can optimize via bulk API
    await Promise.all(
        selectedIds.value.map(id => updateSubscription(id, { status }))
    )
    load()
}

const bulkDelete = async () => {
    if (!selectedIds.value.length) return
    if (!confirm(`Delete ${selectedIds.value.length} subscription(s)?`)) return

    await Promise.all(
        selectedIds.value.map(id => deleteSubscription(id))
    )
    load()
}

// ─────────────────────────────────────────────
// BILLING PREVIEW (SOA JSON)
// ─────────────────────────────────────────────
const openBillingPreview = async (s) => {
    billingDialog.value = true
    billingLoading.value = true
    billingData.value = null

    try {
        const { data } = await axios.get(`/api/subscriptions/${s.id}/soa-json`)
        billingData.value = data
    } finally {
        billingLoading.value = false
    }
}

// ─────────────────────────────────────────────
// HISTORY TIMELINE
// ─────────────────────────────────────────────
const openHistory = async (s) => {
    historyDialog.value = true
    historyLoading.value = true
    historyEvents.value = []

    try {
        const { data } = await axios.get(`/api/subscriptions/${s.id}/history`)
        historyEvents.value = data // [{ date, type, description }, ...]
    } finally {
        historyLoading.value = false
    }
}


// ─────────────────────────────────────────────
// ACTIVATION / DEACTIVATION ENDPOINTS
// ─────────────────────────────────────────────
const activate = async (s) => {
    try {
        await activateSubscription(s.id);
        load(); // reload table
    } catch (err) {
        console.error(err);
        alert(err.response?.data?.message || "Activation failed.");
    }
}

const deactivate = async (s) => {
    try {
        await deactivateSubscription(s.id);
        load();
    } catch (err) {
        console.error(err);
        alert(err.response?.data?.message || "Deactivation failed.");
    }
}

const bulkActivate = async () => {
    if (!selectedIds.value.length) return;
    if (!confirm(`Activate ${selectedIds.value.length} subscription(s)?`)) return;

    await Promise.all(
        selectedIds.value.map(id => activateSubscription(id))
    );
    load();
};

const bulkDeactivate = async () => {
    if (!selectedIds.value.length) return;
    if (!confirm(`Deactivate ${selectedIds.value.length} subscription(s)?`)) return;

    await Promise.all(
        selectedIds.value.map(id => deactivateSubscription(id))
    );
    load();
};



onMounted(async () => {
    await Promise.all([loadDropdowns(), load()])
})
</script>

<template>
    <div class="card">

        <!-- Filters & search -->
        <div class="px-4 pt-4 d-flex flex-column flex-md-row gap-3 align-center justify-space-between mb-2">
            <div class="d-flex flex-wrap gap-3 align-center">
                <VTextField v-model="search" label="Search (subscriber, plan, status)" variant="outlined"
                    density="comfortable" prepend-inner-icon="mdi-magnify" hide-details style="min-width: 260px" />

                <VSelect v-model="activeFilter" :items="statusOptions" item-title="label" item-value="value"
                    label="Status" variant="outlined" density="comfortable" hide-details style="min-width: 160px" />

                <VSelect v-model="planFilter" :items="plans" item-title="name" item-value="id" label="Plan"
                    variant="outlined" density="comfortable" hide-details style="min-width: 200px" />
            </div>

            <VBtn color="primary" @click="openCreate">
                Add Subscription
            </VBtn>
        </div>

        <!-- Bulk actions bar -->
        <div v-if="selectedIds.length"
            class="px-4 pt-2 d-flex flex-wrap gap-2 align-center justify-space-between text-body-2">
            <div>
                <strong>{{ selectedIds.length }}</strong> selected
            </div>
            <div class="d-flex gap-2 mb-2">
                <VBtn size="small" color="success" variant="tonal" @click="bulkActivate">
                    Activate
                </VBtn>
                <VBtn size="small" color="warning" variant="tonal" @click="bulkUpdateStatus('suspended')">
                    Suspend
                </VBtn>
                <VBtn size="small" color="secondary" variant="tonal" @click="bulkDeactivate">
                    Deactivate
                </VBtn>
                <VBtn size="small" color="error" variant="outlined" @click="bulkDelete">
                    Delete
                </VBtn>
            </div>
        </div>

        <!-- Table -->
        <div class="table-responsive text-nowrap">
            <VTable>
                <thead>
                    <tr>
                        <th class="text-start" style="width: 40px;">
                            <VCheckbox :model-value="allVisibleSelected" @update:model-value="toggleSelectAll"
                                hide-details density="compact" />
                        </th>

                        <th @click="setSort('subscriber_name')" class="sortable-header">
                            <div class="d-flex align-center">
                                Subscriber
                                <VIcon size="16" class="ms-1">{{ sortIcon('subscriber_name') }}</VIcon>
                            </div>
                        </th>

                        <th @click="setSort('plan_name')" class="sortable-header">
                            <div class="d-flex align-center">
                                Plan
                                <VIcon size="16" class="ms-1">{{ sortIcon('plan_name') }}</VIcon>
                            </div>
                        </th>

                        <th @click="setSort('start_date')" class="sortable-header">
                            <div class="d-flex align-center">
                                Start
                                <VIcon size="16" class="ms-1">{{ sortIcon('start_date') }}</VIcon>
                            </div>
                        </th>

                        <th @click="setSort('monthly_discount')" class="sortable-header">
                            <div class="d-flex align-center">
                                Discount
                                <VIcon size="16" class="ms-1">{{ sortIcon('monthly_discount') }}</VIcon>
                            </div>
                        </th>

                        <th @click="setSort('current_balance')" class="sortable-header">
                            <div class="d-flex align-center">
                                Balance
                                <VIcon size="16" class="ms-1">{{ sortIcon('current_balance') }}</VIcon>
                            </div>
                        </th>

                        <th @click="setSort('status')" class="sortable-header">
                            <div class="d-flex align-center">
                                Status
                                <VIcon size="16" class="ms-1">{{ sortIcon('status') }}</VIcon>
                            </div>
                        </th>

                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="s in subscriptions" :key="s.id">
                        <td>
                            <VCheckbox :model-value="selectedIds.includes(s.id)" @update:model-value="checked => {
                                if (checked) selectedIds.push(s.id)
                                else selectedIds = selectedIds.filter(id => id !== s.id)
                            }" hide-details density="compact" />
                        </td>

                        <td>
                            {{ s.subscriber?.name ?? "—" }}
                        </td>

                        <td>
                            <div class="fw-500">
                                {{ s.plan?.name ?? "—" }}
                            </div>
                            <div class="text-caption text-medium-emphasis">
                                {{ s.plan?.speed ? `${s.plan.speed} Mbps` : "" }}
                                <span v-if="s.plan?.price">
                                    • {{ formatCurrency(s.plan.price) }}
                                </span>
                            </div>
                        </td>

                        <td>{{ formatIsoToReadable(s.start_date) }}</td>

                        <td>{{ formatCurrency(s.monthly_discount) }}</td>

                        <td>{{ formatCurrency(s.current_balance) }}</td>

                        <td>
                            <VChip :color="s.active ? 'success' : 'secondary'">
                                {{ s.active ? 'Active' : 'Inactive' }}
                            </VChip>
                        </td>

                        <td class="text-end">
                            <VBtn size="small" variant="text" class="me-1" @click="openBillingPreview(s)">
                                Billing
                            </VBtn>

                            <VBtn size="small" variant="text" class="me-1" @click="openHistory(s)">
                                History
                            </VBtn>

                            <VMenu>
                                <template #activator="{ props }">
                                    <VBtn size="small" variant="outlined" v-bind="props">
                                        Actions
                                    </VBtn>
                                </template>

                                <VList density="compact">
                                    <VListItem @click="openEdit(s)">
                                        <VListItemTitle>Edit</VListItemTitle>
                                    </VListItem>

                                    <VListItem v-if="!s.active" @click="activate(s)">
                                        <VListItemTitle class="text-success">Activate</VListItemTitle>
                                    </VListItem>

                                    <VListItem v-if="s.active" @click="deactivate(s)">
                                        <VListItemTitle class="text-secondary">Deactivate</VListItemTitle>
                                    </VListItem>

                                    <VListItem @click="openBillingPreview(s)">
                                        <VListItemTitle>Billing Preview</VListItemTitle>
                                    </VListItem>

                                    <VListItem @click="openHistory(s)">
                                        <VListItemTitle>History</VListItemTitle>
                                    </VListItem>

                                    <VListItem @click="remove(s)">
                                        <VListItemTitle class="text-error">Delete</VListItemTitle>
                                    </VListItem>
                                </VList>

                            </VMenu>
                        </td>
                    </tr>

                    <tr v-if="!loading && subscriptions.length === 0">
                        <td colspan="8" class="text-center text-muted py-4">
                            No subscriptions found
                        </td>
                    </tr>
                </tbody>
            </VTable>

            <!-- Pagination + per page -->
            <div class="d-flex flex-column flex-sm-row align-center justify-space-between px-4 py-3 gap-3">
                <VPagination v-model="page" :length="Math.ceil(totalItems / perPage)" @update:modelValue="load"
                    rounded="lg" variant="flat" color="primary" class="pagination-sneat" />

                <div class="d-flex align-center">
                    <span class="me-2 text-body-2">Rows per page:</span>

                    <VSelect v-model="perPage" :items="[10, 20, 50, 100]" density="comfortable" variant="outlined"
                        hide-details class="sneat-rows-select" style="max-width: 110px"
                        @update:modelValue="() => { page = 1; load() }" />
                </div>
            </div>
        </div>
    </div>

    <!-- Dialog: Create / Edit -->
    <VDialog v-model="dialog" persistent max-width="700">
        <VCard>
            <VCardTitle>
                {{ form.id ? "Edit Subscription" : "Add Subscription" }}
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

    <!-- Dialog: Billing Preview / SOA -->
    <VDialog v-model="billingDialog" max-width="700">
        <VCard>
            <VCardTitle>Statement of Account Preview</VCardTitle>
            <VCardText>
                <div v-if="billingLoading" class="text-center py-6">
                    <VProgressCircular indeterminate color="primary" />
                </div>

                <div v-else-if="billingData">
                    <p class="mb-1">
                        <strong>Subscriber:</strong> {{ billingData.subscriber }}
                    </p>
                    <p class="mb-1">
                        <strong>Plan:</strong> {{ billingData.plan }}
                    </p>
                    <p class="mb-1">
                        <strong>Billing Period:</strong> {{ billingData.billing_period }}
                    </p>

                    <VTable density="compact" class="mt-4">
                        <tbody>
                            <tr>
                                <td>Previous Balance</td>
                                <td class="text-end">{{ formatCurrency(billingData.previous_balance) }}</td>
                            </tr>
                            <tr>
                                <td>Base Amount</td>
                                <td class="text-end">{{ formatCurrency(billingData.base_amount) }}</td>
                            </tr>
                            <tr>
                                <td>Add-ons</td>
                                <td class="text-end">{{ formatCurrency(billingData.addons_amount) }}</td>
                            </tr>
                            <tr>
                                <td>Service Credits</td>
                                <td class="text-end">-{{ formatCurrency(billingData.credits_amount) }}</td>
                            </tr>
                            <tr>
                                <td><strong>Total Due</strong></td>
                                <td class="text-end"><strong>{{ formatCurrency(billingData.total_due) }}</strong></td>
                            </tr>
                        </tbody>
                    </VTable>
                </div>

                <div v-else class="text-center py-6">
                    No billing data.
                </div>
            </VCardText>
            <VCardActions>
                <VSpacer />
                <VBtn variant="tonal" @click="billingDialog = false">Close</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>

    <!-- Dialog: History Timeline -->
    <VDialog v-model="historyDialog" max-width="700">
        <VCard>
            <VCardTitle>Subscription History</VCardTitle>
            <VCardText>
                <div v-if="historyLoading" class="text-center py-6">
                    <VProgressCircular indeterminate color="primary" />
                </div>

                <div v-else-if="historyEvents.length">
                    <VTimeline side="end" density="compact">
                        <VTimelineItem v-for="(event, idx) in historyEvents" :key="idx"
                            :dot-color="event.type === 'status' ? 'primary' : 'secondary'" size="small">
                            <div class="text-caption text-medium-emphasis">
                                {{ event.date }}
                            </div>
                            <div class="fw-500">{{ event.title || event.type }}</div>
                            <div class="text-body-2">
                                {{ event.description }}
                            </div>
                        </VTimelineItem>
                    </VTimeline>
                </div>

                <div v-else class="text-center py-6">
                    No history available.
                </div>
            </VCardText>
            <VCardActions>
                <VSpacer />
                <VBtn variant="tonal" @click="historyDialog = false">Close</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<style scoped>
.sortable-header {
    cursor: pointer;
    user-select: none;
    white-space: nowrap;
}

.sortable-header .v-icon {
    opacity: 0.6;
}

/* Sneat-style pagination */
.pagination-sneat .v-pagination__item,
.pagination-sneat .v-pagination__first,
.pagination-sneat .v-pagination__last,
.pagination-sneat .v-pagination__next,
.pagination-sneat .v-pagination__prev {
    border-radius: 8px !important;
    min-width: 38px !important;
    height: 38px !important;
    font-weight: 500;
    box-shadow: var(--v-shadow-2);
    transition: all 0.2s ease-in-out;
}

.pagination-sneat .v-pagination__item:hover {
    background-color: rgba(var(--v-theme-primary), 0.1) !important;
    transform: translateY(-2px);
}

.pagination-sneat .v-pagination__item--is-active {
    background-color: rgb(var(--v-theme-primary)) !important;
    color: white !important;
    box-shadow: var(--v-shadow-4);
}

.sneat-rows-select .v-field {
    border-radius: 8px !important;
}
</style>
