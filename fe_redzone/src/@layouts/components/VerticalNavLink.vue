<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const active = computed(() => {
  if (!props.item.to) return false
  const [path, query] = props.item.to.split('?')
  return route.path === path && (!query || [...new URLSearchParams(query)].every(([key, value]) => route.query[key] === value))
})
const props = defineProps({
  item: {
    type: null,
    required: true,
  },
})
</script>

<template>
  <li
    class="nav-link"
    :class="{ disabled: item.disable }"
  >
    <Component
      :is="item.to ? 'RouterLink' : 'a'"
      :to="item.to"
      active-class="" exact-active-class=""
      :class="{ 'router-link-exact-active': active }"
      :aria-current="active ? 'page' : undefined"
      :href="item.href"
    >
      <VIcon
        :icon="item.icon"
        class="nav-item-icon"
      />
      <!-- 👉 Title -->
      <span class="nav-item-title">
        {{ item.title }}
      </span>
    </Component>
  </li>
</template>

<style lang="scss">
.layout-vertical-nav {
  .nav-link a {
    display: flex;
    align-items: center;
    cursor: pointer;
  }
}
</style>
