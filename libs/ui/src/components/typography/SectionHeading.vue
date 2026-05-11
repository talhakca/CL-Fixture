<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '../../lib/utils';

const props = defineProps<{
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <h2
    data-slot="section-heading"
    :class="
      cn(
        'text-xs font-semibold uppercase tracking-wide text-muted-foreground',
        props.class,
      )
    "
  >
    <slot />
  </h2>
</template>
