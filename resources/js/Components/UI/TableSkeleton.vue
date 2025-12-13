<script setup>
const props = defineProps({
    rows: {
        type: Number,
        default: 5
    },
    columns: {
        type: Number,
        default: 4
    }
});
</script>

<template>
    <div class="overflow-x-auto">
        <table class="data-table">
            <!-- Table Header Skeleton -->
            <thead>
                <tr>
                    <th v-for="col in columns" :key="`header-${col}`">
                        <div class="shimmer h-4 w-24 rounded"></div>
                    </th>
                </tr>
            </thead>

            <!-- Table Body Skeleton -->
            <tbody>
                <tr v-for="row in rows" :key="`row-${row}`">
                    <td v-for="col in columns" :key="`cell-${row}-${col}`">
                        <div class="shimmer h-5 rounded" :style="{ width: getRandomWidth() }"></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script>
export default {
    methods: {
        getRandomWidth() {
            // Generate random widths for more realistic skeleton
            const widths = ['60%', '70%', '80%', '90%', '100%'];
            return widths[Math.floor(Math.random() * widths.length)];
        }
    }
};
</script>
