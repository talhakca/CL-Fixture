<script setup lang="ts">
import { AlertCircle, X } from 'lucide-vue-next';
import { Button } from './ui/button';

interface Props {
  message: string;
}

defineProps<Props>();

const emit = defineEmits<{
  retry: [];
  dismiss: [];
}>();
</script>

<template>
  <div
    class="flex items-start gap-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm"
    role="alert"
  >
    <AlertCircle class="mt-0.5 size-4 text-destructive" />
    <p class="flex-1 text-destructive">
      {{ message }}
    </p>
    <Button
      variant="ghost"
      size="sm"
      @click="emit('retry')"
    >
      Retry
    </Button>
    <Button
      variant="ghost"
      size="icon-sm"
      aria-label="Dismiss"
      @click="emit('dismiss')"
    >
      <X class="size-4" />
    </Button>
  </div>
</template>
