<script setup>
import { ref, onMounted, watch, computed } from "vue"

import debounce from "lodash/debounce"
import api from "@/plugins/axios"
import { formatIsoToReadable } from "@/helpers/dateUtils"
import {
    assignCollectorFromCollectionSheet,
    removeCollectorAssignment,
} from "@/services/collectionAssignments"



const loading = ref(false)
const assigning = ref(false)
const printing = ref(false)
const rows = ref([])

const page = ref(1)
const perPage = ref(10)
const totalItems = ref(0)

const search = ref("")
const typeFilter = ref("due")
const assignmentDate = ref(new Date().toISOString().slice(0, 10))
const collectorName = ref("")
const assignmentStatusFilter = ref(null)

const filterOptions = [
    { label: "Due", value: "due" },
    { label: "Overdue", value: "overdue" },
    { label: "Disconnected Clients", value: "disconnected" },
]

const assignmentStatusOptions = [
    { label: "All", value: null },
    { label: "Assigned", value: "assigned" },
    { label: "Unassigned", value: "unassigned" },
]

const selectedIds = ref([])
const assignDialog = ref(false)

const assignForm = ref({
    collector_name: "",
    assignment_date: new Date().toISOString().slice(0, 10),
    notes: "",
})

 const collectorOptions = [
   "Collector A",
   "Collector B",
   "Collector C",
 ]

// const collectorOptions = computed(() => {
//     const names = rows.value
//         .map(r => r.collector_name)
//         .filter(Boolean)

//     return [...new Set(names)].sort((a, b) => a.localeCompare(b))
// })

const money = v => Number(v ?? 0).toFixed(2)

const allSelected = computed(() =>
    rows.value.length > 0 &&
    rows.value.every(r => selectedIds.value.includes(r.subscription_id))
)

const toggleSelectAll = () => {
    if (allSelected.value) {
        selectedIds.value = []
    } else {
        selectedIds.value = rows.value.map(r => r.subscription_id)
    }
}

const toggleRowSelection = (checked, subscriptionId) => {
    if (checked) {
        if (!selectedIds.value.includes(subscriptionId)) {
            selectedIds.value.push(subscriptionId)
        }
    } else {
        selectedIds.value = selectedIds.value.filter(id => id !== subscriptionId)
    }
}

const load = async () => {
    loading.value = true
    try {
        const { data } = await api.get("/api/collection-sheet", {
            params: {
                page: page.value,
                per_page: perPage.value,
                search: search.value || undefined,
                type: typeFilter.value || undefined,
                assignment_date: assignmentDate.value || undefined,
                collector_name: collectorName.value || undefined,
                assignment_status: assignmentStatusFilter.value ?? undefined,
            },
        })

        rows.value = data.data ?? []
        totalItems.value = data.total ?? 0

        selectedIds.value = selectedIds.value.filter(id =>
            rows.value.some(row => row.subscription_id === id)
        )
    } finally {
        loading.value = false
    }
}

const debouncedLoad = debounce(() => {
    page.value = 1
    load()
}, 350)

watch(search, debouncedLoad)

watch([typeFilter, assignmentDate, collectorName, assignmentStatusFilter], () => {
    page.value = 1
    load()
})

const openAssignDialog = () => {
    if (!selectedIds.value.length) {
        alert("Select subscriptions first")
        return
    }

    assignForm.value = {
        collector_name: "",
        assignment_date: assignmentDate.value,
        notes: "",
    }

    assignDialog.value = true
}

const assignCollector = async () => {
    if (!selectedIds.value.length) {
        alert("Select subscriptions first")
        return
    }

    if (!assignForm.value.collector_name) {
        alert("Please select a collector")
        return
    }

    assigning.value = true
    try {
        await assignCollectorFromCollectionSheet({
            subscription_ids: selectedIds.value,
            collector_name: assignForm.value.collector_name,
            assignment_date: assignForm.value.assignment_date,
            notes: assignForm.value.notes,
        })

        assignDialog.value = false
        selectedIds.value = []
        await load()
        alert("Collector assigned successfully")
    } catch (e) {
        alert(e.response?.data?.message || "Failed to assign collector")
    } finally {
        assigning.value = false
    }
}

const removeAssignment = async row => {
    if (!row.assignment_id) {
        alert("This subscription is not assigned to any collector.")
        return
    }

    if (!confirm("Remove assigned collector?")) return

    try {
        await removeCollectorAssignment(row.assignment_id)
        await load()
    } catch (e) {
        alert(e.response?.data?.message || "Failed to remove assignment")
    }
}

const printCollectionSheet = async () => {
    printing.value = true
    try {
        const response = await api.get("/api/collection-sheet/print", {
            params: {
                assignment_date: assignmentDate.value || undefined,
                collector_name: collectorName.value || undefined,
                type: typeFilter.value || undefined,
                assignment_status: assignmentStatusFilter.value ?? undefined,
                search: search.value || undefined,
            },
            responseType: "blob",
        })

        const blob = new Blob([response.data], { type: "application/pdf" })
        const url = window.URL.createObjectURL(blob)

        const a = document.createElement("a")
        a.href = url
        a.download = `collection-sheet-${assignmentDate.value}.pdf`
        a.click()

        window.URL.revokeObjectURL(url)
    } catch (e) {
        alert("Failed to print collection sheet")
    } finally {
        printing.value = false
    }
}

onMounted(load)
</script>

<template>
    <div class="card">
        <VCardTitle class="d-flex flex-column flex-md-row align-center justify-space-between gap-3">
            <span>Collection Sheet</span>

            <div class="d-flex flex-wrap gap-3 align-center">
                <VTextField v-model="search" label="Search subscriber / plan / collector" variant="outlined"
                    density="comfortable" prepend-inner-icon="mdi-magnify" clearable hide-details
                    style="min-width: 260px" />

                <VTextField v-model="assignmentDate" type="date" label="Assignment Date" variant="outlined"
                    density="comfortable" hide-details style="min-width: 190px" />

                <VCombobox v-model="collectorName" :items="collectorOptions" label="Collector" variant="outlined"
                    density="comfortable" clearable hide-details auto-select-first style="min-width: 220px" />

                <VSelect v-model="typeFilter" :items="filterOptions" item-title="label" item-value="value"
                    label="Collection Type" variant="outlined" density="comfortable" hide-details
                    style="min-width: 220px" />

                <VSelect v-model="assignmentStatusFilter" :items="assignmentStatusOptions" item-title="label"
                    item-value="value" label="Assignment Status" variant="outlined" density="comfortable" hide-details
                    clearable style="min-width: 220px" />
            </div>
        </VCardTitle>

        <div class="table-responsive text-nowrap">
            <div class="px-4 pb-2 d-flex flex-wrap gap-2">
                <VBtn color="primary" :disabled="!selectedIds.length" @click="openAssignDialog">
                    Assign Collector
                </VBtn>

                <VBtn color="success" :loading="printing" @click="printCollectionSheet">
                    Print
                </VBtn>
            </div>

            <VTable>
                <thead>
                    <tr>
                        <th style="width: 40px">
                            <VCheckbox :model-value="allSelected" @update:model-value="toggleSelectAll" hide-details
                                density="compact" />
                        </th>
                        <th>Date</th>
                        <th>Collector</th>
                        <th>Assignment</th>
                        <th>Subscriber</th>
                        <th>Plan</th>
                        <th>Due Date</th>
                        <th>Days Overdue</th>
                        <th>Status</th>
                        <th>Amount Due</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="row in rows" :key="`${row.assignment_id ?? 'na'}-${row.subscription_id}`">
                        <td>
                            <VCheckbox :model-value="selectedIds.includes(row.subscription_id)"
                                @update:model-value="checked => toggleRowSelection(checked, row.subscription_id)"
                                hide-details density="compact" />
                        </td>

                        <td>{{ row.assignment_date ? formatIsoToReadable(row.assignment_date) : "—" }}</td>

                        <td>
                            <div class="fw-500">{{ row.collector_name || "—" }}</div>
                            <div class="text-caption text-medium-emphasis">{{ row.notes || "—" }}</div>
                        </td>

                        <td>
                            <VChip size="small" :color="row.assignment_status === 'assigned' ? 'success' : 'secondary'">
                                {{ row.assignment_status }}
                            </VChip>
                        </td>

                        <td>
                            <div class="fw-500">{{ row.subscriber_name }}</div>
                            <div class="text-caption text-medium-emphasis">{{ row.subscriber_email }}</div>
                        </td>

                        <td>
                            <div>{{ row.plan_name }}</div>
                            <div class="text-caption text-medium-emphasis" v-if="row.plan_speed">
                                {{ row.plan_speed }} Mbps
                            </div>
                        </td>

                        <td>{{ row.due_date ? formatIsoToReadable(row.due_date) : "—" }}</td>
                        <td>{{ row.days_overdue || 0 }}</td>

                        <td>
                            <VChip size="small" :color="row.collection_type === 'overdue'
                                    ? 'error'
                                    : row.collection_type === 'disconnected'
                                        ? 'secondary'
                                        : 'warning'
                                ">
                                {{ row.collection_type }}
                            </VChip>
                        </td>

                        <td>
                            <strong>₱{{ money(row.total_due) }}</strong>
                        </td>

                        <td>{{ row.subscriber_phone || "—" }}</td>
                        <td>{{ row.subscriber_address || "—" }}</td>

                        <td class="text-end">
                            <VBtn v-if="row.assignment_id" size="small" color="error" variant="outlined"
                                @click="removeAssignment(row)">
                                Remove
                            </VBtn>
                        </td>
                    </tr>

                    <tr v-if="!loading && rows.length === 0">
                        <td colspan="13" class="text-center text-muted py-4">
                            No records found
                        </td>
                    </tr>
                </tbody>
            </VTable>

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

    <VDialog v-model="assignDialog" max-width="500">
        <VCard>
            <VCardTitle>Assign Collector sss</VCardTitle>

            <VCardText>
                <VRow>
                    <VCol cols="12">
                        <VAlert type="info" variant="tonal">
                            Assign <strong>{{ selectedIds.length }}</strong> selected subscription(s).
                        </VAlert>
                    </VCol>

                    <VCol cols="12">
                        <VSelect v-model="assignForm.collector_name" :items="collectorOptions" label="Collector"
                            variant="outlined" />
                    </VCol>

                    <VCol cols="12">
                        <VTextField v-model="assignForm.assignment_date" type="date" label="Assignment Date"
                            variant="outlined" />
                    </VCol>

                    <VCol cols="12">
                        <VTextarea v-model="assignForm.notes" label="Notes" variant="outlined" />
                    </VCol>
                </VRow>
            </VCardText>

            <VCardActions>
                <VSpacer />

                <VBtn variant="tonal" @click="assignDialog = false">
                    Cancel
                </VBtn>

                <VBtn color="primary" :loading="assigning" @click="assignCollector">
                    Assign
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<style scoped>
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