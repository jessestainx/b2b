/*
 * notify-order — Adobe I/O Runtime action.
 *
 * Receives an order-event webhook fired by the AWA Motos Magento store
 * (GrupoAwamotos\WhatsAppCommerce\Observer\OrderNotification) and sends the
 * WhatsApp confirmation message from serverless Adobe infrastructure instead
 * of blocking a PHP-FPM worker on the store's VPS during checkout/admin
 * requests (sales_order_place_after, sales_order_invoice_pay,
 * sales_order_shipment_save_after, sales_order_creditmemo_save_after).
 *
 * Why this action is a 'raw' web action (not 'yes'): we need the exact raw
 * request body bytes to verify the HMAC-SHA256 signature Magento computes
 * over them. A parsed ('yes') web action would hand us an already-decoded
 * JSON object, making byte-for-byte signature verification unreliable.
 */

const fetch = require('node-fetch')
const crypto = require('crypto')
const { Core, State } = require('@adobe/aio-sdk')
const { errorResponse, stringParameters } = require('../utils')

const WHATSAPP_API_VERSION = 'v21.0'
const IDEMPOTENCY_TTL_SECONDS = 6 * 60 * 60 // 6h is comfortably longer than any Magento retry window

const MESSAGE_TEMPLATES = {
  placed: (o) => `✅ Pedido #${o.order_id} confirmado! Valor: ${o.total ?? ''}`,
  paid: (o) => `💰 Pagamento do pedido #${o.order_id} confirmado!`,
  shipped: (o) => `🚚 Pedido #${o.order_id} enviado! Rastreio: ${o.tracking || 'em breve'}`,
  refunded: (o) => `🔄 Reembolso do pedido #${o.order_id} processado`
}

function decodeRawBody (params) {
  if (typeof params.__ow_body !== 'string') {
    return ''
  }
  // OpenWhisk raw web actions always base64-encode the body regardless of content-type.
  return Buffer.from(params.__ow_body, 'base64').toString('utf8')
}

function verifySignature (rawBody, signatureHeader, sharedSecret) {
  if (!signatureHeader || !sharedSecret) {
    return false
  }
  const expected = crypto.createHmac('sha256', sharedSecret).update(rawBody, 'utf8').digest('hex')
  const provided = signatureHeader.replace(/^sha256=/, '')

  const expectedBuf = Buffer.from(expected, 'hex')
  const providedBuf = Buffer.from(provided, 'hex')
  if (expectedBuf.length !== providedBuf.length) {
    return false
  }
  return crypto.timingSafeEqual(expectedBuf, providedBuf)
}

function buildMessage (payload) {
  const template = MESSAGE_TEMPLATES[payload.event]
  return template ? template(payload) : `📦 Atualização do pedido #${payload.order_id}`
}

async function sendWhatsAppMessage ({ token, phoneId, to, message }, logger) {
  const url = `https://graph.facebook.com/${WHATSAPP_API_VERSION}/${phoneId}/messages`

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      messaging_product: 'whatsapp',
      to,
      type: 'text',
      text: { body: message }
    })
  })

  const responseBody = await response.json().catch(() => ({}))

  if (!response.ok) {
    logger.error('WhatsApp Cloud API rejected the message', { status: response.status, responseBody })
    throw new Error(`WhatsApp API error (${response.status}): ${responseBody?.error?.message || 'unknown error'}`)
  }

  return responseBody
}

async function main (params) {
  const logger = Core.Logger('notify-order', { level: params.LOG_LEVEL || 'info' })

  try {
    logger.debug(stringParameters(params))

    const rawBody = decodeRawBody(params)
    const signature = params.__ow_headers?.['x-awa-signature']

    if (!verifySignature(rawBody, signature, params.WEBHOOK_SHARED_SECRET)) {
      return errorResponse(401, 'invalid or missing signature', logger).error
    }

    let payload
    try {
      payload = JSON.parse(rawBody)
    } catch (e) {
      return errorResponse(400, 'body must be valid JSON', logger).error
    }

    const requiredFields = ['order_id', 'event', 'phone']
    const missing = requiredFields.filter((f) => !payload[f])
    if (missing.length > 0) {
      return errorResponse(400, `missing required field(s): ${missing.join(', ')}`, logger).error
    }

    if (!MESSAGE_TEMPLATES[payload.event]) {
      return errorResponse(400, `unsupported event type: ${payload.event}`, logger).error
    }

    const state = await State.init()
    const idempotencyKey = `notify-order:${payload.order_id}:${payload.event}`
    const alreadyProcessed = await state.get(idempotencyKey)

    if (alreadyProcessed) {
      logger.info('duplicate delivery ignored (idempotency hit)', { key: idempotencyKey })
      return {
        statusCode: 200,
        body: { status: 'duplicate', order_id: payload.order_id, event: payload.event }
      }
    }

    const message = buildMessage(payload)

    await sendWhatsAppMessage({
      token: params.WHATSAPP_TOKEN,
      phoneId: params.WHATSAPP_PHONE_ID,
      to: payload.phone,
      message
    }, logger)

    await state.put(idempotencyKey, true, { ttl: IDEMPOTENCY_TTL_SECONDS })

    logger.info('WhatsApp order notification sent', { order_id: payload.order_id, event: payload.event })

    return {
      statusCode: 200,
      body: { status: 'sent', order_id: payload.order_id, event: payload.event }
    }
  } catch (error) {
    logger.error(error)
    return {
      statusCode: 500,
      body: { error: error.message }
    }
  }
}

exports.main = main
