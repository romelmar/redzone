<template>
  <div>
    <h3>Subscription Details</h3>
    <!-- other subscription details -->

    <button 
      class="btn btn-primary"
      @click="downloadStatement(subscription.id)">
      Print Statement of Account
    </button>
  </div>
</template>

<script setup>
import axios from "axios";

const props = defineProps({
  subscription: Object
});

const downloadStatement = async (id) => {
  try {
    const response = await axios.get(`/statements/${id}`, { responseType: 'blob' });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `Statement-${id}.pdf`);
    document.body.appendChild(link);
    link.click();
  } catch (error) {
    console.error("Error downloading statement:", error);
  }
};
</script>
