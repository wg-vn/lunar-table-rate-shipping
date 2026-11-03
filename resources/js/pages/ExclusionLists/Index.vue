<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button, DataTable, Dialog, FieldLabel, PageEmpty, Pagination, SettingsShell, TextInput } from '@lunarphp/panel';

// SettingsShell is the page's whole chrome, so the auto-applied PanelLayout is opted out of.
defineOptions({ layout: (_h: unknown, page: unknown) => page });

type List = { id: number; name: string; exclusions_count: number; urls: { edit: string } };

const props = defineProps<{
    lists: { data: List[] } & Record<string, unknown>;
    columns: { key: string; label: string; width?: string; align?: 'left' | 'right' | 'center' }[];
    tableActions: Record<string, unknown>[];
    urls: { index: string; store: string };
}>();

const { t } = useI18n();

const creating = ref(false);
const form = useForm({ name: '' });

const submit = (): void => {
    form.post(props.urls.store, { onSuccess: () => (creating.value = false) });
};
</script>

<template>
    <SettingsShell :title="t('lunar-shipping::panel.exclusion_lists.title')" :description="t('lunar-shipping::panel.exclusion_lists.description')" wide>
        <template #actions>
            <Button variant="primary" icon="plus" size="sm" @click="form.reset(); form.clearErrors(); creating = true">
                {{ t('lunar-shipping::panel.exclusion_lists.create') }}
            </Button>
        </template>

        <DataTable :columns="columns" :rows="lists.data" :row-to="(row) => (row as unknown as List).urls.edit" :row-actions="tableActions">
            <template #cell-name="{ row }">
                <span class="text-[12.5px] text-ink-900 font-medium">{{ (row as unknown as List).name }}</span>
            </template>
            <template #empty>
                <PageEmpty :title="t('lunar-shipping::panel.exclusion_lists.empty_title')" />
            </template>
        </DataTable>

        <div class="mt-4"><Pagination :meta="lists" /></div>
    </SettingsShell>

    <Dialog v-model:open="creating" :title="t('lunar-shipping::panel.exclusion_lists.create')" :description="t('lunar-shipping::panel.exclusion_lists.create_description')">
        <div>
            <FieldLabel required>{{ t('lunar-shipping::panel.exclusion_lists.field_name') }}</FieldLabel>
            <TextInput v-model="form.name" :invalid="!!form.errors.name" @keyup.enter="submit" />
            <div v-if="form.errors.name" class="mt-1 text-[11px] text-danger">{{ form.errors.name }}</div>
        </div>
        <template #footer>
            <Button variant="ghost" @click="creating = false">{{ t('common.cancel') }}</Button>
            <Button variant="primary" :disabled="form.processing" @click="submit">{{ t('common.create') }}</Button>
        </template>
    </Dialog>
</template>
