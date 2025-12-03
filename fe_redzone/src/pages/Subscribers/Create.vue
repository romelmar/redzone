<template>
  <AppLayout>
    <div class="container py-4">
      <h4 class="mb-3">Create Subscription</h4>

      <SubscriptionForm @saved="goBack" />
    </div>
  </AppLayout>
</template>

<script setup>
import SubscriptionForm from './Components/SubscriptionForm.vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const goBack = () => router.push('/subscriptions');
</script>
