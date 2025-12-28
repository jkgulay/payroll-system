<template>
  <div class="virtual-data-table">
    <!-- Table Header -->
    <div class="table-header elevation-1">
      <v-row no-gutters class="px-4 py-2 font-weight-bold">
        <v-col
          v-for="header in headers"
          :key="header.key"
          :cols="header.cols || 'auto'"
          :class="['header-cell', header.align ? `text-${header.align}` : '']"
        >
          {{ header.title }}
        </v-col>
      </v-row>
    </div>

    <!-- Virtual Scroller for Table Rows -->
    <RecycleScroller
      :items="items"
      :item-size="itemSize"
      :buffer="buffer"
      key-field="id"
      :class="['table-scroller']"
      :style="{ height: height }"
      v-slot="{ item, index }"
    >
      <div
        :class="[
          'table-row',
          { 'table-row-hover': hoverable },
          { 'table-row-clickable': clickable },
        ]"
        @click="handleRowClick(item, index)"
      >
        <v-row no-gutters class="px-4 py-3">
          <v-col
            v-for="header in headers"
            :key="header.key"
            :cols="header.cols || 'auto'"
            :class="['row-cell', header.align ? `text-${header.align}` : '']"
          >
            <!-- Use custom slot if provided -->
            <slot
              v-if="$slots[`item.${header.key}`]"
              :name="`item.${header.key}`"
              :item="item"
              :index="index"
            ></slot>
            <!-- Otherwise display raw value -->
            <span v-else>{{ item[header.key] }}</span>
          </v-col>
        </v-row>
      </div>
    </RecycleScroller>

    <!-- Empty state -->
    <div v-if="items.length === 0" class="empty-state pa-8 text-center">
      <v-icon size="64" color="grey-lighten-1" class="mb-4">
        mdi-table-off
      </v-icon>
      <div class="text-h6 text-grey">No data available</div>
    </div>

    <!-- Loading overlay -->
    <div v-if="loading" class="loading-overlay">
      <v-progress-circular indeterminate color="primary" size="48" />
    </div>
  </div>
</template>

<script setup>
import { RecycleScroller } from "vue-virtual-scroller";
import "vue-virtual-scroller/dist/vue-virtual-scroller.css";

/**
 * VirtualDataTable Component
 * 
 * High-performance data table with virtual scrolling
 * Perfect for large datasets (1000+ rows)
 * 
 * @example
 * <VirtualDataTable
 *   :items="employees"
 *   :headers="[
 *     { key: 'name', title: 'Name' },
 *     { key: 'position', title: 'Position' },
 *     { key: 'salary', title: 'Salary', align: 'end' }
 *   ]"
 *   :item-size="60"
 *   height="600px"
 *   hoverable
 *   clickable
 *   @row-click="handleRowClick"
 * >
 *   <template #item.salary="{ item }">
 *     <v-chip color="success">
 *       {{ formatCurrency(item.salary) }}
 *     </v-chip>
 *   </template>
 * </VirtualDataTable>
 */

const props = defineProps({
  // Array of data items
  items: {
    type: Array,
    required: true,
    default: () => [],
  },
  // Table headers configuration
  headers: {
    type: Array,
    required: true,
    default: () => [],
  },
  // Height of each row in pixels
  itemSize: {
    type: Number,
    default: 60,
  },
  // Buffer items for smooth scrolling
  buffer: {
    type: Number,
    default: 10,
  },
  // Height of the table
  height: {
    type: String,
    default: "500px",
  },
  // Enable hover effect on rows
  hoverable: {
    type: Boolean,
    default: true,
  },
  // Enable click on rows
  clickable: {
    type: Boolean,
    default: false,
  },
  // Loading state
  loading: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["row-click"]);

const handleRowClick = (item, index) => {
  if (props.clickable) {
    emit("row-click", { item, index });
  }
};
</script>

<style scoped lang="scss">
.virtual-data-table {
  position: relative;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
  background: white;
}

.table-header {
  background: #f5f5f5;
  border-bottom: 2px solid #e0e0e0;
  position: sticky;
  top: 0;
  z-index: 10;

  .header-cell {
    font-size: 14px;
    color: #424242;
  }
}

.table-scroller {
  overflow-y: auto;
  overflow-x: hidden;

  &::-webkit-scrollbar {
    width: 8px;
  }

  &::-webkit-scrollbar-track {
    background: #f1f1f1;
  }

  &::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;

    &:hover {
      background: #555;
    }
  }
}

.table-row {
  border-bottom: 1px solid #e0e0e0;
  transition: background-color 0.2s ease;

  &.table-row-hover:hover {
    background-color: #f5f5f5;
  }

  &.table-row-clickable {
    cursor: pointer;
  }

  .row-cell {
    font-size: 14px;
    color: #616161;
  }
}

.empty-state {
  min-height: 300px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.8);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}
</style>
