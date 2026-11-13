import { ref, onMounted } from 'vue';
import { formatRuPhone, normalizeEmail } from '../utils/contactInput.js';

const defaultSettings = {
  primary_phone: '+7 (8422) 71-73-03',
  secondary_phone: '+7 (967) 771-73-03',
  contact_email: 'bezpregrad73@yandex.ru',
  max_url: 'https://max.ru/u/f9LHodD0cOIMqlrwFHrhALk5VeAh-knTiBJCLnhZSFM3iMksBuOEXJ6JMec',
};

/** Provides a resilient public contact list; PHP remains the authoritative source. */
export function useSiteContacts() {
  const settings = ref({ ...defaultSettings });
  onMounted(async () => {
    try {
      const response = await fetch('/api/public.php', { cache: 'no-store' });
      if (!response.ok) return;
      const data = await response.json();
      if (!data || !data.settings) return;
      settings.value = {
        ...settings.value,
        ...data.settings,
        primary_phone: formatRuPhone(data.settings.primary_phone || settings.value.primary_phone),
        secondary_phone: formatRuPhone(data.settings.secondary_phone || settings.value.secondary_phone),
        contact_email: normalizeEmail(data.settings.contact_email || settings.value.contact_email),
      };
    } catch {
      // Public contact details remain usable during temporary API downtime.
    }
  });
  return { settings };
}
