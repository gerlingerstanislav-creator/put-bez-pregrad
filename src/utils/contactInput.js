/**
 * Formatting for Russian phone numbers only. Preserve the actual dialable
 * digits; accept +7, 8 and national 10-digit numbers.
 */
export function nationalDigits(value) {
  const raw = String(value ?? '').trim();
  let digits = raw.replace(/\D/g, '');
  const hasCountryPrefix = raw.startsWith('+7') || /^\s*[78]\s*\(/.test(raw);
  if ((hasCountryPrefix || digits.length >= 11) && (digits.startsWith('7') || digits.startsWith('8'))) digits = digits.slice(1);
  return digits.slice(0, 10);
}
export function formatRuPhone(value) {
  const raw = String(value ?? '');
  const n = nationalDigits(raw);
  if (!n) return raw.trim() ? '+7 (' : '';
  const landline = n.startsWith('8422');
  const codeSize = landline ? 4 : 3;
  const firstSize = landline ? 2 : 3;
  let result = '+7 (' + n.slice(0, codeSize);
  if (n.length >= codeSize) result += ')';
  if (n.length > codeSize) result += ' ' + n.slice(codeSize, codeSize + firstSize);
  if (n.length > codeSize + firstSize) result += '-' + n.slice(codeSize + firstSize, codeSize + firstSize + 2);
  if (n.length > codeSize + firstSize + 2) result += '-' + n.slice(codeSize + firstSize + 2);
  return result;
}
export function isCompleteRuPhone(value) {
  const digits = String(value ?? '').replace(/\D/g, '');
  return digits.length === 11 && digits[0] === '7' && nationalDigits(value).length === 10;
}
export function telHref(value) {
  return isCompleteRuPhone(formatRuPhone(value))
    ? 'tel:+7' + nationalDigits(value)
    : '';
}
export function normalizeEmail(value) {
  const trimmed = String(value ?? '').trim();
  const at = trimmed.lastIndexOf('@');
  return at === -1 ? trimmed : trimmed.slice(0, at + 1) + trimmed.slice(at + 1).toLowerCase();
}
export function applyPhoneInput(event, setter) {
  const element = event.target;
  const raw = element.value;
  const position = element.selectionStart ?? raw.length;
  const beforeCaret = nationalDigits(raw.slice(0, position)).length;
  const formatted = formatRuPhone(raw);
  setter(formatted);
  element.value = formatted;
  // Count national digits, not punctuation or the fixed country code.
  const firstDigitPos = formatted.indexOf('(') + 1;
  let nationalCount = 0;
  let cursor = firstDigitPos;
  if (beforeCaret === 0) {
    cursor = Math.min(formatted.length, Math.max(0, firstDigitPos));
  } else {
    for (let i = firstDigitPos; i < formatted.length; i += 1) {
      if (/\d/.test(formatted[i])) nationalCount += 1;
      cursor = i + 1;
      if (nationalCount >= beforeCaret) break;
    }
  }
  if (document.activeElement === element && element.setSelectionRange) {
    element.setSelectionRange(cursor, cursor);
  }
}
export function phoneFocus(event, setter) {
  if (!event.target.value) {
    setter('+7 (');
    event.target.value = '+7 (';
    event.target.setSelectionRange?.(4, 4);
  }
}
export function phoneBlur(event, setter) {
  const normalized = nationalDigits(event.target.value);
  if (!normalized) {
    setter('');
    event.target.value = '';
  } else {
    const value = formatRuPhone(event.target.value);
    setter(value);
    event.target.value = value;
  }
}
