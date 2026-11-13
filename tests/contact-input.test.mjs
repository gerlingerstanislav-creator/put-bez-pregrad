import test from 'node:test';
import assert from 'node:assert/strict';
import {formatRuPhone, isCompleteRuPhone, normalizeEmail, telHref, nationalDigits} from '../src/utils/contactInput.js';

test('normalizes RU mobile numbers, country prefixes and pasted characters', () => {
  assert.equal(formatRuPhone('89261234567'), '+7 (926) 123-45-67');
  assert.equal(formatRuPhone('+7 926 123-45-67'), '+7 (926) 123-45-67');
  assert.equal(formatRuPhone('9261234567'), '+7 (926) 123-45-67');
  assert.equal(formatRuPhone('9'), '+7 (9');
  assert.equal(formatRuPhone('+7 ('), '+7 (');
  assert.equal(formatRuPhone(''), '');
  assert.equal(formatRuPhone('8 (967) 771-73-03'), '+7 (967) 771-73-03');
});
test('preserves Ulyanovsk landline formatting', () => {
  assert.equal(formatRuPhone('8 (8422) 71-73-03'), '+7 (8422) 71-73-03');
  assert.equal(telHref('8 (8422) 71-73-03'), 'tel:+78422717303');
  assert.equal(formatRuPhone('8422717303'), '+7 (8422) 71-73-03');
});
test('rejects short numbers and limits overlong input', () => {
  assert.equal(isCompleteRuPhone('+7 (999) 12'), false);
  assert.equal(isCompleteRuPhone('+7 (999) 123-45-67'), true);
  assert.equal(nationalDigits('+79991112233445'), '9991112233');
  assert.equal(telHref('wrong'), '');
});
test('keeps email local part and normalizes only domain', () => {
  assert.equal(normalizeEmail('  My.Name+Test@YANDEX.RU '), 'My.Name+Test@yandex.ru');
  assert.equal(normalizeEmail('person@'), 'person@');
  assert.equal(normalizeEmail(''), '');
});
