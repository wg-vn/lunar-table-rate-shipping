<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button, Combobox, ConfirmDialog, DatePicker, FieldLabel, Select, SettingsShell, SideCard, Textarea, TextInput, Toggle } from '@lunarphp/panel';

// SettingsShell is the page's whole chrome, so the auto-applied PanelLayout is opted out of.
defineOptions({ layout: (_h: unknown, page: unknown) => page });

type Day = { enabled: boolean; from: string | null; to: string | null };
type GroupAvailability = { customer_group_id: number; enabled: boolean; visible: boolean; starts_at: string | null; ends_at: string | null };

const props = defineProps<{
    method: {
        id: number;
        name: string;
        code: string;
        driver: string;
        description: string | null;
        enabled: boolean;
        stock_available: boolean;
        charge_by: string;
        weight_unit: string | null;
        min_weight: number | null;
        max_weight: number | null;
        schedule: Record<string, Day> | null;
        customer_groups: GroupAvailability[];
    };
    drivers: string[];
    weightUnits: string[];
    customerGroups: { id: number; name: string }[];
    urls: { index: string; update: string; destroy: string };
}>();

const { t } = useI18n();
const tr = (key: string, params?: Record<string, unknown>): string => t(`lunar-shipping::panel.methods.${key}`, params ?? {});

const days = ['1', '2', '3', '4', '5', '6', '7'];

const breadcrumbs = computed(() => [
    { label: t('nav.settings') },
    { label: tr('title'), href: props.urls.index },
    { label: props.method.name, current: true },
]);

// A full week, seeded from the stored schedule; sent only while "use a weekly schedule" is on.
const week = Object.fromEntries(days.map((day) => [day, { enabled: true, from: null, to: null, ...(props.method.schedule?.[day] ?? {}) }]));

const useSchedule = ref(props.method.schedule !== null);

const form = useForm({
    name: props.method.name,
    code: props.method.code,
    driver: props.method.driver,
    description: props.method.description ?? '',
    enabled: props.method.enabled,
    stock_available: props.method.stock_available,
    charge_by: props.method.charge_by,
    weight_unit: props.method.weight_unit ?? '',
    min_weight: props.method.min_weight,
    max_weight: props.method.max_weight,
    schedule: week as Record<string, Day>,
    customer_groups: props.method.customer_groups.map((group) => ({ ...group })),
});

const submit = (): void => {
    form
        .transform((data) => ({
            ...data,
            weight_unit: data.weight_unit || null,
            min_weight: data.weight_unit ? data.min_weight : null,
            max_weight: data.weight_unit ? data.max_weight : null,
            schedule: useSchedule.value ? data.schedule : null,
        }))
        .put(props.urls.update, { preserveScroll: true });
};

const groupName = (id: number): string => props.customerGroups.find((group) => group.id === id)?.name ?? String(id);

const availableGroups = computed(() =>
    props.customerGroups
        .filter((group) => !form.customer_groups.some((row) => row.customer_group_id === group.id))
        .map((group) => ({ value: group.id, label: group.name })));

const addGroup = (id: string | number): void => {
    form.customer_groups.push({ customer_group_id: Number(id), enabled: true, visible: true, starts_at: null, ends_at: null });
};

const noGroupEnabled = computed(() => !form.customer_groups.some((group) => group.enabled));

const error = (key: string): string | undefined => (form.errors as Record<string, string>)[key];

const deleting = ref(false);
</script>

<template>
    <SettingsShell :title="tr('edit_title', { name: method.name })" :breadcrumbs="breadcrumbs" wide>
        <template #actions>
            <Button variant="ghost" icon="trash" @click="deleting = true">{{ t('common.delete') }}</Button>
            <Button variant="primary" icon="check" size="sm" :disabled="form.processing" @click="submit">{{ t('common.save') }}</Button>
        </template>

        <div v-if="noGroupEnabled" class="mb-4 px-3.5 py-2.5 rounded-lg border border-warn/40 bg-warn/10 text-xs text-ink-900">
            {{ tr('no_customer_groups_warning') }}
        </div>

        <SideCard :title="tr('section_details')">
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <FieldLabel required>{{ tr('field_name') }}</FieldLabel>
                    <TextInput v-model="form.name" :invalid="!!form.errors.name" />
                    <div v-if="form.errors.name" class="mt-1 text-[11px] text-danger">{{ form.errors.name }}</div>
                </div>
                <div>
                    <FieldLabel required>{{ tr('field_code') }}</FieldLabel>
                    <TextInput v-model="form.code" mono :invalid="!!form.errors.code" />
                    <div class="mt-1 text-[11px]" :class="form.errors.code ? 'text-danger' : 'text-ink-500'">{{ form.errors.code ?? tr('code_hint') }}</div>
                </div>
                <div>
                    <FieldLabel>{{ tr('field_driver') }}</FieldLabel>
                    <Select v-model="form.driver">
                        <option v-for="driver in drivers" :key="driver" :value="driver">{{ tr(`driver_${driver}`) }}</option>
                    </Select>
                </div>
                <div v-if="form.driver === 'ship-by'">
                    <FieldLabel>{{ tr('field_charge_by') }}</FieldLabel>
                    <Select v-model="form.charge_by">
                        <option value="cart_total">{{ tr('charge_by_cart_total') }}</option>
                        <option value="weight">{{ tr('charge_by_weight') }}</option>
                    </Select>
                </div>
                <div class="sm:col-span-2">
                    <FieldLabel>{{ tr('field_description') }}</FieldLabel>
                    <Textarea v-model="form.description" :rows="3" />
                </div>
                <label class="flex items-center gap-3 cursor-pointer">
                    <Toggle :on="form.enabled" @toggle="form.enabled = !form.enabled" />
                    <span class="text-[12.5px] text-ink-900 font-medium">{{ tr('field_enabled') }}</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer">
                    <Toggle :on="form.stock_available" @toggle="form.stock_available = !form.stock_available" />
                    <span class="text-[12.5px] text-ink-900 font-medium">{{ tr('field_stock_available') }}</span>
                </label>
            </div>
        </SideCard>

        <SideCard :title="tr('section_weight')">
            <p class="mt-0 mb-3 text-xs text-ink-500">{{ tr('weight_desc') }}</p>
            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <FieldLabel>{{ tr('field_weight_unit') }}</FieldLabel>
                    <Select v-model="form.weight_unit">
                        <option value="">{{ t('lunar-shipping::panel.common.none') }}</option>
                        <option v-for="unit in weightUnits" :key="unit" :value="unit">{{ unit }}</option>
                    </Select>
                </div>
                <div>
                    <FieldLabel>{{ tr('field_min_weight') }}</FieldLabel>
                    <TextInput v-model="form.min_weight" type="number" min="0" step="any" :disabled="!form.weight_unit" :invalid="!!form.errors.min_weight" />
                    <div v-if="form.errors.min_weight" class="mt-1 text-[11px] text-danger">{{ form.errors.min_weight }}</div>
                </div>
                <div>
                    <FieldLabel>{{ tr('field_max_weight') }}</FieldLabel>
                    <TextInput v-model="form.max_weight" type="number" min="0" step="any" :disabled="!form.weight_unit" :invalid="!!form.errors.max_weight" />
                    <div v-if="form.errors.max_weight" class="mt-1 text-[11px] text-danger">{{ form.errors.max_weight }}</div>
                </div>
            </div>
        </SideCard>

        <SideCard :title="tr('section_schedule')">
            <label class="flex items-center gap-3 mb-3 cursor-pointer">
                <Toggle :on="useSchedule" @toggle="useSchedule = !useSchedule" />
                <div>
                    <div class="text-[12.5px] text-ink-900 font-medium">{{ tr('use_schedule') }}</div>
                    <div class="text-[11px] text-ink-500">{{ tr('schedule_desc') }}</div>
                </div>
            </label>

            <div v-if="useSchedule" class="flex flex-col gap-2">
                <div v-for="day in days" :key="day" class="grid grid-cols-[130px_120px_120px] items-start gap-3">
                    <label class="flex items-center gap-2 pt-1.5 cursor-pointer">
                        <Toggle :on="form.schedule[day].enabled" @toggle="form.schedule[day].enabled = !form.schedule[day].enabled" />
                        <span class="text-[12.5px] text-ink-900">{{ tr(`day_${day}`) }}</span>
                    </label>
                    <TextInput v-model="form.schedule[day].from" type="time" :aria-label="tr('from')" :disabled="!form.schedule[day].enabled" />
                    <div>
                        <TextInput v-model="form.schedule[day].to" type="time" :aria-label="tr('to')" :disabled="!form.schedule[day].enabled" :invalid="!!error(`schedule.${day}.to`)" />
                        <div v-if="error(`schedule.${day}.to`)" class="mt-1 text-[11px] text-danger">{{ error(`schedule.${day}.to`) }}</div>
                    </div>
                </div>
            </div>
        </SideCard>

        <SideCard :title="tr('section_availability')">
            <p class="mt-0 mb-3 text-xs text-ink-500">{{ tr('availability_desc') }}</p>

            <div v-for="(group, index) in form.customer_groups" :key="group.customer_group_id" class="grid sm:grid-cols-[1fr_auto_auto_150px_150px_auto] items-center gap-3 mb-2">
                <span class="text-[12.5px] text-ink-900 font-medium">{{ groupName(group.customer_group_id) }}</span>
                <label class="flex items-center gap-2 cursor-pointer">
                    <Toggle :on="group.enabled" @toggle="group.enabled = !group.enabled" />
                    <span class="text-xs text-ink-700">{{ tr('field_enabled') }}</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <Toggle :on="group.visible" @toggle="group.visible = !group.visible" />
                    <span class="text-xs text-ink-700">{{ tr('field_visible') }}</span>
                </label>
                <DatePicker :model-value="group.starts_at ?? ''" :aria-label="tr('field_starts_at')" @update:model-value="group.starts_at = $event || null" />
                <div>
                    <DatePicker :model-value="group.ends_at ?? ''" :aria-label="tr('field_ends_at')" :invalid="!!error(`customer_groups.${index}.ends_at`)" @update:model-value="group.ends_at = $event || null" />
                    <div v-if="error(`customer_groups.${index}.ends_at`)" class="mt-1 text-[11px] text-danger">{{ error(`customer_groups.${index}.ends_at`) }}</div>
                </div>
                <Button variant="ghost" size="sm" icon="trash" :aria-label="t('lunar-shipping::panel.common.remove')" @click="form.customer_groups.splice(index, 1)" />
            </div>

            <div class="max-w-[340px] mt-2">
                <Combobox :options="availableGroups" :placeholder="tr('add_customer_group')" @change="addGroup" />
            </div>
        </SideCard>
    </SettingsShell>

    <ConfirmDialog
        v-model:open="deleting"
        :title="t('common.delete')"
        :description="t('lunar-shipping::panel.common.confirm_delete')"
        :confirm-label="t('common.delete')"
        tone="danger"
        @confirm="router.delete(urls.destroy)"
    />
</template>
