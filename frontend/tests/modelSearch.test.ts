import { test } from 'node:test'
import assert from 'node:assert/strict'
import { matchesSearch } from '../utils/modelSearch.ts'

const row = { name: 'GPT-5.6 Terra', provider: 'DeepSeek' }

test('matches by model name', () => {
  assert.equal(matchesSearch(row, 'gpt'), true)
  assert.equal(matchesSearch(row, 'terra'), true)
  assert.equal(matchesSearch(row, 'GPT 5'), true)
})

test('matches by provider', () => {
  assert.equal(matchesSearch(row, 'deepseek'), true)
  assert.equal(matchesSearch(row, 'z-ai'), false)
})

test('provider slug spelling matches display name punctuation-insensitively', () => {
  assert.equal(matchesSearch(row, '~deepseek'), true)
  assert.equal(matchesSearch({ name: 'Kimi K2', provider: 'Z.AI' }, 'z.ai'), true)
  assert.equal(matchesSearch({ name: 'Kimi K2', provider: 'Z.AI' }, 'z-ai'), true)
})

test('no match returns false', () => {
  assert.equal(matchesSearch(row, 'claude'), false)
})

test('empty/whitespace/punctuation-only query matches everything', () => {
  assert.equal(matchesSearch(row, ''), true)
  assert.equal(matchesSearch(row, '   '), true)
  assert.equal(matchesSearch(row, '~~~'), true)
})