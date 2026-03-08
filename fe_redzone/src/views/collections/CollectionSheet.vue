<script setup>
import { ref, onMounted, watch } from "vue"
import debounce from "lodash/debounce"
import api from "@/plugins/axios"
import { formatIsoToReadable } from "@/helpers/dateUtils"

const loading = ref(false)
const rows = ref([])

const page = ref(1)
const perPage = ref(10)
const totalItems = ref(0)

const search = ref("")
const typeFilter = ref("due")

const filterOptions = [
  { label: "Due", value: "due" },
  { label: "Overdue", value: "overdue" },
  { label: "Disconnected Clients", value: "disconnected" },
]

const money = (v) => Number(v ?? 0).toFixed(2)

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get("/api/collection-sheet", {
      params: {
        page: page.value,
        per_page: perPage.value,
        search: search.value || undefined,
        type: typeFilter.value || undefined,
      },
    })

    rows.value = data.data ?? []
    totalItems.value = data.total ?? 0
  } finally {
    loading.value = false
  }
}

const debouncedLoad = debounce(() => {
  page.value = 1
  load()
}, 350)

watch(search, debouncedLoad)
watch(typeFilter, () => {
  page.value = 1
  load()
})

onMounted(load)
</script>

<template>
  <div class="card">
    <VCardTitle class="d-flex flex-column flex-md-row align-center justify-space-between gap-3">
      <span>Collection Sheet</span>

      <div class="d-flex flex-wrap gap-3 align-center">
        <VTextField
          v-model="search"
          label="Search subscriber / plan"
          variant="outlined"
          density="comfortable"
          prepend-inner-icon="mdi-magnify"
          clearable
          hide-details
          style="min-width: 260px"
        />

        <VSelect
          v-model="typeFilter"
          :items="filterOptions"
          item-title="label"
          item-value="value"
          label="Collection Type"
          variant="outlined"
          density="comfortable"
          hide-details
          style="min-width: 220px"
        />
      </div>
    </VCardTitle>

    <div class="table-responsive text-nowrap">
      <VTable>
        <thead>
          <tr>
            <th>Subscriber</th>
            <th>Plan</th>
            <th>Due Date</th>
            <th>Days Overdue</th>
            <th>Status</th>
            <th>Amount Due</th>
            <th>Contact</th>
            <th>Address</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="row in rows" :key="row.subscription_id">
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

            <td>
              <span v-if="row.days_overdue > 0">{{ row.days_overdue }}</span>
              <span v-else>0</span>
            </td>

            <td>
              <VChip
                size="small"
                :color="
                  row.collection_type === 'overdue'
                    ? 'error'
                    : row.collection_type === 'disconnected'
                    ? 'secondary'
                    : 'warning'
                "
              >
                {{ row.collection_type }}
              </VChip>
            </td>

            <td>
              <strong>₱{{ money(row.total_due) }}</strong>
            </td>

            <td>{{ row.subscriber_phone ?? "—" }}</td>
            <td>{{ row.subscriber_address ?? "—" }}</td>
          </tr>

          <tr v-if="!loading && rows.length === 0">
            <td colspan="8" class="text-center text-muted py-4">
              No records found
            </td>
          </tr>
        </tbody>
      </VTable>

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
  </div>
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