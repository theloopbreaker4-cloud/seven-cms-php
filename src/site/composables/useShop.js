// Storefront helpers: Shop API calls (/api/v1/shop/*), money formatting, localized fields and UI
// strings. The cart lives in a server-side cookie, so every call is same-origin with credentials.
import { apiFetch } from './useApi.js'

export const lang = window.__LANG__ || 'en'

const post = (path, data, method = 'POST') => apiFetch('/shop' + path, { method, body: JSON.stringify(data) })

export const shop = {
  products:   (params = {}) => apiFetch('/shop/products?' + new URLSearchParams({ lang, ...params })),
  product:    (slug)        => apiFetch(`/shop/products/${encodeURIComponent(slug)}?lang=${lang}`),
  categories: ()            => apiFetch('/shop/categories'),
  cart:       ()            => apiFetch('/shop/cart'),
  add:        (productId, quantity = 1) => post('/cart/items', { product_id: productId, quantity }),
  update:     (itemId, quantity)        => post(`/cart/items/${itemId}`, { quantity }, 'PUT'),
  remove:     (itemId)      => apiFetch(`/shop/cart/items/${itemId}`, { method: 'DELETE' }),
  discount:   (code)        => post('/cart/discount', { code }),
  checkout:   (data)        => post('/checkout', data),
}

/** Multilingual JSON (string or object) → text in the current language. */
export function localise(value) {
  if (value == null) return ''
  try {
    const obj = typeof value === 'string' && value.trim().startsWith('{') ? JSON.parse(value) : value
    if (typeof obj === 'string') return obj
    return obj?.[lang] || obj?.en || Object.values(obj || {})[0] || ''
  } catch {
    return String(value)
  }
}

/** Minor units (cents) → "25.00 USD". */
export function money(minor, currency = 'USD') {
  const n = Number(minor)
  if (!Number.isFinite(n)) return ''
  try {
    return new Intl.NumberFormat(lang, { style: 'currency', currency }).format(n / 100)
  } catch {
    return (n / 100).toFixed(2) + ' ' + currency
  }
}

const strings = {
  en: {
    shop: 'Shop', subtitle: 'Physical goods, downloads and subscriptions — try the whole checkout.',
    all: 'All', empty: 'No products yet.', cart: 'Cart', addToCart: 'Add to cart', added: 'Added to cart',
    viewCart: 'View cart', back: 'Back to shop', perMonth: 'per month', perYear: 'per year', perWeek: 'per week', perDay: 'per day',
    subscription: 'Subscription', digital: 'Download', service: 'Service', inStock: 'in stock', outOfStock: 'Out of stock',
    cartEmpty: 'Your cart is empty.', quantity: 'Qty', remove: 'Remove', subtotal: 'Subtotal', discount: 'Discount',
    shipping: 'Shipping', tax: 'Tax', total: 'Total', discountCode: 'Discount code', apply: 'Apply',
    checkout: 'Checkout', email: 'Email', firstName: 'First name', lastName: 'Last name', country: 'Country',
    city: 'City', address: 'Address', zip: 'Postal code', placeOrder: 'Place order', ordered: 'Thank you! Order',
    orderNote: 'Payment method "manual" — in a real shop this is where Stripe or PayPal takes over.', notFound: 'Product not found.',
  },
  ru: {
    shop: 'Магазин', subtitle: 'Товары, загрузки и подписки — попробуйте оформить заказ целиком.',
    all: 'Все', empty: 'Товаров пока нет.', cart: 'Корзина', addToCart: 'В корзину', added: 'Добавлено в корзину',
    viewCart: 'Открыть корзину', back: 'Назад в магазин', perMonth: 'в месяц', perYear: 'в год', perWeek: 'в неделю', perDay: 'в день',
    subscription: 'Подписка', digital: 'Загрузка', service: 'Услуга', inStock: 'в наличии', outOfStock: 'Нет в наличии',
    cartEmpty: 'Корзина пуста.', quantity: 'Кол-во', remove: 'Удалить', subtotal: 'Сумма', discount: 'Скидка',
    shipping: 'Доставка', tax: 'Налог', total: 'Итого', discountCode: 'Промокод', apply: 'Применить',
    checkout: 'Оформление', email: 'Email', firstName: 'Имя', lastName: 'Фамилия', country: 'Страна',
    city: 'Город', address: 'Адрес', zip: 'Индекс', placeOrder: 'Оформить заказ', ordered: 'Спасибо! Заказ',
    orderNote: 'Способ оплаты «manual» — в настоящем магазине здесь подключается Stripe или PayPal.', notFound: 'Товар не найден.',
  },
}
export const t = (key) => (strings[lang] || strings.en)[key] ?? strings.en[key] ?? key
