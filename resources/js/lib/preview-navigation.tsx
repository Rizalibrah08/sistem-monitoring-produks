import { Link as InertiaLink, router as inertiaRouter, type InertiaLinkProps } from '@inertiajs/react';

export function previewHref(href: string): string {
    if (!href.startsWith('/') || href.startsWith('//') || /^\/preview(?:\/|\?|#|$)/.test(href)) return href;
    return href === '/' ? '/preview' : href.startsWith('/?') ? `/preview${href.slice(1)}` : `/preview${href}`;
}

export function Link({ href, ...props }: InertiaLinkProps) {
    const destination = typeof href === 'string' ? previewHref(href) : href ? { ...href, url: previewHref(href.url) } : href;
    return <InertiaLink {...props} href={destination} />;
}

export const router = {
    visit(href: string, options?: Parameters<typeof inertiaRouter.visit>[1]) {
        inertiaRouter.visit(previewHref(href), options);
    },
};
