/**
 * awa-selectors.ts — AWA Motos
 * Central de URLs e seletores reutilizaveis para specs Playwright.
 * Valores alinhados aos seletores ja validados em execucao real (ver
 * header-core-interactions-p0.spec.ts / header.helpers.ts).
 */
export const awaUrls = {
  home: '/',
  contact: '/contact',
  cart: '/checkout/cart',
  checkout: '/checkout',
  b2bLogin: '/b2b/account/login/',
  b2bRegister: '/b2b/register',
  searchResults: '/catalogsearch/result/?q=',
  searchSuggest: '/search/ajax/suggest',
  forgotPassword: '/customer/account/forgotpassword',
  createPassword: '/customer/account/createpassword',
} as const;

export const awaSelectors = {
  header: {
    root: '[data-awa-header-content], .page-header, .awa-header, .awa-site-header',
    mobileToggle: '.awa-header-mobile-toggle, .action.nav-toggle',
    primaryNavigation: '#awa-primary-navigation',
    horizontalMenu: '.navigation.custommenu.main-nav, .header-control, .header-nav, .awa-nav-bar',
    horizontalMenuList: '.main-nav-list',
  },

  verticalMenu: {
    shell: '[data-role="awa-vertical-menu"]',
    trigger: '[data-role="awa-vertical-menu-trigger"]',
    list: '.togge-menu.list-category-dropdown',
  },

  search: {
    form: '#search_mini_form',
    input: 'input[data-awa-search-input="true"], #search',
    button: '#search_mini_form .action.search',
    block: '.block.block-search',
    suggestEndpoint: '/search/ajax/suggest',
  },

  minicart: {
    wrapper: '[data-block="minicart"], .minicart-wrapper, .awa-header-minicart',
    trigger: '.action.showcart.header-mini-cart, .action.showcart, .showcart',
    dropdown: '.block.block-minicart',
    shell: '[data-awa-header-minicart-shell="true"]',
    panel: '#awa-minicart-panel',
    fallback: '[data-awa-header-minicart-fallback="true"]',
  },

  account: {
    b2bLoginLink: 'a[href*="/b2b/account/login"]',
    b2bRegisterLink: 'a[href*="/b2b/register"]',
    customerAccountLink: 'a[href*="/customer/account"]',
    forgotPasswordLink: 'a[href*="/customer/account/forgotpassword"]',
    b2bStatusPanel: '.b2b-status-panel',
    b2bStatusTrigger: '.b2b-status-trigger, [aria-controls*="b2b"]',
  },
} as const;
