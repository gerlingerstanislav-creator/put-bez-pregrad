import { ref, onMounted } from 'vue';
import { isCompleteRuPhone } from '../utils/contactInput.js';

/**
 * Handles the public ride request. Contacts are delivered exclusively
 * through the existing server-side PHPMailer/SMTP endpoint.
 */
export function useTransportRequest() {
  const departure = ref('');
  const destination = ref('');
  const passenger = ref('');
  const contact = ref('');
  const website = ref(''); // Honeypot: intentionally hidden from human visitors.
  const consent = ref(false);
  const challenge = ref('');
  const sending = ref(false);
  const requestMessage = ref('');
  const requestError = ref(false);

  async function renewChallenge() {
    const response = await fetch('/api/public.php', {
      credentials: 'same-origin',
      cache: 'no-store',
    });
    if (!response.ok) throw new Error('Не удалось подготовить защиту заявки. Обновите страницу.');
    const data = await response.json();
    if (typeof data.challenge !== 'string' || !data.challenge) {
      throw new Error('Защита формы временно недоступна. Позвоните нам по телефону.');
    }
    challenge.value = data.challenge;
  }

  async function sendRequest() {
    if (sending.value || !consent.value || !departure.value.trim()
      || !destination.value.trim() || !contact.value.trim()) return;

    if (!isCompleteRuPhone(contact.value)) {
      requestError.value = true;
      requestMessage.value = 'Проверьте телефон: нужны 10 цифр после +7';
      return;
    }

    sending.value = true;
    requestError.value = false;
    requestMessage.value = '';

    try {
      if (!challenge.value) {
        await renewChallenge();
        // A newly issued challenge is deliberately too young for the server.
        await new Promise((resolve) => setTimeout(resolve, 3200));
      }

      const response = await fetch('/api/submit.php', {
        method: 'POST',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          departure: departure.value.trim(),
          destination: destination.value.trim(),
          passenger: passenger.value.trim(),
          contact: contact.value.trim(),
          website: website.value,
          consent: consent.value,
          challenge: challenge.value,
        }),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Не удалось отправить заявку');

      requestMessage.value = data.message || 'Заявка отправлена. Мы свяжемся с вами.';
      departure.value = '';
      destination.value = '';
      passenger.value = '';
      contact.value = '';
      consent.value = false;
    } catch (error) {
      requestError.value = true;
      requestMessage.value = error instanceof Error ? error.message
        : 'Ошибка отправки. Позвоните нам по телефону.';
    } finally {
      try {
        await renewChallenge();
      } catch {
        // A new challenge will be requested on the next submission.
        challenge.value = '';
      }
      sending.value = false;
    }
  }

  // Preload proof when the form becomes visible so legitimate visitors
  // never have to wait for the minimum anti-bot age after pressing Submit.
  onMounted(() => { renewChallenge().catch(() => {}); });

  return {
    departure, destination, passenger, contact, website, consent, challenge,
    sending, requestMessage, requestError, sendRequest,
  };
}
