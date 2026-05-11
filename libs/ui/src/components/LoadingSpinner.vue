<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next';

interface Props {
  label?: string;
}

withDefaults(defineProps<Props>(), { label: 'Loading…' });
</script>

<template>
  <div class="flex items-center gap-2 text-sm text-muted-foreground">
    <Loader2 class="size-4 animate-spin" />
    <span>{{ label }}</span>
  </div>
</template>
