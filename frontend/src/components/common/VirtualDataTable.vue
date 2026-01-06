<template>
  <div class="virtual-data-table">
    <!-- Header -->
    <v-card>
      <v-card-text class="pa-0">
        <v-table density="compact">
          <thead>
            <tr>
              <th
                v-for="header in headers"
                :key="header.key"
                :class="{
                  'text-center': header.align === 'center',
                  'text-right': header.align === 'right',
                  'cursor-pointer': header.sortable !== false,
                }"
                @click="header.sortable !== false && sortBy(header.key)"
              >
                {{ header.title }}
                <v-icon
                  v-if="header.sortable !== false && sortKey === header.key"
                  size="small"
                  class="ml-1"
                >
                  {{ sortOrder === 'asc' ? 'mdi-arrow-up' : 'mdi-arrow-down' }}
                </v-icon>
              </th>
            </tr>
          </thead>
        </v-table>
      </v-card-text>

      <!-- Virtual Scroller for Body -->
      <v-card-text class="pa-0" style="max-height: 600px; overflow-y: auto">
        <RecycleScroller
          class="scroller"
          :items="sortedItems"
          :item-size="itemHeight"
          key-field="id"
          v-slot="{ item, index }"
        >
          <v-table density="compact">
            <tbody>
              <tr
                :class="{
                  'row-hover': true,
                  'bg-grey-lighten-4': index % 2 === 0,
                }"
                @click="$emit('row-click', item)"
              >
                <td
                  v-for="header in headers"
                  :key="header.key"
                  :class="{
                    'text-center': header.align === 'center',
                    'text-right': header.align === 'right',
                  }"
                >
                  <slot
                    :name="`item.${header.key}`"
                    :item="item"
                    :value="getNestedValue(item, header.key)"
                  >
                    {{ getNestedValue(item, header.key) }}
                  </slot>
                </td>
              </tr>
            </tbody>
          </v-table>
        </RecycleScroller>
      </v-card-text>

      <!-- Loading State -->
      <v-card-text v-if="loading" class="text-center pa-8">
        <v-progress-circular indeterminate color="primary"></v-progress-circular>
        <p class="mt-4">Loading data...</p>
      </v-card-text>

      <!-- Empty State -->
      <v-card-text v-if="!loading && items.length === 0" class="text-center pa-8">
        <v-icon size="64" color="grey">mdi-database-off</v-icon>
        <p class="text-h6 mt-4">No data available</p>
      </v-card-text>

      <!-- Footer with pagination info -->
      <v-card-text v-if="items.length > 0" class="text-center text-caption">
        Showing {{ items.length }} items
        <span v-if="totalItems">({{ totalItems }} total)</span>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { RecycleScroller } from 'vue-virtual-scroller';
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css';

const props = defineProps({
  headers: {
    type: Array,
    required: true,
  },
  items: {
    type: Array,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  itemHeight: {
    type: Number,
    default: 52, // Default height for each row in pixels
  },
  totalItems: {
    type: Number,
    default: null,
  },
});

defineEmits(['row-click']);

const sortKey = ref('');
const sortOrder = ref('asc');

const sortedItems = computed(() => {
  if (!sortKey.value) return props.items;

  return [...props.items].sort((a, b) => {
    const aVal = getNestedValue(a, sortKey.value);
    const bVal = getNestedValue(b, sortKey.value);

    if (aVal === bVal) return 0;

    const result = aVal < bVal ? -1 : 1;
    return sortOrder.value === 'asc' ? result : -result;
  });
});

function sortBy(key) {
  if (sortKey.value === key) {
    sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
  } else {
    sortKey.value = key;
    sortOrder.value = 'asc';
  }
}

function getNestedValue(obj, path) {
  return path.split('.').reduce((acc, part) => acc && acc[part], obj);
}
</script>

<style scoped>
.virtual-data-table {
  width: 100%;
}

.scroller {
  height: 600px;
}

.row-hover {
  cursor: pointer;
  transition: background-color 0.2s;
}

.row-hover:hover {
  background-color: rgba(0, 0, 0, 0.04) !important;
}

.cursor-pointer {
  cursor: pointer;
  user-select: none;
}

th {
  font-weight: 600;
  position: sticky;
  top: 0;
  background: white;
  z-index: 1;
}
</style>
