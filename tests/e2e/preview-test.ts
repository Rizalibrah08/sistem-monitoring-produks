import { test as base } from '@playwright/test';
export { expect } from '@playwright/test';

export const test = base.extend({
    page: async ({ page }, use) => {
        const goto = page.goto.bind(page);
        page.goto = (url, options) => {
            const path = url === '/' ? '/preview' : url.startsWith('/?') ? `/preview${url.slice(1)}` : url.startsWith('/') ? `/preview${url}` : url;
            return goto(path, options);
        };
        await use(page);
    },
});
