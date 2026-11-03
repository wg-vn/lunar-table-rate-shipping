<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Button, Combobox, ConfirmDialog, FieldLabel, Select, SettingsShell, SideCard, Textarea, TextInput, Toggle } from '@lunarphp/panel';
import ChipPicker from '../../components/ChipPicker.vue';

// SettingsShell is the page's whole chrome, so the auto-applied PanelLayout is opted out of.
defineOptions({ layout: (_h: unknown, page: unknown) => page });

type Tier = { customer_group_id: number | null; currency_id: number; price: number | string; min_quantity: number | string };
type Rate = { id: number | null; shipping_method_id: number | null; enabled: boolean; base_prices: Record<number, number | string | null>; tiers: Tier[] };
type Currency = { id: number; code: string; name: string; decimal_places: number; default: boolean };
type Method = { id: number; name: string; charge_by: string; weight_unit: string };
type Named = { id: number; name: string };

const props = defineProps<{
    zone: {
        id: number;
        name: string;
        type: string;
        countries: number[];
        country_id: number | null;
        states: number[];
        postcodes: string;
        exclusion_lists: number[];
        rates: Rate[];
    };
    countries: (Named & { iso2: string | null })[];
    states: (Named & { code: string; country_id: number })[];
    currencies: Currency[];
    customerGroups: Named[];
    shippingMethods: Method[];
    exclusionLists: Named[];
    pricesIncludeTax: boolean;
    urls: { index: string; update: string; destroy: string };
}>();

const { t } = useI18n();
const tr = (key: string, params?: Record<string, unknown>): string => t(`lunar-shipping::panel.zones.${key}`, params ?? {});

const types = ['unrestricted', 'countries', 'states', 'postcodes'];

const breadcrumbs = computed(() => [
    { label: t('nav.settings') },
    { label: tr('title'), href: props.urls.index },
    { label: props.zone.name, current: true },
]);

const form = useForm({
    name: props.zone.name,
    type: props.zone.type,
    countries: [...props.zone.countries],
    country_id: props.zone.country_id,
    states: [...props.zone.states],
    postcodes: props.zone.postcodes,
    exclusion_lists: [...props.zone.exclusion_lists],
    rates: props.zone.rates.map((rate) => ({ ...rate, base_prices: { ...rate.base_prices }, tiers: rate.tiers.map((tier) => ({ ...tier })) })),
});

const submit = (): void => {
    form.put(props.urls.update, { preserveScroll: true });
};

const countryOptions = computed(() => props.countries.map((country) => ({ value: country.id, label: country.name })));
const stateOptions = computed(() =>
    props.states.filter((state) => state.country_id === form.country_id).map((state) => ({ value: state.id, label: state.name })));
const exclusionListOptions = computed(() => props.exclusionLists.map((list) => ({ value: list.id, label: list.name })));

// A different country makes the chosen states meaningless.
const setCountry = (value: string | number): void => {
    form.country_id = Number(value);
    form.states = [];
};

const defaultCurrency = computed(() => props.currencies.find((currency) => currency.default) ?? props.currencies[0]);

const methodFor = (rate: Rate): Method | undefined => props.shippingMethods.find((method) => method.id === rate.shipping_method_id);

const minimumLabel = (rate: Rate): string => {
    const method = methodFor(rate);

    return method?.charge_by === 'weight' ? tr('tier_min_weight', { unit: method.weight_unit }) : tr('tier_min_spend');
};

const addRate = (): void => {
    form.rates.push({ id: null, shipping_method_id: props.shippingMethods[0]?.id ?? null, enabled: true, base_prices: {}, tiers: [] });
};

const addTier = (rate: Rate): void => {
    rate.tiers.push({ customer_group_id: null, currency_id: defaultCurrency.value?.id ?? 0, price: '', min_quantity: '' });
};

const error = (key: string): string | undefined => (form.errors as Record<string, string>)[key];

const deleting = ref(false);
</script>

<template>
    <SettingsShell :title="tr('edit_title', { name: zone.name })" :breadcrumbs="breadcrumbs" wide>
        <template #actions>
            <Button variant="ghost" icon="trash" @click="deleting = true">{{ t('common.delete') }}</Button>
            <Button variant="primary" icon="check" size="sm" :disabled="form.processing" @click="submit">{{ t('common.save') }}</Button>
        </template>

        <SideCard :title="tr('section_details')">
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <FieldLabel required>{{ tr('field_name') }}</FieldLabel>
                    <TextInput v-model="form.name" :invalid="!!form.errors.name" />
                    <div v-if="form.errors.name" class="mt-1 text-[11px] text-danger">{{ form.errors.name }}</div>
                </div>
                <div>
                    <FieldLabel>{{ tr('field_type') }}</FieldLabel>
                    <Select v-model="form.type">
                        <option v-for="type in types" :key="type" :value="type">{{ tr(`type_${type}`) }}</option>
                    </Select>
                </div>
            </div>
        </SideCard>

        <SideCard :title="tr('section_coverage')">
            <p v-if="form.type === 'unrestricted'" class="m-0 text-xs text-ink-500">{{ tr('unrestricted_hint') }}</p>

            <ChipPicker
                v-else-if="form.type === 'countries'"
                v-model="form.countries"
                :options="countryOptions"
                :placeholder="tr('add_country')"
                :empty-text="tr('no_countries')"
                :remove-label="t('lunar-shipping::panel.common.remove')"
            />

            <div v-else class="flex flex-col gap-3">
                <div class="max-w-[340px]">
                    <FieldLabel required>{{ tr('field_country') }}</FieldLabel>
                    <Combobox :model-value="form.country_id" :options="countryOptions" :invalid="!!form.errors.country_id" @change="setCountry" />
                    <div v-if="form.errors.country_id" class="mt-1 text-[11px] text-danger">{{ form.errors.country_id }}</div>
                </div>

                <div v-if="form.type === 'states'">
                    <FieldLabel>{{ tr('field_states') }}</FieldLabel>
                    <ChipPicker
                        v-model="form.states"
                        :options="stateOptions"
                        :placeholder="tr('add_state')"
                        :empty-text="tr('no_states')"
                        :remove-label="t('lunar-shipping::panel.common.remove')"
                    />
                </div>

                <div v-else>
                    <FieldLabel>{{ tr('field_postcodes') }}</FieldLabel>
                    <Textarea v-model="form.postcodes" :rows="8" :invalid="!!form.errors.postcodes" />
                    <div class="mt-1 text-[11px] text-ink-500">{{ tr('postcodes_hint') }}</div>
                </div>
            </div>
        </SideCard>

        <SideCard :title="tr('section_exclusions')">
            <p class="mt-0 mb-3 text-xs text-ink-500">{{ tr('exclusions_desc') }}</p>
            <ChipPicker
                v-model="form.exclusion_lists"
                :options="exclusionListOptions"
                :placeholder="tr('add_exclusion_list')"
                :empty-text="tr('no_exclusion_lists')"
                :remove-label="t('lunar-shipping::panel.common.remove')"
            />
        </SideCard>

        <SideCard :title="tr('section_rates')">
            <template #actions>
                <Button variant="primary" size="sm" icon="plus" @click="addRate">{{ tr('add_rate') }}</Button>
            </template>

            <p class="mt-0 mb-3 text-xs text-ink-500">
                {{ tr('rates_desc') }} {{ pricesIncludeTax ? tr('prices_inc_tax') : tr('prices_excl_tax') }}
            </p>

            <div v-if="!form.rates.length" class="px-6 py-10 text-center text-xs text-ink-500 border border-dashed border-line rounded-md">
                {{ tr('no_rates') }}
            </div>

            <div v-for="(rate, index) in form.rates" :key="rate.id ?? `new-${index}`" class="mb-3 p-3.5 border border-line rounded-lg bg-surface">
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-[220px] flex-1">
                        <FieldLabel required>{{ tr('field_method') }}</FieldLabel>
                        <Select v-model="rate.shipping_method_id" :invalid="!!error(`rates.${index}.shipping_method_id`)">
                            <option v-for="method in shippingMethods" :key="method.id" :value="method.id">{{ method.name }}</option>
                        </Select>
                    </div>
                    <label class="flex items-center gap-2 pb-1.5 cursor-pointer">
                        <Toggle :on="rate.enabled" @toggle="rate.enabled = !rate.enabled" />
                        <span class="text-[12.5px] text-ink-900">{{ tr('field_enabled') }}</span>
                    </label>
                    <Button variant="ghost" size="sm" icon="trash" :aria-label="tr('remove_rate')" @click="form.rates.splice(index, 1)" />
                </div>

                <div class="mt-3">
                    <FieldLabel required>{{ tr('base_prices') }}</FieldLabel>
                    <div class="grid sm:grid-cols-3 gap-2">
                        <div v-for="currency in currencies" :key="currency.id">
                            <TextInput
                                v-model="rate.base_prices[currency.id]"
                                type="number"
                                min="0"
                                step="any"
                                :invalid="!!error(`rates.${index}.base_prices.${currency.id}`)"
                            >
                                <template #prefix><span class="text-[11px] font-mono">{{ currency.code }}</span></template>
                            </TextInput>
                            <div v-if="error(`rates.${index}.base_prices.${currency.id}`)" class="mt-1 text-[11px] text-danger">
                                {{ error(`rates.${index}.base_prices.${currency.id}`) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <div class="flex items-center justify-between">
                        <FieldLabel>{{ tr('tiers') }}</FieldLabel>
                        <Button size="sm" icon="plus" @click="addTier(rate)">{{ tr('add_tier') }}</Button>
                    </div>
                    <div class="mb-2 text-[11px] text-ink-500">{{ tr('tiers_hint') }}</div>

                    <div v-for="(tier, tierIndex) in rate.tiers" :key="tierIndex" class="grid sm:grid-cols-[1.2fr_0.8fr_1fr_1fr_auto] gap-2 mb-2 items-start">
                        <Select v-model="tier.customer_group_id" :aria-label="tr('tier_customer_group')">
                            <option :value="null">{{ t('lunar-shipping::panel.common.any_customer_group') }}</option>
                            <option v-for="group in customerGroups" :key="group.id" :value="group.id">{{ group.name }}</option>
                        </Select>
                        <Select v-model="tier.currency_id" :aria-label="tr('tier_currency')">
                            <option v-for="currency in currencies" :key="currency.id" :value="currency.id">{{ currency.code }}</option>
                        </Select>
                        <div>
                            <TextInput v-model="tier.min_quantity" type="number" min="0" step="any" :placeholder="minimumLabel(rate)" :invalid="!!error(`rates.${index}.tiers.${tierIndex}.min_quantity`)" />
                            <div v-if="error(`rates.${index}.tiers.${tierIndex}.min_quantity`)" class="mt-1 text-[11px] text-danger">
                                {{ error(`rates.${index}.tiers.${tierIndex}.min_quantity`) }}
                            </div>
                        </div>
                        <TextInput v-model="tier.price" type="number" min="0" step="any" :placeholder="tr('tier_price')" :invalid="!!error(`rates.${index}.tiers.${tierIndex}.price`)" />
                        <Button variant="ghost" size="sm" icon="trash" :aria-label="t('lunar-shipping::panel.common.remove')" @click="rate.tiers.splice(tierIndex, 1)" />
                    </div>
                </div>
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
