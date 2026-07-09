const crypto = require('crypto')

jest.mock('node-fetch')
const fetch = require('node-fetch')

jest.mock('@adobe/aio-sdk', () => ({
  Core: { Logger: () => ({ debug: jest.fn(), info: jest.fn(), error: jest.fn() }) },
  State: { init: jest.fn() }
}))
const { State } = require('@adobe/aio-sdk')

const action = require('../actions/notify-order/index.js')

const SECRET = 'test-shared-secret'

function sign (rawBody) {
  return crypto.createHmac('sha256', SECRET).update(rawBody, 'utf8').digest('hex')
}

function buildParams (payloadObj, { signature, headers = {} } = {}) {
  const rawBody = JSON.stringify(payloadObj)
  const sig = signature !== undefined ? signature : sign(rawBody)
  return {
    __ow_body: Buffer.from(rawBody).toString('base64'),
    __ow_headers: { 'x-awa-signature': sig, ...headers },
    WEBHOOK_SHARED_SECRET: SECRET,
    WHATSAPP_TOKEN: 'fake-token',
    WHATSAPP_PHONE_ID: '1234567890',
    LOG_LEVEL: 'debug'
  }
}

describe('notify-order', () => {
  let stateStore

  beforeEach(() => {
    stateStore = { get: jest.fn().mockResolvedValue(undefined), put: jest.fn().mockResolvedValue(undefined) }
    State.init.mockResolvedValue(stateStore)
    fetch.mockReset()
    fetch.mockResolvedValue({ ok: true, json: async () => ({ messages: [{ id: 'wamid.test' }] }) })
  })

  test('rejects requests with an invalid signature', async () => {
    const params = buildParams({ order_id: '000000042', event: 'placed', phone: '5516991234567' }, { signature: 'deadbeef' })
    const result = await action.main(params)
    expect(result.statusCode).toBe(401)
  })

  test('rejects payloads missing required fields', async () => {
    const rawBody = JSON.stringify({ event: 'placed' })
    const params = {
      __ow_body: Buffer.from(rawBody).toString('base64'),
      __ow_headers: { 'x-awa-signature': sign(rawBody) },
      WEBHOOK_SHARED_SECRET: SECRET
    }
    const result = await action.main(params)
    expect(result.statusCode).toBe(400)
  })

  test('sends a WhatsApp message and marks the event as processed', async () => {
    const params = buildParams({
      order_id: '000000042',
      event: 'placed',
      phone: '5516991234567',
      total: 'R$ 199,90'
    })

    const result = await action.main(params)

    expect(result.statusCode).toBe(200)
    expect(result.body.status).toBe('sent')
    expect(fetch).toHaveBeenCalledTimes(1)

    const [, requestInit] = fetch.mock.calls[0]
    const sentBody = JSON.parse(requestInit.body)
    expect(sentBody.to).toBe('5516991234567')
    expect(sentBody.text.body).toContain('000000042')
    expect(stateStore.put).toHaveBeenCalledWith(
      'notify-order:000000042:placed',
      true,
      expect.objectContaining({ ttl: expect.any(Number) })
    )
  })

  test('short-circuits duplicate deliveries using idempotency state', async () => {
    stateStore.get.mockResolvedValue(true)
    const params = buildParams({ order_id: '000000042', event: 'paid', phone: '5516991234567' })

    const result = await action.main(params)

    expect(result.statusCode).toBe(200)
    expect(result.body.status).toBe('duplicate')
    expect(fetch).not.toHaveBeenCalled()
  })

  test('returns 500 when the WhatsApp API call fails', async () => {
    fetch.mockResolvedValue({ ok: false, status: 401, json: async () => ({ error: { message: 'Invalid token' } }) })
    const params = buildParams({ order_id: '000000099', event: 'shipped', phone: '5516991234567', tracking: 'Correios: BR123' })

    const result = await action.main(params)

    expect(result.statusCode).toBe(500)
    expect(result.body.error).toContain('Invalid token')
  })
})
