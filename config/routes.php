<?php

defined('_SEVEN') or die('No direct script access allowed');

// ─── Core routes (non-module) ─────────────────────────────────────────────────

// Setup
Route::add('setup',         ['controller' => 'setup', 'action' => 'index']);
Route::add('setup.install', ['controller' => 'setup', 'action' => 'install']);

// Site
Route::add('home',   ['controller' => 'home',  'action' => 'index']);
Route::add('about',  ['controller' => 'about', 'action' => 'index']);

// Storefront (Vue 3 SPA over Shop API; rendered when Ecom plugin is installed)
Route::add('shop',         ['controller' => 'shop', 'action' => 'index']);
Route::add('shop.cart',    ['controller' => 'shop', 'action' => 'cart']);
Route::add('shop.product', ['controller' => 'shop', 'action' => 'product']);

// Auth — site
Route::add('auth.login',    ['controller' => 'auth', 'action' => 'index']);
Route::add('auth.register', ['controller' => 'auth', 'action' => 'register']);
Route::add('auth.forgot',   ['controller' => 'auth', 'action' => 'forgot']);
Route::add('auth.recover',  ['controller' => 'auth', 'action' => 'recover']);
Route::add('auth.logout',   ['controller' => 'auth', 'action' => 'logout']);

// ─── Admin core ───────────────────────────────────────────────────────────────

Route::add('admin.dashboard', ['controller' => 'home', 'action' => 'index', 'prefix' => 'admin']);

// Users
Route::add('admin.users',       ['controller' => 'user', 'action' => 'index',  'prefix' => 'admin']);
Route::add('admin.user.edit',   ['controller' => 'user', 'action' => 'edit',   'prefix' => 'admin']);
Route::add('admin.user.update', ['controller' => 'user', 'action' => 'update', 'prefix' => 'admin']);
Route::add('admin.user.delete', ['controller' => 'user', 'action' => 'delete', 'prefix' => 'admin']);

// Settings
Route::add('admin.settings',            ['controller' => 'setting', 'action' => 'index',         'prefix' => 'admin']);
Route::add('admin.setting.save',        ['controller' => 'setting', 'action' => 'save',          'prefix' => 'admin']);
Route::add('admin.setting.uploadbrand', ['controller' => 'setting', 'action' => 'uploadbrand',   'prefix' => 'admin']);
Route::add('admin.setting.resetbrand',  ['controller' => 'setting', 'action' => 'resetbrand',    'prefix' => 'admin']);
Route::add('admin.ui.lang',             ['controller' => 'setting', 'action' => 'setuilang',     'prefix' => 'admin']);
Route::add('admin.ui.lang.add',         ['controller' => 'setting', 'action' => 'adduilang',     'prefix' => 'admin']);
Route::add('admin.ui.lang.archive',     ['controller' => 'setting', 'action' => 'archiveuilang', 'prefix' => 'admin']);
Route::add('admin.ui.lang.restore',     ['controller' => 'setting', 'action' => 'restoreuilang', 'prefix' => 'admin']);

// Languages
Route::add('admin.languages',    ['controller' => 'language', 'action' => 'index',      'prefix' => 'admin']);
Route::add('admin.lang.store',   ['controller' => 'language', 'action' => 'store',      'prefix' => 'admin']);
Route::add('admin.lang.default', ['controller' => 'language', 'action' => 'setdefault', 'prefix' => 'admin']);
Route::add('admin.lang.delete',  ['controller' => 'language', 'action' => 'delete',     'prefix' => 'admin']);
Route::add('admin.lang.restore', ['controller' => 'language', 'action' => 'restore',    'prefix' => 'admin']);

// Cache
Route::add('admin.cache',       ['controller' => 'cache', 'action' => 'index', 'prefix' => 'admin']);
Route::add('admin.cache.flush', ['controller' => 'cache', 'action' => 'flush', 'prefix' => 'admin']);
Route::add('admin.cache.save',  ['controller' => 'cache', 'action' => 'save',  'prefix' => 'admin']);

// Modules / Themes / Help / UI
Route::add('admin.modules',        ['controller' => 'module', 'action' => 'index',   'prefix' => 'admin']);
Route::add('admin.module.enable',  ['controller' => 'module', 'action' => 'enable',  'prefix' => 'admin']);
Route::add('admin.module.disable', ['controller' => 'module', 'action' => 'disable', 'prefix' => 'admin']);
Route::add('admin.themes',         ['controller' => 'theme',  'action' => 'index',    'prefix' => 'admin']);
Route::add('admin.theme.activate', ['controller' => 'theme',  'action' => 'activate', 'prefix' => 'admin']);
Route::add('admin.help',           ['controller' => 'help',   'action' => 'index',    'prefix' => 'admin']);
Route::add('admin.help.topic',     ['controller' => 'help',   'action' => 'topic',    'prefix' => 'admin']);
Route::add('admin.ui',             ['controller' => 'ui',     'action' => 'index',    'prefix' => 'admin']);

// ─── API core ─────────────────────────────────────────────────────────────────

// Auth
Route::add('api.auth.login',    ['controller' => 'auth', 'action' => 'login',    'prefix' => 'api']);
Route::add('api.auth.logout',   ['controller' => 'auth', 'action' => 'logout',   'prefix' => 'api']);
Route::add('api.auth.me',       ['controller' => 'auth', 'action' => 'me',       'prefix' => 'api']);
Route::add('api.auth.register', ['controller' => 'auth', 'action' => 'register', 'prefix' => 'api']);
Route::add('api.auth.forgot',   ['controller' => 'auth', 'action' => 'forgot',   'prefix' => 'api']);
Route::add('api.auth.reset',    ['controller' => 'auth', 'action' => 'reset',    'prefix' => 'api']);

// Posts API
Route::add('api.post.index',  ['controller' => 'post', 'action' => 'index',  'prefix' => 'api']);
Route::add('api.post.show',   ['controller' => 'post', 'action' => 'show',   'prefix' => 'api']);
Route::add('api.post.store',  ['controller' => 'post', 'action' => 'store',  'prefix' => 'api']);
Route::add('api.post.update', ['controller' => 'post', 'action' => 'update', 'prefix' => 'api']);
Route::add('api.post.delete', ['controller' => 'post', 'action' => 'delete', 'prefix' => 'api']);

// Pages API
Route::add('api.page.index',  ['controller' => 'page', 'action' => 'index',  'prefix' => 'api']);
Route::add('api.page.show',   ['controller' => 'page', 'action' => 'show',   'prefix' => 'api']);
Route::add('api.page.store',  ['controller' => 'page', 'action' => 'store',  'prefix' => 'api']);
Route::add('api.page.update', ['controller' => 'page', 'action' => 'update', 'prefix' => 'api']);
Route::add('api.page.delete', ['controller' => 'page', 'action' => 'delete', 'prefix' => 'api']);

// Users API
Route::add('api.user.index',  ['controller' => 'user', 'action' => 'index',  'prefix' => 'api']);
Route::add('api.user.show',   ['controller' => 'user', 'action' => 'show',   'prefix' => 'api']);
Route::add('api.user.update', ['controller' => 'user', 'action' => 'update', 'prefix' => 'api']);
Route::add('api.user.delete', ['controller' => 'user', 'action' => 'delete', 'prefix' => 'api']);

// Calendar & Language API
Route::add('api.calendar.events', ['controller' => 'calendar', 'action' => 'events', 'prefix' => 'api']);
Route::add('api.language.list',   ['controller' => 'language', 'action' => 'list',   'prefix' => 'api']);
Route::add('api.language.known',  ['controller' => 'language', 'action' => 'known',  'prefix' => 'api']);

// ─── New admin: Plugins / RBAC / Activity / 2FA / Content ────────────────────

// Plugins (legacy 'module' prefix kept; new actions added)
Route::add('admin.module.install',   ['controller' => 'module', 'action' => 'install',   'prefix' => 'admin']);
Route::add('admin.module.uninstall', ['controller' => 'module', 'action' => 'uninstall', 'prefix' => 'admin']);

// RBAC: roles & permissions
Route::add('admin.roles',         ['controller' => 'role', 'action' => 'index',  'prefix' => 'admin']);
Route::add('admin.role.create',   ['controller' => 'role', 'action' => 'create', 'prefix' => 'admin']);
Route::add('admin.role.store',    ['controller' => 'role', 'action' => 'store',  'prefix' => 'admin']);
Route::add('admin.role.edit',     ['controller' => 'role', 'action' => 'edit',   'prefix' => 'admin']);
Route::add('admin.role.update',   ['controller' => 'role', 'action' => 'update', 'prefix' => 'admin']);
Route::add('admin.role.delete',   ['controller' => 'role', 'action' => 'delete', 'prefix' => 'admin']);
Route::add('admin.role.assign',   ['controller' => 'role', 'action' => 'assign', 'prefix' => 'admin']);

// Activity log
Route::add('admin.activity', ['controller' => 'activity', 'action' => 'index', 'prefix' => 'admin']);

// Two-factor auth (admin)
Route::add('admin.2fa',         ['controller' => 'twofactor', 'action' => 'index',   'prefix' => 'admin']);
Route::add('admin.2fa.enable',  ['controller' => 'twofactor', 'action' => 'enable',  'prefix' => 'admin']);
Route::add('admin.2fa.disable', ['controller' => 'twofactor', 'action' => 'disable', 'prefix' => 'admin']);

// ─── API v1 (JWT auth + RBAC + versioned) ────────────────────────────────────

// ─── GraphQL ─────────────────────────────────────────────────────────────────

Route::add('api.v1.graphql',            ['controller' => 'graphQL', 'action' => 'endpoint',   'prefix' => 'api/v1']);
Route::add('api.v1.graphql.playground', ['controller' => 'graphQL', 'action' => 'playground', 'prefix' => 'api/v1']);

Route::add('api.v1.auth.login',   ['controller' => 'authv1', 'action' => 'login',   'prefix' => 'api/v1']);
Route::add('api.v1.auth.refresh', ['controller' => 'authv1', 'action' => 'refresh', 'prefix' => 'api/v1']);
Route::add('api.v1.auth.logout',  ['controller' => 'authv1', 'action' => 'logout',  'prefix' => 'api/v1']);
Route::add('api.v1.auth.me',      ['controller' => 'authv1', 'action' => 'me',      'prefix' => 'api/v1']);

Route::add('api.v1.content.types',         ['controller' => 'contentv1', 'action' => 'types',        'prefix' => 'api/v1']);
Route::add('api.v1.content.list',          ['controller' => 'contentv1', 'action' => 'listEntries',  'prefix' => 'api/v1']);
Route::add('api.v1.content.show',          ['controller' => 'contentv1', 'action' => 'showEntry',    'prefix' => 'api/v1']);
Route::add('api.v1.content.create',        ['controller' => 'contentv1', 'action' => 'createEntry',  'prefix' => 'api/v1']);
Route::add('api.v1.content.update',        ['controller' => 'contentv1', 'action' => 'updateEntry',  'prefix' => 'api/v1']);
Route::add('api.v1.content.delete',        ['controller' => 'contentv1', 'action' => 'deleteEntry',  'prefix' => 'api/v1']);

// ─── Multi-site admin ────────────────────────────────────────────────────────

Route::add('admin.sites',             ['controller' => 'site', 'action' => 'index',      'prefix' => 'admin']);
Route::add('admin.sites.store',       ['controller' => 'site', 'action' => 'store',      'prefix' => 'admin']);
Route::add('admin.sites.edit',        ['controller' => 'site', 'action' => 'edit',       'prefix' => 'admin']);
Route::add('admin.sites.update',      ['controller' => 'site', 'action' => 'update',     'prefix' => 'admin']);
Route::add('admin.sites.host.add',    ['controller' => 'site', 'action' => 'hostAdd',    'prefix' => 'admin']);
Route::add('admin.sites.host.remove', ['controller' => 'site', 'action' => 'hostRemove', 'prefix' => 'admin']);

// ─── Notifications, calendar, mail queue, cron ──────────────────────────────

Route::add('admin.notifications',          ['controller' => 'notifications', 'action' => 'index',    'prefix' => 'admin']);
Route::add('admin.notifications.feed',     ['controller' => 'notifications', 'action' => 'feed',     'prefix' => 'admin']);
Route::add('admin.notifications.read',     ['controller' => 'notifications', 'action' => 'read',     'prefix' => 'admin']);
Route::add('admin.notifications.read_all', ['controller' => 'notifications', 'action' => 'readAll',  'prefix' => 'admin']);

Route::add('admin.calendar',          ['controller' => 'calendar', 'action' => 'index',  'prefix' => 'admin']);
Route::add('admin.calendar.feed',     ['controller' => 'calendar', 'action' => 'feed',   'prefix' => 'admin']);
Route::add('admin.calendar.store',    ['controller' => 'calendar', 'action' => 'store',  'prefix' => 'admin']);
Route::add('admin.calendar.delete',   ['controller' => 'calendar', 'action' => 'delete', 'prefix' => 'admin']);

Route::add('admin.mail',          ['controller' => 'mail', 'action' => 'index', 'prefix' => 'admin']);
Route::add('admin.mail.flush',    ['controller' => 'mail', 'action' => 'flush', 'prefix' => 'admin']);
Route::add('admin.mail.retry',    ['controller' => 'mail', 'action' => 'retry', 'prefix' => 'admin']);

Route::add('admin.cron',          ['controller' => 'cron', 'action' => 'index',  'prefix' => 'admin']);
Route::add('admin.cron.run',      ['controller' => 'cron', 'action' => 'run',    'prefix' => 'admin']);
Route::add('admin.cron.toggle',   ['controller' => 'cron', 'action' => 'toggle', 'prefix' => 'admin']);

