import ZonesIndex from './pages/Zones/Index.vue';
import ZonesEdit from './pages/Zones/Edit.vue';
import MethodsIndex from './pages/Methods/Index.vue';
import MethodsEdit from './pages/Methods/Edit.vue';
import ExclusionListsIndex from './pages/ExclusionLists/Index.vue';
import ExclusionListsEdit from './pages/ExclusionLists/Edit.vue';
import ShippingDiscountForm from './components/ShippingDiscountForm.vue';

// Registered at the top level: the panel mounts after every add-on script has run, and a page or
// component registered later (inside LunarPanel.booting()) would be missing on a hard load.
window.LunarPanel.registerPages({
    'lunar-shipping::Zones/Index': ZonesIndex,
    'lunar-shipping::Zones/Edit': ZonesEdit,
    'lunar-shipping::Methods/Index': MethodsIndex,
    'lunar-shipping::Methods/Edit': MethodsEdit,
    'lunar-shipping::ExclusionLists/Index': ExclusionListsIndex,
    'lunar-shipping::ExclusionLists/Edit': ExclusionListsEdit,
});

window.LunarPanel.registerComponents('lunar-shipping', {
    ShippingDiscountForm,
});
