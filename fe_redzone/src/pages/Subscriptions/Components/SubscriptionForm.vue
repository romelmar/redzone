<template>
  <div class="card p-4">

    <div class="mb-3">
      <label class="form-label">Subscriber</label>
      <select v-model="form.subscriber_id" class="form-control">
        <option disabled value="">Select...</option>
        <option v-for="s in subscribers" :key="s.id" :value="s.id">
          {{ s.name }}
        </option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Plan</label>
      <select v-model="form.plan_id" class="form-control">
        <option disabled value="">Select...</option>
        <option v-for="p in plans" :key="p.id" :value="p.id">
          {{ p.name }} (₱{{ p.price }})
        </option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Start Date</label>
      <input type="date" v-model="form.start_date" class="form-control" />
    </div>

    <div class="mb-3">
      <label class="form-label">Monthly Discount</label>
      <input
        type="number"
        v-model="form.monthly_discount"
        class="form-control"
      />
    </div>

    <button class="btn btn-primary" @click="save">
      Save
    </button>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
  id: {
    type: Number,
    default: null
  }
});

const subscribers = ref([]);
const plans = ref([]);

const form = ref({
  subscriber_id: '',
  plan_id: '',
  start_date: '',
  monthly_discount: 0
});

const loadOptions = async () => {
  subscribers.value = (await axios.get('/api/subscribers')).data;
  plans.value = (await axios.get('/api/plans')).data;
};

const loadSubscription = async () => {
  if (!props.id) return;
  const res = await axios.get(`/api/subscriptions/${props.id}`);
  Object.assign(form.value, res.data.subscription);
};

const save = async () => {
  if (props.id) {
    await axios.put(`/api/subscriptions/${props.id}`, form.value);
  } else {
    await axios.post(`/api/subscribers/${form.value.subscriber_id}/subscriptions`, form.value);
  }

  emit('saved');
};

const emit = defineEmits(['saved']);

onMounted(async () => {
  await loadOptions();
  await loadSubscription();
});
</script>
