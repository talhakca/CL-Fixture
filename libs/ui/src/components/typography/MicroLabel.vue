<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../../lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <span
    data-slot="micro-label"
    :class="
      cn('text-xs uppercase tracking-wide text-muted-foreground', props.class)
    "
  >
    <slot />
  </span>
</template>
