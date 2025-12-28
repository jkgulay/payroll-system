<template>
  <div class="virtual-scroll-wrapper">
    <!-- Virtual Scroller for large lists -->
    <RecycleScroller
      :items="items"
      :item-size="itemSize"
      :buffer="buffer"
      :key-field="keyField"
      :class="['virtual-scroller', scrollerClass]"
      :style="{ height: height }"
      v-slot="{ item, index }"
    >
      <slot :item="item" :index="index"></slot>
    </RecycleScroller>

    <!-- Empty state when no items -->
    <div v-if="items.length === 0 && showEmpty" class="empty-state pa-8 text-center">
      <v-icon size="64" color="grey-lighten-1" class="mb-4">
        {{ emptyIcon }}
      </v-icon>
      <div class="text-h6 text-grey">{{ emptyText }}</div>
    </div>
  </div>
</template>

<script setup>
import { RecycleScroller } from "vue-virtual-scroller";
import "vue-virtual-scroller/dist/vue-virtual-scroller.css";

/**
 * VirtualScrollList Component
 * 
 * High-performance virtual scrolling for large lists
 * Only renders visible items + buffer for smooth scrolling
 * 
 * @example
 * <VirtualScrollList
 *   :items="employees"
 *   :item-size="72"
 *   height="600px"
 *   key-field="id"
 * >
 *   <template #default="{ item }">
 *     <div class="employee-item">
 *       {{ item.name }}
 *     </div>
 *   </template>
 * </VirtualScrollList>
 */

defineProps({
  // Array of items to render
  items: {
    type: Array,
    required: true,
    default: () => [],
  },
  // Height of each item in pixels (for consistent sizing)
  itemSize: {
    type: Number,
    default: 60,
  },
  // Number of items to render outside visible area (for smooth scrolling)
  buffer: {
    type: Number,
    default: 10,
  },
  // Field name to use as unique key for each item
  keyField: {
    type: String,
    default: "id",
  },
  // Height of the scroller container
  height: {
    type: String,
    default: "500px",
  },
  // Additional CSS class for scroller
  scrollerClass: {
    type: String,
    default: "",
  },
  // Show empty state when no items
  showEmpty: {
    type: Boolean,
    default: true,
  },
  // Empty state icon
  emptyIcon: {
    type: String,
    default: "mdi-inbox-outline",
  },
  // Empty state text
  emptyText: {
    type: String,
    default: "No items to display",
  },
});
</script>

<style scoped lang="scss">
.virtual-scroll-wrapper {
  position: relative;
  width: 100%;
}

.virtual-scroller {
  border-radius: 8px;
  overflow-y: auto;
  overflow-x: hidden;

  // Custom scrollbar styling
  &::-webkit-scrollbar {
    width: 8px;
  }

  &::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
  }

  &::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;

    &:hover {
      background: #555;
    }
  }
}

.empty-state {
  min-height: 300px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

// Animation for smooth appearance
:deep(.vue-recycle-scroller__item-view) {
  transition: transform 0.1s ease-out;
}
</style>
