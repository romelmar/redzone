<script setup>
import { ref, onMounted, watch } from "vue";
import {
    fetchSubscribers,
    createSubscriber,
    updateSubscriber,
    deleteSubscriber,
    showSubscriber,
    searchSubscribers
} from "@/services/subscribers";
import debounce from "lodash/debounce";

// UI state
const loading = ref(false);
const dialog = ref(false);

// Table state
const page = ref(1);
const perPage = ref(10);
const totalItems = ref(0);
const subscribers = ref([]);

// Table search (triggered AFTER selecting autocomplete result)
const tableSearch = ref("");

// Autocomplete state
const searchText = ref("");               // user typing
const selectedSubscriber = ref(null);     // selected dropdown item
const searchResults = ref([]);
const searchLoading = ref(false);

// Autocomplete remote search
const onSearch = debounce(async query => {
    if (!query) {
        searchResults.value = [];
        return;
    }

    searchLoading.value = true;
    const { data } = await searchSubscribers(query);
    searchResults.value = data;
    searchLoading.value = false;
}, 300);

// Load table from API
const load = async () => {
    loading.value = true;

    const { data } = await fetchSubscribers({
        page: page.value,
        per_page: perPage.value,
        search: tableSearch.value,
    });

    subscribers.value = data.data;
    totalItems.value = data.total;

    loading.value = false;
};

// When selecting an autocomplete result
const applySearchSelection = (subscriber) => {
    if (!subscriber) {
        selectedSubscriber.value = null
        tableSearch.value = ""
        page.value = 1
        load()
        return
    }

    // subscriber is now an object → { id, name }
    tableSearch.value = subscriber.name
    searchText.value = subscriber.name
    page.value = 1
    load()
}


// Search table when tableSearch changes
watch(tableSearch, () => {
    page.value = 1;
    load();
});

// Form state
const form = ref({
    id: null,
    name: "",
    email: "",
    phone: "",
    address: "",
});

// Reset form after dialog close
watch(dialog, isOpen => {
    if (!isOpen) {
        form.value = {
            id: null,
            name: "",
            email: "",
            phone: "",
            address: "",
        };
    }
});

watch(selectedSubscriber, (id) => {
    if (!id) {
        searchText.value = "";
        return;
    }

    // Try to find in remote list first
    let match = searchResults.value.find(s => s.id === id);

    // If not found, use tableSearch (fallback)
    searchText.value = match?.name || tableSearch.value || "";
});


// CRUD
const openCreate = () => {
    form.value = {
        id: null,
        name: "",
        email: "",
        phone: "",
        address: "",
    };
    dialog.value = true;
};

const openEdit = async id => {
    const { data } = await showSubscriber(id);
    form.value = { ...data };
    dialog.value = true;
};

const save = async () => {
    if (form.value.id) await updateSubscriber(form.value.id, form.value);
    else await createSubscriber(form.value);

    dialog.value = false;
    load();
};

const remove = async item => {
    if (!confirm(`Delete subscriber "${item.name}"?`)) return;
    await deleteSubscriber(item.id);
    load();
};

const handleFocus = () => {
    const id = selectedSubscriber.value;
    if (!id) return;

    const match = searchResults.value.find(s => s.id === id);

    // Force name back into search box
    searchText.value = match?.name || tableSearch.value || "";
};


onMounted(load);
</script>



<template>
    <div class="card">

        <!-- Autocomplete Search -->
        <VAutocomplete v-model="selectedSubscriber" v-model:search="searchText" label="Search subscribers" clearable
            variant="outlined" prepend-inner-icon="mdi-magnify" :items="searchResults" item-title="name" return-object
            :loading="searchLoading" :no-filter="true" @update:search="onSearch"
            @update:modelValue="applySearchSelection" hide-details>
            <template #selection="{ item }">
                <span>{{ item?.name }}</span>
            </template>
        </VAutocomplete>



        <!-- Header -->
        <VCardTitle class="d-flex justify-space-between align-center">
            <span>Subscribers</span>
            <VBtn color="primary" @click="openCreate">Add Subscriber</VBtn>
        </VCardTitle>

        <!-- Table -->
        <div class="table-responsive text-nowrap">

            <VTable>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Subscriptions</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="s in subscribers" :key="s.id">
                        <td>{{ s.name }}</td>
                        <td>{{ s.email }}</td>
                        <td>{{ s.phone }}</td>
                        <td>{{ s.subscriptions_count }}</td>

                        <td class="text-end">
                            <VBtn size="small" variant="outlined" class="me-1" @click="openEdit(s.id)">
                                Edit
                            </VBtn>

                            <VBtn size="small" color="error" variant="outlined" @click="remove(s)">
                                Delete
                            </VBtn>
                        </td>
                    </tr>

                    <tr v-if="!loading && subscribers.length === 0">
                        <td colspan="5" class="text-center text-muted py-4">
                            No subscribers found
                        </td>
                    </tr>
                </tbody>
            </VTable>

            <!-- Pagination -->
<div class="d-flex flex-column flex-sm-row align-center justify-space-between px-4 py-3 gap-3">

  <!-- Pagination -->
  <VPagination
    v-model="page"
    :length="Math.ceil(totalItems / perPage)"
    @update:modelValue="load"
    rounded="lg"
    variant="flat"
    color="primary"
    class="pagination-sneat"
  />

  <!-- Rows per page -->
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

    <!-- Dialog Form -->
    <VDialog v-model="dialog" persistent max-width="600px">
        <VCard>
            <VCardTitle>
                <span class="text-h6">
                    {{ form.id ? "Edit Subscriber" : "New Subscriber" }}
                </span>
            </VCardTitle>

            <VCardText>
                <VRow>
                    <VCol cols="12">
                        <VTextField label="Name" v-model="form.name" />
                    </VCol>

                    <VCol cols="12">
                        <VTextField label="Email" type="email" v-model="form.email" />
                    </VCol>

                    <VCol cols="12">
                        <VTextField label="Phone" v-model="form.phone" />
                    </VCol>

                    <VCol cols="12">
                        <VTextarea label="Address" rows="2" v-model="form.address" />
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
