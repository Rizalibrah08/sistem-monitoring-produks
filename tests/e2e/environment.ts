import { resolve } from 'node:path';

export const browserDatabase = process.env.AISHA_E2E_DATABASE ?? resolve('.superpowers/backend-foundation-tests', `browser-${process.pid}-${Date.now()}.sqlite`);
process.env.AISHA_E2E_DATABASE = browserDatabase;

export const browserEnvironment = {
    APP_ENV: 'local',
    APP_KEY: 'base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE=',
    APP_URL: 'http://127.0.0.1:8013',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: browserDatabase,
    DB_URL: '',
    SESSION_DRIVER: 'database',
    SESSION_COOKIE: 'aisha_monitor_browser_tests',
    SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'database',
    QUEUE_CONNECTION: 'sync',
    BCRYPT_ROUNDS: '4',
};
