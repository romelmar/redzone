<script setup>
import { useToastStore } from '@/stores/toast';
import { hexToRgb } from '@layouts/utils';
import { useTheme } from 'vuetify';
const { global } = useTheme()

import { useAuthStore } from '@/stores/auth';
const toast = useToastStore()
const auth = useAuthStore()
</script>

<template>
  <div>

    <VApp :style="`--v-global-theme-primary: ${hexToRgb(global.current.value.colors.primary)}`">
      <v-overlay :model-value="auth.loading" persistent>
        <v-progress-circular indeterminate size="64" width="5" color="primary"></v-progress-circular>
      </v-overlay>
      <RouterView />
    </VApp>

    <!-- Global Snackbar -->
    <v-snackbar v-model="toast.visible" :color="toast.color" :timeout="toast.timeout" location="top right">
      {{ toast.message }}
    </v-snackbar>
  </div>

</template>
