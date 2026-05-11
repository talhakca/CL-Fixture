<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../../lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <h1
    data-slot="page-title"
    :class="cn('text-2xl md:text-3xl font-bold tracking-tight', props.class)"
  >
    <slot />
  </h1>
</template>
