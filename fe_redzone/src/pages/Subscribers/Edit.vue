<template>
  <AppLayout>
    <div class="container py-4">
      <h4>Edit Subscription</h4>

      <SubscriptionForm :id="id" @saved="goBack" />
    </div>
  </AppLayout>
</template>

<script setup>
import SubscriptionForm from './Components/SubscriptionForm.vue';
import { useRouter } from 'vue-router';

const props = defineProps({ id: Number });
const router = useRouter();

const goBack = () => router.push('/subscriptions');
</script>
