<script setup lang="ts">
import { computed } from 'vue';
import { Combobox } from '@lunarphp/panel';

// A set of ids shown as removable chips, with a combobox to add any option not already chosen.
const props = defineProps<{
    modelValue: number[];
    options: { value: number; label: string }[];
    placeholder: string;
    emptyText: string;
    removeLabel: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: number[]] }>();

const labelFor = (id: number): string => props.options.find((option) => option.value === id)?.label ?? String(id);

const available = computed(() => props.options.filter((option) => !props.modelValue.includes(option.value)));
</script>

<template>
    <div>
        <div class="flex flex-wrap gap-1.5 mb-3">
            <span
                v-for="id in modelValue"
                :key="id"
                class="inline-flex items-center gap-1.5 pl-2.5 pr-1.5 py-1 rounded-full bg-surface-2 border border-line text-xs text-ink-900"
            >
                {{ labelFor(id) }}
                <button
                    type="button"
                    class="w-4 h-4 inline-flex items-center justify-center rounded-full text-ink-500 hover:text-danger"
                    :aria-label="removeLabel"
                    @click="emit('update:modelValue', modelValue.filter((value) => value !== id))"
                >
                    ×
                </button>
            </span>
            <span v-if="!modelValue.length" class="text-xs text-ink-500">{{ emptyText }}</span>
        </div>
        <div class="max-w-[340px]">
            <Combobox
                :options="available"
                :placeholder="placeholder"
                @change="(value) => emit('update:modelValue', [...modelValue, Number(value)])"
            />
        </div>
    </div>
</template>
