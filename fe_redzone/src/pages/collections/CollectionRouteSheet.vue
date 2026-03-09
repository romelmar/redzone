<script setup>
import { ref, onMounted } from "vue"
import api from "@/plugins/axios"

const loading = ref(false)
const rows = ref([])

const assignmentDate = ref(new Date().toISOString().slice(0,10))
const collectorName = ref("")

const load = async () => {

  loading.value = true

  const res = await api.get("/api/collection-route",{
    params:{
      assignment_date: assignmentDate.value,
      collector_name: collectorName.value
    }
  })

  rows.value = res.data

  loading.value = false
}

const downloadPdf = async () => {

  const response = await api.get("/api/collection-route/pdf",{
    params:{
      assignment_date: assignmentDate.value,
      collector_name: collectorName.value
    },
    responseType:"blob"
  })

  const blob = new Blob([response.data],{type:"application/pdf"})
  const url = window.URL.createObjectURL(blob)

  const a = document.createElement("a")
  a.href = url
  a.download = "collection-route.pdf"
  a.click()

  window.URL.revokeObjectURL(url)
}

onMounted(load)
</script>

<template>

<div class="card">

<div class="card-header d-flex justify-space-between align-center">

<h5>Collection Route Sheet</h5>

<div class="d-flex gap-2">

<VTextField
v-model="assignmentDate"
type="date"
label="Date"
/>

<VTextField
v-model="collectorName"
label="Collector"
/>

<VBtn
color="primary"
@click="load"
>
Load
</VBtn>

<VBtn
color="success"
@click="downloadPdf"
>
Export PDF
</VBtn>

</div>

</div>

<div class="table-responsive text-nowrap">

<VTable>

<thead>

<tr>
<th>#</th>
<th>Subscriber</th>
<th>Plan</th>
<th>Amount Due</th>
<th>Phone</th>
<th>Address</th>
<th>Notes</th>
</tr>

</thead>

<tbody>

<tr
v-for="(r,i) in rows"
:key="i"
>

<td>{{ i+1 }}</td>
<td>{{ r.subscriber_name }}</td>
<td>{{ r.plan }} {{ r.speed }} Mbps</td>
<td><strong>₱{{ Number(r.amount_due).toFixed(2) }}</strong></td>
<td>{{ r.phone }}</td>
<td>{{ r.address }}</td>
<td>{{ r.notes }}</td>

</tr>

<tr v-if="rows.length===0">

<td colspan="7" class="text-center text-muted">
No assigned accounts
</td>

</tr>

</tbody>

</VTable>

</div>

</div>

</template>