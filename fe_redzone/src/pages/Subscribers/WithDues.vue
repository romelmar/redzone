<template>
  <v-container>
    <h1>Subscribers with Dues</h1>
    <v-row v-for="subscriber in subscribers" :key="subscriber.id" class="mb-4">
      <v-col>
        <v-card>
          <v-card-title>{{ subscriber.name }}</v-card-title>
          <v-card-text>
            <div v-for="subscription in subscriber.subscriptions" :key="subscription.id">
              <p><strong>Plan:</strong> {{ subscription.plan.name }}</p>
              <v-list dense>
                <v-list-item v-for="detail in subscription.monthlyDetails" :key="detail.month">
                  {{ detail.month }} — Due: {{ detail.due }} — Balance: {{ detail.balance }}
                </v-list-item>
              </v-list>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'

const subscribers = ref([])

onMounted(async () => {
  const response = await axios.get('/api/dues')
  subscribers.value = response.data
})
</script>
