<script setup>
import { ref, onMounted, watch, computed } from "vue"
import debounce from "lodash/debounce"
import { formatIsoToReadable } from "@/helpers/dateUtils"
import { fetchSubscriptions } from "@/services/subscriptions"
import {
    fetchCollectionAssignments,
    createCollectionAssignment,
    deleteCollectionAssignment,
    bulkReassignCollectionAssignments,
    bulkDeleteCollectionAssignments,
} from "@/services/collectionAssignments"

const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)

const assignments = ref([])
const subscriptions = ref([])

const page = ref(1)
const perPage = ref(10)
const totalItems = ref(0)

const search = ref("")
const assignmentDateFilter = ref(new Date().toISOString().slice(0, 10))
const collectorFilter = ref("")

const collectors = ref([
    "Collector A",
    "Collector B",
    "Collector C",
])

const form = ref({
    subscription_ids: [],
    assignment_date: new Date().toISOString().slice(0, 10),
    collector_name: "",
    notes: "",
})

const selectedAssignmentIds = ref([])

const bulkDialog = ref(false)
const bulkSaving = ref(false)

const bulkForm = ref({
    collector_name: "",
    notes: "",
})

const allVisibleSelected = computed(() =>
    assignments.value.length > 0 &&
    assignments.value.every(a => selectedAssignmentIds.value.includes(a.id))
)

const toggleSelectAll = () => {
    if (allVisibleSelected.value) {
        selectedAssignmentIds.value = selectedAssignmentIds.value.filter(
            id => !assignments.value.some(a => a.id === id)
        )
    } else {
        const ids = assignments.value.map(a => a.id)
        selectedAssignmentIds.value = [...new Set([...selectedAssignmentIds.value, ...ids])]
    }
}

const openBulkReassign = () => {
    bulkForm.value = {
        collector_name: "",
        notes: "",
    }
    bulkDialog.value = true
}

const saveBulkReassign = async () => {
    if (!selectedAssignmentIds.value.length) return

    bulkSaving.value = true
    try {
        await bulkReassignCollectionAssignments({
            assignment_ids: selectedAssignmentIds.value,
            collector_name: bulkForm.value.collector_name,
            notes: bulkForm.value.notes,
        })

        bulkDialog.value = false
        selectedAssignmentIds.value = []
        await loadAssignments()
    } catch (e) {
        alert(e.response?.data?.message || "Failed to reassign.")
    } finally {
        bulkSaving.value = false
    }
}

const bulkRemove = async () => {
    if (!selectedAssignmentIds.value.length) return
    if (!confirm(`Remove ${selectedAssignmentIds.value.length} assignment(s)?`)) return

    try {
        await bulkDeleteCollectionAssignments({
            assignment_ids: selectedAssignmentIds.value,
        })
        selectedAssignmentIds.value = []
        await loadAssignments()
    } catch (e) {
        alert(e.response?.data?.message || "Failed to remove assignments.")
    }
}



const selectedSubscriptions = computed(() => {
    return subscriptions.value.filter(s => form.value.subscription_ids.includes(s.id))
})

const subscriptionOptions = computed(() => {
    const q = search.value.trim().toLowerCase()

    return subscriptions.value
        .filter(s => {
            if (!q) return true
            const subscriber = s.subscriber?.name?.toLowerCase() ?? ""
            const plan = s.plan?.name?.toLowerCase() ?? ""
            return subscriber.includes(q) || plan.includes(q)
        })
        .map(s => ({
            title: `${s.subscriber?.name ?? "—"} — ${s.plan?.name ?? "—"}`,
            value: s.id,
            raw: s,
        }))
})

const loadAssignments = async () => {
    loading.value = true
    try {
        const { data } = await fetchCollectionAssignments({
            page: page.value,
            per_page: perPage.value,
            assignment_date: assignmentDateFilter.value || undefined,
            collector_name: collectorFilter.value || undefined,
        })

        assignments.value = data.data ?? []
        totalItems.value = data.total ?? 0

        // keep only selections that still exist on the page
        selectedAssignmentIds.value = selectedAssignmentIds.value.filter(id =>
            assignments.value.some(row => row.id === id)
        )

    } finally {
        loading.value = false
    }
}

const loadSubscriptions = async () => {
    const { data } = await fetchSubscriptions({
        per_page: 10,
        active: "active",
        sort_by: "subscriber_name",
        sort_dir: "asc",
    })

    subscriptions.value = data.data ?? data
}

const debouncedReload = debounce(() => {
    page.value = 1
    loadAssignments()
}, 300)

watch([assignmentDateFilter, collectorFilter], debouncedReload)

const openCreate = () => {
    form.value = {
        subscription_ids: [],
        assignment_date: assignmentDateFilter.value || new Date().toISOString().slice(0, 10),
        collector_name: "",
        notes: "",
    }
    search.value = ""
    dialog.value = true
}

const save = async () => {
    saving.value = true
    try {
        await createCollectionAssignment(form.value)
        dialog.value = false
        await loadAssignments()
    } catch (e) {
        alert(e.response?.data?.message || "Failed to save assignment.")
    } finally {
        saving.value = false
    }
}

const remove = async (row) => {
    if (!confirm(`Remove assignment for ${row.subscription?.subscriber?.name ?? "this subscriber"}?`)) return

    try {
        await deleteCollectionAssignment(row.id)
        await loadAssignments()
    } catch (e) {
        alert(e.response?.data?.message || "Failed to delete assignment.")
    }
}

onMounted(async () => {
    await Promise.all([loadAssignments(), loadSubscriptions()])
})
</script>

<template>
    <div class="card">
        <VCardTitle class="d-flex flex-column flex-md-row align-center justify-space-between gap-3">
            <span>Assign Collector</span>

            <div class="d-flex flex-wrap gap-3 align-center">
                <VTextField v-model="assignmentDateFilter" type="date" label="Assignment Date" variant="outlined"
                    density="comfortable" hide-details style="min-width: 190px" />

                <VSelect v-model="collectorFilter" :items="collectors" label="Collector" variant="outlined"
                    density="comfortable" clearable hide-details style="min-width: 200px" />

                <VBtn color="primary" @click="openCreate">
                    Assign Collector
                </VBtn>
            </div>
        </VCardTitle>

        <div class="table-responsive text-nowrap">
            <div v-if="selectedAssignmentIds.length"
                class="px-4 pt-2 d-flex flex-wrap gap-2 align-center justify-space-between text-body-2">
                <div>
                    <strong>{{ selectedAssignmentIds.length }}</strong> selected
                </div>

                <div class="d-flex gap-2">
                    <VBtn size="small" color="primary" variant="tonal" @click="openBulkReassign">
                        Reassign Collector
                    </VBtn>

                    <VBtn size="small" color="error" variant="outlined" @click="bulkRemove">
                        Remove Selected
                    </VBtn>
                </div>
            </div>
            <VTable>
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <VCheckbox :model-value="allVisibleSelected" @update:model-value="toggleSelectAll"
                                hide-details density="compact" />
                        </th>
                        <th>Date</th>
                        <th>Collector</th>
                        <th>Subscriber</th>
                        <th>Plan</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="row in assignments" :key="row.id">
                        <td>
                            <VCheckbox :model-value="selectedAssignmentIds.includes(row.id)" @update:model-value="checked => {
                                if (checked) {
                                    if (!selectedAssignmentIds.includes(row.id)) {
                                        selectedAssignmentIds.push(row.id)
                                    }
                                } else {
                                    selectedAssignmentIds.value = selectedAssignmentIds.value.filter(id => id !== row.id)
                                }
                            }" hide-details density="compact" />
                        </td>

                        <td>{{ formatIsoToReadable(row.assignment_date) }}</td>
                        <td>{{ row.collector_name }}</td>
                        <td>{{ row.subscription?.subscriber?.name ?? "—" }}</td>
                        <td>{{ row.subscription?.plan?.name ?? "—" }}</td>
                        <td>{{ row.notes || "—" }}</td>

                        <td class="text-end">
                            <VBtn size="small" color="error" variant="outlined" @click="remove(row)">
                                Remove
                            </VBtn>
                        </td>
                    </tr>
                </tbody>
            </VTable>

            <div class="d-flex flex-column flex-sm-row align-center justify-space-between px-4 py-3 gap-3">
                <VPagination v-model="page" :length="Math.ceil(totalItems / perPage)"
                    @update:modelValue="loadAssignments" rounded="lg" variant="flat" color="primary"
                    class="pagination-sneat" />

                <div class="d-flex align-center">
                    <span class="me-2 text-body-2">Rows per page:</span>
                    <VSelect v-model="perPage" :items="[10, 20, 50, 100]" density="comfortable" variant="outlined"
                        hide-details class="sneat-rows-select" style="max-width: 110px"
                        @update:modelValue="() => { page = 1; loadAssignments() }" />
                </div>
            </div>
        </div>
    </div>

    <VDialog v-model="dialog" persistent max-width="800">
        <VCard>
            <VCardTitle>Assign Collector</VCardTitle>

            <VCardText>
                <VRow>
                    <VCol cols="12">
                        <VTextField v-model="search" label="Search subscriber / plan" variant="outlined"
                            density="comfortable" prepend-inner-icon="mdi-magnify" clearable />
                    </VCol>

                    <VCol cols="12">
                        <VAutocomplete v-model="form.subscription_ids" :items="subscriptionOptions" item-title="title"
                            item-value="value" label="Subscriptions" variant="outlined" clearable multiple chips
                            closable-chips>
                            <template #item="{ props, item }">
                                <VListItem v-bind="props">
                                    <VListItemTitle>
                                        {{ item.raw.raw?.subscriber?.name ?? "—" }}
                                    </VListItemTitle>
                                    <VListItemSubtitle>
                                        {{ item.raw.raw?.plan?.name ?? "—" }}
                                    </VListItemSubtitle>
                                </VListItem>
                            </template>
                        </VAutocomplete>
                    </VCol>

                    <VCol cols="12" md="6">
                        <VTextField v-model="form.assignment_date" type="date" label="Assignment Date"
                            variant="outlined" />
                    </VCol>

                    <VCol cols="12" md="6">
                        <VSelect v-model="form.collector_name" :items="collectors" label="Collector"
                            variant="outlined" />
                    </VCol>

                    <VCol cols="12">
                        <VTextarea v-model="form.notes" label="Notes" rows="2" variant="outlined" />
                    </VCol>

                    <VCol cols="12" v-if="selectedSubscriptions.length">
                        <VAlert type="info" variant="tonal">
                            <div><strong>Selected Subscriptions:</strong></div>
                            <ul class="mb-0 ps-4">
                                <li v-for="sub in selectedSubscriptions" :key="sub.id">
                                    {{ sub.subscriber?.name }} — {{ sub.plan?.name }}
                                </li>
                            </ul>
                        </VAlert>
                    </VCol>
                </VRow>
            </VCardText>

            <VCardActions>
                <VSpacer />
                <VBtn variant="tonal" @click="dialog = false">Cancel</VBtn>
                <VBtn color="primary" :loading="saving" @click="save">Save</VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
    <VDialog v-model="bulkDialog" persistent max-width="500">
        <VCard>
            <VCardTitle>Reassign Collector</VCardTitle>

            <VCardText>
                <VRow>
                    <VCol cols="12">
                        <VAlert type="info" variant="tonal">
                            Reassign <strong>{{ selectedAssignmentIds.length }}</strong> selected assignment(s).
                        </VAlert>
                    </VCol>

                    <VCol cols="12">
                        <VSelect v-model="bulkForm.collector_name" :items="collectors" label="New Collector"
                            variant="outlined" />
                    </VCol>

                    <VCol cols="12">
                        <VTextarea v-model="bulkForm.notes" label="Notes (optional)" rows="2" variant="outlined" />
                    </VCol>
                </VRow>
            </VCardText>

            <VCardActions>
                <VSpacer />
                <VBtn variant="tonal" @click="bulkDialog = false">Cancel</VBtn>
                <VBtn color="primary" :loading="bulkSaving" :disabled="!bulkForm.collector_name"
                    @click="saveBulkReassign">
                    Save
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