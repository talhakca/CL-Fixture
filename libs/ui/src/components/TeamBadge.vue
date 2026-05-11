<script setup lang="ts">
import { Shirt } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
  name: string;
  /**
   * Icon-only mode for compact UIs. Default false.
   */
  iconOnly?: boolean;
}

interface Kit {
  /** Tailwind `text-{color}` — sets the SVG stroke (icon outline). */
  stroke: string;
  /** Tailwind `fill-{color}` — sets the SVG body fill. */
  fill: string;
}

const KITS: Record<string, Kit> = {
  PSG: { stroke: 'text-blue-700', fill: 'fill-white' },
  Bayern: { stroke: 'text-red-800', fill: 'fill-red-500' },
  Arsenal: { stroke: 'text-red-700', fill: 'fill-amber-100' },
  Atletico: { stroke: 'text-red-700', fill: 'fill-blue-700' },
};

const DEFAULT_KIT: Kit = {
  stroke: 'text-muted-foreground',
  fill: 'fill-transparent',
};

const props = withDefaults(defineProps<Props>(), { iconOnly: false });

const kit = computed((): Kit => KITS[props.name] ?? DEFAULT_KIT);
</script>

<template>
  <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
    <Shirt :class="['size-4 shrink-0', kit.stroke, kit.fill]" />
    <span
      v-if="!iconOnly"
      class="font-medium"
    >{{ name }}</span>
  </span>
</template>
