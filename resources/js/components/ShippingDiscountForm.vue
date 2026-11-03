<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import { Button, FieldLabel, Select, TextInput } from '@lunarphp/panel';

// The shipping discount's form in the panel's discount editor (ShippingDiscountForm on the server).
type Rule = { shipping_method_id: number | null; type: 'fixed' | 'percentage'; percentage: number | string | null; prices: Record<string, number | string | null> };
type Currency = { id: number; code: string; decimal_places: number; default: boolean };

const props = defineProps<{
    modelValue: Record<string, unknown>;
    currencies: Currency[];
    errors?: Record<string, string>;
}>();

const emit = defineEmits<{ 'update:modelValue': [Record<string, unknown>] }>();

const { t } = useI18n();
const tr = (key: string): string => t(`lunar-shipping::panel.discount.${key}`);

const rules = (): Rule[] => ((props.modelValue.methods as Rule[] | undefined) ?? []).map((rule) => ({ ...rule, prices: { ...rule.prices } }));
const methods = (): { id: number; name: string }[] => (props.modelValue.shipping_methods as { id: number; name: string }[] | undefined) ?? [];

const update = (next: Rule[]): void => emit('update:modelValue', { ...props.modelValue, methods: next });

const setField = (index: number, field: keyof Rule, value: unknown): void => {
    const next = rules();
    next[index] = { ...next[index], [field]: value };
    update(next);
};

const setPrice = (index: number, code: string, value: unknown): void => {
    const next = rules();
    next[index].prices = { ...next[index].prices, [code]: value as string };
    update(next);
};

const addRule = (): void => update([...rules(), { shipping_method_id: null, type: 'fixed', percentage: null, prices: {} }]);

const removeRule = (index: number): void => update(rules().filter((_, position) => position !== index));

const error = (key: string): string | undefined => props.errors?.[`data.${key}`];
</script>

<template>
    <div>
        <div class="flex items-center justify-between mb-2">
            <FieldLabel required>{{ tr('rules') }}</FieldLabel>
            <Button size="sm" icon="plus" @click="addRule">{{ tr('add_rule') }}</Button>
        </div>
        <div v-if="!rules().length" class="text-xs" :class="error('methods') ? 'text-danger' : 'text-ink-500'">{{ error('methods') ?? tr('no_rules') }}</div>

        <div v-for="(rule, index) in rules()" :key="index" class="mb-3 p-3 border border-line rounded-lg">
            <div class="grid sm:grid-cols-[1fr_1fr_auto] gap-2 items-end">
                <div>
                    <FieldLabel>{{ tr('field_method') }}</FieldLabel>
                    <Select :model-value="rule.shipping_method_id" @update:model-value="setField(index, 'shipping_method_id', $event)">
                        <option :value="null">{{ tr('all_methods') }}</option>
                        <option v-for="method in methods()" :key="method.id" :value="method.id">{{ method.name }}</option>
                    </Select>
                </div>
                <div>
                    <FieldLabel>{{ tr('field_type') }}</FieldLabel>
                    <Select :model-value="rule.type" @update:model-value="setField(index, 'type', $event)">
                        <option value="fixed">{{ tr('type_fixed') }}</option>
                        <option value="percentage">{{ tr('type_percentage') }}</option>
                    </Select>
                </div>
                <Button variant="ghost" size="sm" icon="trash" :aria-label="tr('remove_rule')" @click="removeRule(index)" />
            </div>

            <div v-if="rule.type === 'percentage'" class="mt-2 max-w-[200px]">
                <FieldLabel required>{{ tr('field_percentage') }}</FieldLabel>
                <TextInput
                    :model-value="rule.percentage ?? ''"
                    type="number"
                    min="0"
                    max="100"
                    step="any"
                    :invalid="!!error(`methods.${index}.percentage`)"
                    @update:model-value="setField(index, 'percentage', $event)"
                />
                <div v-if="error(`methods.${index}.percentage`)" class="mt-1 text-[11px] text-danger">{{ error(`methods.${index}.percentage`) }}</div>
            </div>

            <div v-else class="mt-2">
                <FieldLabel>{{ tr('field_prices') }}</FieldLabel>
                <div class="grid sm:grid-cols-3 gap-2">
                    <TextInput
                        v-for="currency in currencies"
                        :key="currency.code"
                        :model-value="rule.prices[currency.code] ?? ''"
                        type="number"
                        min="0"
                        step="any"
                        @update:model-value="setPrice(index, currency.code, $event)"
                    >
                        <template #prefix><span class="text-[11px] font-mono">{{ currency.code }}</span></template>
                    </TextInput>
                </div>
            </div>
        </div>
    </div>
</template>
